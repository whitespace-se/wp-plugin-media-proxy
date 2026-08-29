<?php

/**
 * Downloads one validated remote file into WordPress' uploads directory.
 *
 * Publishing through a temporary file prevents concurrent requests from
 * exposing a partially downloaded asset.
 */
function wsmp_fetch_remote_file($path) {
  $remote_path = wsmp_rewrite_remote_path($path);
  $local_path = wsmp_get_local_upload_path($path);
  if ($remote_path === null || $local_path === null) {
    return new WP_Error("wsmp_invalid_path", "Invalid uploads path", [
      "status" => 400,
    ]);
  }

  $folder = dirname($local_path);
  if (!wp_mkdir_p($folder)) {
    return new WP_Error(
      "wsmp_local_directory_failed",
      "Failed to create local media directory",
      ["status" => 500],
    );
  }

  $temporary_path = tempnam($folder, ".wsmp-");
  if ($temporary_path === false) {
    return new WP_Error(
      "wsmp_local_write_failed",
      "Failed to create temporary media file",
      ["status" => 500],
    );
  }

  $remote = wsmp_get_remote();
  $url = $remote . $remote_path;
  $request_options = [
    "filename" => $temporary_path,
    "redirection" => 0,
    "stream" => true,
    "timeout" => 30,
  ];
  $basic_auth = wsmp_get_remote_basic_auth($remote);
  if ($basic_auth !== null) {
    $request_options["headers"] = [
      "Authorization" =>
        "Basic " .
        base64_encode($basic_auth["username"] . ":" . $basic_auth["password"]),
    ];
  }

  $remote_response = wp_safe_remote_get($url, $request_options);
  if (is_wp_error($remote_response)) {
    wsmp_remove_temporary_file($temporary_path);
    return new WP_Error(
      "wsmp_remote_request_failed",
      "Failed to fetch remote file",
      ["status" => 502],
    );
  }

  $response_code = wp_remote_retrieve_response_code($remote_response);
  if ($response_code !== 200) {
    wsmp_remove_temporary_file($temporary_path);
    return new WP_Error(
      "wsmp_remote_response_failed",
      "Failed to fetch remote file: HTTP " . $response_code,
      ["status" => $response_code === 404 ? 404 : 502],
    );
  }

  clearstatcache(true, $temporary_path);
  if (!is_file($temporary_path) || filesize($temporary_path) === 0) {
    wsmp_remove_temporary_file($temporary_path);
    return new WP_Error(
      "wsmp_remote_response_failed",
      "Failed to fetch remote file: empty response",
      ["status" => 502],
    );
  }

  if (
    !@chmod($temporary_path, defined("FS_CHMOD_FILE") ? FS_CHMOD_FILE : 0644)
  ) {
    wsmp_remove_temporary_file($temporary_path);
    return new WP_Error(
      "wsmp_local_write_failed",
      "Failed to set local media file permissions",
      ["status" => 500],
    );
  }
  if (!@rename($temporary_path, $local_path)) {
    wsmp_remove_temporary_file($temporary_path);
    return new WP_Error(
      "wsmp_local_publish_failed",
      "Failed to publish local media file",
      ["status" => 500],
    );
  }

  return ["local_path" => $local_path, "remote_url" => $url];
}

function wsmp_remove_temporary_file($path) {
  if (is_string($path) && is_file($path)) {
    @unlink($path);
  }
}

function wsmp_get_local_media_url($path) {
  $upload_path = wsmp_parse_upload_path($path);
  return $upload_path === null ? null : home_url($upload_path["path"]);
}

function wsmp_fetch_response($path, $ext, $result, $request, $server) {
  $fetched_file = wsmp_fetch_remote_file($path);
  if (is_wp_error($fetched_file)) {
    error_log($fetched_file->get_error_message());
    $error_data = $fetched_file->get_error_data();
    wp_die(
      $fetched_file->get_error_message(),
      "",
      is_array($error_data) && isset($error_data["status"])
        ? $error_data["status"]
        : 500,
    );
  }

  error_log("Fetched remote file: " . $fetched_file["remote_url"]);

  $local_url = wsmp_get_local_media_url($path);
  if ($local_url === null) {
    wp_die("Invalid uploads path", "", 400);
  }

  // The file is now atomically available on the normal static uploads path.
  // Redirecting there avoids buffering large PHP response bodies in the web
  // server and makes this request use the same cache and range behavior as all
  // subsequent requests.
  wp_safe_redirect($local_url, 302, "Whitespace Media Proxy");
  exit();
}

function wsmp_pipe_response($path, $ext, $result, $request, $server) {
  $remote_path = wsmp_rewrite_remote_path($path);
  if ($remote_path === null) {
    wp_die("Invalid uploads path", "", 400);
  }
  $url = wsmp_get_remote() . $remote_path;
  header("Content-Type: " . mime_content_type($url));
  header("Content-Length: " . strlen($url));
  readfile($url);
  exit();
}

function wsmp_redirect_response($path, $ext, $result, $request, $server) {
  $remote_path = wsmp_rewrite_remote_path($path);
  if ($remote_path === null) {
    wp_die("Invalid uploads path", "", 400);
  }
  $url = wsmp_get_remote() . $remote_path;
  wp_redirect($url, 302, "Whitespace Media Proxy");
  exit();
}
