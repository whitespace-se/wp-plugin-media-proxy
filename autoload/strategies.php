<?php

function wsmp_fetch_response($path, $ext, $result, $request, $server) {
  $url = wsmp_get_remote() . $path;
  // Get the local uploads directory. (Should be /app/uploads)
  $local_path = ltrim($path, "/");
  // Make sure all the folders exist before downloading (mkdir -p)
  $folder = dirname($local_path);
  if (!file_exists($folder)) {
    mkdir($folder, 0755, true);
  }

  $remote_response = wp_remote_get($url, [
    "timeout" => 30,
    "stream" => true,
    "filename" => $local_path,
    "ignore_errors" => true,
  ]);
  if (is_wp_error($remote_response)) {
    error_log(
      "Error fetching remote file: " . $remote_response->get_error_message(),
    );
    wp_die(
      "Failed to fetch remote file: " . $remote_response->get_error_message(),
      "",
      500,
    );
  }
  if ($remote_response["response"]["code"] !== 200) {
    error_log(
      "Error fetching remote file: HTTP " .
        $remote_response["response"]["code"],
    );
    wp_die(
      "Failed to fetch remote file: HTTP " .
        $remote_response["response"]["code"],
      "",
      500,
    );
  }
  file_put_contents($local_path, $remote_response["body"]);

  error_log("Fetched remote file: $url");

  // Serve the file and exit
  header("Content-Type: " . mime_content_type($local_path));
  header("Content-Length: " . filesize($local_path));
  readfile($local_path);
  exit();
}

function wsmp_pipe_response($path, $ext, $result, $request, $server) {
  $url = wsmp_get_remote() . $path;
  header("Content-Type: " . mime_content_type($url));
  header("Content-Length: " . strlen($url));
  readfile($url);
  exit();
}

function wsmp_redirect_response($path, $ext, $result, $request, $server) {
  status_header(302);
  $url = wsmp_get_remote() . $path;
  header("Location: " . $url);
  exit();
}
