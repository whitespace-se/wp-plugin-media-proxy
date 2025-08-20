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
  file_put_contents($local_path, file_get_contents($url));

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
