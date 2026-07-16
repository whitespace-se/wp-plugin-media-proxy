<?php

class WP_Error {
  private $message;
  private $data;

  public function __construct($code, $message, $data = null) {
    $this->message = $message;
    $this->data = $data;
  }

  public function get_error_message() {
    return $this->message;
  }

  public function get_error_data() {
    return $this->data;
  }
}

$wsmp_test_upload_dir = "/srv/www/example/shared/uploads";
$wsmp_test_response = ["response" => ["code" => 200], "body" => "asset"];
$wsmp_test_remote_url = null;

function add_action() {
}

function add_filter() {
}

function get_option($name) {
  if ($name === "wsmp_remote_uploads_path") {
    return "/app/uploads";
  }
  return $name === "wsmp_remote_url" ? "https://www.hoor.se" : null;
}

function home_url() {
  return "https://hoor1.dev.m7o.w8e.se";
}

function is_wp_error($value) {
  return $value instanceof WP_Error;
}

function wp_http_validate_url($url) {
  return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

function wp_get_upload_dir() {
  global $wsmp_test_upload_dir;
  return ["basedir" => $wsmp_test_upload_dir, "error" => false];
}

function wp_mkdir_p($path) {
  if (is_dir($path)) {
    return true;
  }
  if (!mkdir($path, 0775, true)) {
    return false;
  }
  chmod($path, 0775);
  return true;
}

function wp_safe_remote_get($url) {
  global $wsmp_test_remote_url, $wsmp_test_response;
  $wsmp_test_remote_url = $url;
  return $wsmp_test_response;
}

function wp_remote_retrieve_response_code($response) {
  return $response["response"]["code"];
}

function wp_remote_retrieve_body($response) {
  return $response["body"];
}

require_once dirname(__DIR__) . "/autoload/paths.php";
require_once dirname(__DIR__) . "/autoload/endpoint.php";
require_once dirname(__DIR__) . "/autoload/strategies.php";

$failures = [];

function wsmp_assert_same($expected, $actual, $message) {
  global $failures;
  if ($expected !== $actual) {
    $failures[] =
      $message .
      "\n  expected: " .
      var_export($expected, true) .
      "\n  actual:   " .
      var_export($actual, true);
  }
}

wsmp_assert_same(
  [
    "path" => "/wp-content/uploads/2026/07/image one.jpg",
    "relative_path" => "2026/07/image one.jpg",
  ],
  wsmp_parse_upload_path("/wp-content/uploads/2026/07/image one.jpg"),
  "A normal Municipio uploads path is accepted",
);
wsmp_assert_same(
  "/srv/www/example/shared/uploads/sites/2/image.jpg",
  wsmp_get_local_upload_path("/wp-content/uploads/sites/2/image.jpg"),
  "The local file is resolved below WordPress' uploads base directory",
);
wsmp_assert_same(
  "/app/uploads/legacy/image.jpg",
  wsmp_rewrite_remote_path("/app/uploads/legacy/image.jpg"),
  "The legacy uploads path uses the configured remote uploads prefix",
);
wsmp_assert_same(
  "/wp-content/uploads/2026/07/image.jpg",
  wsmp_rewrite_remote_path("/wp-content/uploads/2026/07/image.jpg"),
  "A Municipio uploads path is preserved for the remote origin",
);

foreach (
  [
    "/wp-content/uploads/../site/config.php",
    "/wp-content/uploads/%2e%2e/site/config.php",
    "/wp-content/uploads/%252e%252e/site/config.php",
    "/wp-content/uploads/directory%2Ffile.jpg",
    "/wp-content/uploads/directory\\file.jpg",
    "/wp-content/uploads//file.jpg",
    "/wp-content/uploads/",
    "/wp-content/plugins/file.jpg",
  ]
  as $invalid_path
) {
  wsmp_assert_same(
    null,
    wsmp_parse_upload_path($invalid_path),
    "Unsafe uploads path is rejected: " . $invalid_path,
  );
}

wsmp_assert_same(
  true,
  wsmp_urls_share_origin("https://www.hoor.se", "https://www.hoor.se/other"),
  "Matching HTTPS origins are detected",
);
wsmp_assert_same(
  false,
  wsmp_urls_share_origin("https://www.hoor.se", "https://hoor1.dev.m7o.w8e.se"),
  "Different hosts do not trigger the redirect-loop guard",
);
wsmp_assert_same(
  "fetch",
  wsmp_get_strategy_for_extension("WOFF2"),
  "Font extensions use the fetch strategy regardless of case",
);
wsmp_assert_same(
  "redirect",
  wsmp_get_strategy_for_extension("pdf"),
  "Documents keep the redirect strategy",
);

$wsmp_test_upload_dir =
  sys_get_temp_dir() . "/wsmp-tests-" . bin2hex(random_bytes(6));
$fetched_file = wsmp_fetch_remote_file(
  "/wp-content/uploads/2026/07/fetched-image.jpg",
);
wsmp_assert_same(
  false,
  is_wp_error($fetched_file),
  "A fetch response is written to the local uploads directory",
);
wsmp_assert_same(
  "asset",
  file_get_contents($wsmp_test_upload_dir . "/2026/07/fetched-image.jpg"),
  "The fetched response body is published as the requested file",
);
wsmp_assert_same(
  0644,
  fileperms($wsmp_test_upload_dir . "/2026/07/fetched-image.jpg") & 0777,
  "Fetched files use the WordPress file permission contract",
);
wsmp_assert_same(
  0775,
  fileperms($wsmp_test_upload_dir . "/2026/07") & 0777,
  "Fetched media directories preserve the shared uploads group permissions",
);
wsmp_assert_same(
  "https://www.hoor.se/wp-content/uploads/2026/07/fetched-image.jpg",
  $wsmp_test_remote_url,
  "The fetch request stays below the configured remote uploads path",
);

$wsmp_test_response = ["response" => ["code" => 404], "body" => ""];
$missing_file = wsmp_fetch_remote_file(
  "/wp-content/uploads/2026/07/missing-image.jpg",
);
wsmp_assert_same(
  true,
  is_wp_error($missing_file),
  "A missing remote file produces a controlled error",
);
wsmp_assert_same(
  ["status" => 404],
  $missing_file->get_error_data(),
  "A remote 404 remains a 404 instead of becoming a server error",
);
wsmp_assert_same(
  false,
  file_exists($wsmp_test_upload_dir . "/2026/07/missing-image.jpg"),
  "A missing remote file is not created locally",
);

$cleanup = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator(
    $wsmp_test_upload_dir,
    FilesystemIterator::SKIP_DOTS,
  ),
  RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($cleanup as $path) {
  $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
}
rmdir($wsmp_test_upload_dir);

if ($failures !== []) {
  fwrite(STDERR, implode("\n\n", $failures) . "\n");
  exit(1);
}

fwrite(STDOUT, "All media proxy path tests passed.\n");
