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

class WP_REST_Response {
  private $data;
  private $status;

  public function __construct($data, $status) {
    $this->data = $data;
    $this->status = $status;
  }

  public function get_data() {
    return $this->data;
  }

  public function get_status() {
    return $this->status;
  }
}

class WP_REST_Request {
}

$wsmp_test_upload_dir = "/srv/www/example/shared/uploads";
$wsmp_test_upload_url = "https://example.test/wp-content/uploads";
$wsmp_test_response = ["response" => ["code" => 200], "body" => "asset"];
$wsmp_test_remote_url = null;
$wsmp_test_remote_origin = "https://www.hoor.se";
$wsmp_test_remote_uploads_path = "/app/uploads";
$wsmp_test_redirect = null;

function add_action() {
}

function add_filter() {
}

function get_option($name) {
  if ($name === "wsmp_remote_uploads_path") {
    global $wsmp_test_remote_uploads_path;
    return $wsmp_test_remote_uploads_path;
  }
  if ($name === "wsmp_remote_url") {
    global $wsmp_test_remote_origin;
    return $wsmp_test_remote_origin;
  }
  return null;
}

function home_url($path = "") {
  return "https://hoor1.dev.m7o.w8e.se" . $path;
}

function is_wp_error($value) {
  return $value instanceof WP_Error;
}

