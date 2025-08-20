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
  // Download the file from the remote and put it in the same place locally
  $context = stream_context_create([
    "http" => [
      "timeout" => 30,
      "ignore_errors" => true,
    ],
  ]);
  $content = file_get_contents($url, false, $context);
  if ($content === false) {
    $error = error_get_last();
    $error_message = $error ? $error["message"] : "Unknown error";
    error_log("Failed to fetch remote file: $url - Error: $error_message");
    wp_die(
      "Failed to fetch remote file: " . $url . " - Error: " . $error_message,
      "",
      500,
    );
  }

  // Check if we got an HTTP error response
  if (isset($http_response_header)) {
    $status_line = $http_response_header[0];
    if (
      strpos($status_line, "200") === false &&
      strpos($status_line, "304") === false
    ) {
      error_log(
        "HTTP error fetching remote file: $url - Response: $status_line",
      );
      wp_die(
        "HTTP error fetching remote file: " .
          $url .
          " - Response: " .
          $status_line,
        "",
        500,
      );
    }
  }
  file_put_contents($local_path, $content);

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
