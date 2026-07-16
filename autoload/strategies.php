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

  $url = wsmp_get_remote() . $remote_path;
  $remote_response = wp_safe_remote_get($url, [
    "timeout" => 30,
  ]);
  if (is_wp_error($remote_response)) {
    return new WP_Error(
      "wsmp_remote_request_failed",
      "Failed to fetch remote file: " . $remote_response->get_error_message(),
      ["status" => 502],
    );
  }

  $response_code = wp_remote_retrieve_response_code($remote_response);
  $response_body = wp_remote_retrieve_body($remote_response);
  if ($response_code !== 200 || $response_body === "") {
    return new WP_Error(
      "wsmp_remote_response_failed",
      "Failed to fetch remote file: HTTP " . $response_code,
      ["status" => $response_code === 404 ? 404 : 502],
    );
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
  if (
    $temporary_path === false ||
    file_put_contents($temporary_path, $response_body, LOCK_EX) === false
  ) {
    if (is_string($temporary_path) && file_exists($temporary_path)) {
      unlink($temporary_path);
    }
    return new WP_Error(
      "wsmp_local_write_failed",
      "Failed to write local media file",
      ["status" => 500],
    );
  }

  chmod($temporary_path, defined("FS_CHMOD_FILE") ? FS_CHMOD_FILE : 0644);
  if (!rename($temporary_path, $local_path)) {
    unlink($temporary_path);
    return new WP_Error(
      "wsmp_local_publish_failed",
      "Failed to publish local media file",
      ["status" => 500],
    );
  }

  return ["local_path" => $local_path, "remote_url" => $url];
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

  $local_path = $fetched_file["local_path"];
  error_log("Fetched remote file: " . $fetched_file["remote_url"]);

  // Serve the file and exit
  header("Content-Type: " . mime_content_type($local_path));
  header("Content-Length: " . filesize($local_path));
  readfile($local_path);
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