function wp_http_validate_url($url) {
  return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

function wp_get_upload_dir() {
  global $wsmp_test_upload_dir, $wsmp_test_upload_url;
  return [
    "basedir" => $wsmp_test_upload_dir,
    "baseurl" => $wsmp_test_upload_url,
    "error" => false,
  ];
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

function wp_safe_redirect($location, $status, $by) {
  global $wsmp_test_redirect;
  $wsmp_test_redirect = compact("location", "status", "by");
  if (($GLOBALS["argv"][1] ?? null) === "--fetch-redirect-child") {
    fwrite(STDOUT, json_encode($wsmp_test_redirect) . "\n");
  }
  return true;
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

if (($argv[1] ?? null) === "--fetch-redirect-child") {
  $wsmp_test_upload_dir =
    sys_get_temp_dir() . "/wsmp-redirect-test-" . bin2hex(random_bytes(6));
  register_shutdown_function(function () use (&$wsmp_test_upload_dir) {
    wsmp_remove_test_directory($wsmp_test_upload_dir);
  });
  wsmp_fetch_response(
    "/wp-content/uploads/2026/07/redirected-image.jpg",
    "jpg",
    null,
    null,
    null,
  );
}

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

function wsmp_remove_test_directory($directory) {
  if (!is_dir($directory)) {
    return;
  }

  $cleanup = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
  );
  foreach ($cleanup as $path) {
    $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
  }
  rmdir($directory);
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
$wsmp_test_upload_dir = "/srv/www/example/shared/uploads/sites/2";
$wsmp_test_upload_url =
  "https://site-2.example.test/wp-content/uploads/sites/2";
wsmp_assert_same(
  "/srv/www/example/shared/uploads/sites/2/image.jpg",
  wsmp_get_local_upload_path("/wp-content/uploads/sites/2/image.jpg"),
  "A multisite uploads suffix is not duplicated below the site base directory",
);
$wsmp_test_upload_dir = "/srv/www/example/shared/uploads";
$wsmp_test_upload_url = "https://example.test/wp-content/uploads";
$remote_path_cases = [
  [
    "/app/uploads",
    "/app/uploads/legacy/image.jpg",
    "/app/uploads/legacy/image.jpg",
  ],
  [
    "/app/uploads",
    "/wp-content/uploads/legacy/image.jpg",
    "/app/uploads/legacy/image.jpg",
  ],
  [
    "/wp-content/uploads",
    "/app/uploads/legacy/image.jpg",
    "/wp-content/uploads/legacy/image.jpg",
  ],
  [
    "/wp-content/uploads",
    "/wp-content/uploads/legacy/image.jpg",
    "/wp-content/uploads/legacy/image.jpg",
  ],
];
foreach ($remote_path_cases as [$remote_root, $request_path, $expected_path]) {
  $wsmp_test_remote_uploads_path = $remote_root;
  wsmp_assert_same(
    $expected_path,
    wsmp_rewrite_remote_path($request_path),
    "The local uploads prefix is independent of the configured remote root",
  );
}
$wsmp_test_remote_uploads_path = "/app/uploads";

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
$wsmp_test_remote_origin = "https://hoor1.dev.m7o.w8e.se";
$same_origin_response = wsmp_endpoint(new WP_REST_Request());
wsmp_assert_same(
  400,
  $same_origin_response->get_status(),
  "The endpoint rejects a remote URL on the current site's origin",
);
$wsmp_test_remote_origin = "https://www.hoor.se";
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
$fetch_path_cases = [
  [
    "/app/uploads",
    "/app/uploads/2026/07/app-to-app.jpg",
    "https://www.hoor.se/app/uploads/2026/07/app-to-app.jpg",
  ],
  [
    "/wp-content/uploads",
    "/app/uploads/2026/07/app-to-wp-content.jpg",
    "https://www.hoor.se/wp-content/uploads/2026/07/app-to-wp-content.jpg",
  ],
  [
    "/app/uploads",
    "/wp-content/uploads/2026/07/wp-content-to-app.jpg",
    "https://www.hoor.se/app/uploads/2026/07/wp-content-to-app.jpg",
  ],
  [
    "/wp-content/uploads",
    "/wp-content/uploads/2026/07/wp-content-to-wp-content.jpg",
    "https://www.hoor.se/wp-content/uploads/2026/07/wp-content-to-wp-content.jpg",
  ],
];
foreach (
  $fetch_path_cases
  as [$remote_root, $request_path, $expected_remote_url]
) {
  $wsmp_test_remote_uploads_path = $remote_root;
  $fetched_path_case = wsmp_fetch_remote_file($request_path);
  wsmp_assert_same(
    false,
    is_wp_error($fetched_path_case),
    "Both supported local prefixes can fetch from both remote roots",
  );
  wsmp_assert_same(
    $expected_remote_url,
    $fetched_path_case["remote_url"] ?? null,
    "The fetch uses the configured remote uploads root",
  );
  wsmp_assert_same(
    "asset",
    file_get_contents(
      $wsmp_test_upload_dir .
        "/" .
        wsmp_parse_upload_path($request_path)["relative_path"],
    ),
    "The path matrix publishes each fetched file below local uploads",
  );
}
$wsmp_test_remote_uploads_path = "/app/uploads";
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
  [],
  glob($wsmp_test_upload_dir . "/2026/07/.wsmp-*"),
  "Atomic publication leaves no temporary media file behind",
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
  "https://www.hoor.se/app/uploads/2026/07/fetched-image.jpg",
  $wsmp_test_remote_url,
  "The fetch request stays below the configured remote uploads path",
);
wsmp_assert_same(
  "https://hoor1.dev.m7o.w8e.se/wp-content/uploads/2026/07/fetched-image.jpg",
  wsmp_get_local_media_url("/wp-content/uploads/2026/07/fetched-image.jpg"),
  "A fetched file is redirected to its normal local static URL",
);
wsmp_assert_same(
  null,
  wsmp_get_local_media_url("/wp-content/plugins/private.php"),
  "An invalid fetch path cannot produce a local redirect URL",
);

$redirect_output = [];
$redirect_exit_code = null;
exec(
  escapeshellarg(PHP_BINARY) .
    " " .
    escapeshellarg(__FILE__) .
    " --fetch-redirect-child",
  $redirect_output,
  $redirect_exit_code,
);
wsmp_assert_same(
  0,
  $redirect_exit_code,
  "A successful fetch response exits cleanly after redirecting",
);
wsmp_assert_same(
  [
    "location" =>
      "https://hoor1.dev.m7o.w8e.se/wp-content/uploads/2026/07/redirected-image.jpg",
    "status" => 302,
    "by" => "Whitespace Media Proxy",
  ],
  json_decode(end($redirect_output), true),
  "A successful fetch sends a 302 redirect to the now-local static file",
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

$wsmp_test_response = new WP_Error("http_request_failed", "Timed out");
$failed_request = wsmp_fetch_remote_file(
  "/wp-content/uploads/2026/07/failed-request.jpg",
);
wsmp_assert_same(
  ["status" => 502],
  $failed_request->get_error_data(),
  "A remote transport failure produces a controlled gateway error",
);
wsmp_assert_same(
  false,
  file_exists($wsmp_test_upload_dir . "/2026/07/failed-request.jpg"),
  "A failed remote request is never published locally",
);

wsmp_remove_test_directory($wsmp_test_upload_dir);

if ($failures !== []) {
  fwrite(STDERR, implode("\n\n", $failures) . "\n");
  exit(1);
}

fwrite(STDOUT, "All media proxy path tests passed.\n");
