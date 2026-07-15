<?php

// /wp-json/wsmp/v1/media-file

// 1) Route
add_action("rest_api_init", function () {
  register_rest_route("wsmp/v1", "/media-file", [
    "methods" => "GET",
    "callback" => "wsmp_endpoint",
    "permission_callback" => "__return_true",
  ]);
});

function wsmp_get_default_strategy() {
  return "redirect";
}

function wsmp_get_extension_strategies() {
  return [
    "png" => "fetch",
    "jpg" => "fetch",
    "jpeg" => "fetch",
    "svg" => "fetch",
    "woff" => "fetch",
    "woff2" => "fetch",
    "ttf" => "fetch",
    "otf" => "fetch",
    "pdf" => "redirect",
    "docx" => "redirect",
    "mp4" => "redirect",
  ];
}

function wsmp_get_strategy_for_extension($ext) {
  $strategies = wsmp_get_extension_strategies();
  return $strategies[$ext] ?? wsmp_get_default_strategy();
}

function wsmp_endpoint(WP_REST_Request $req) {
  if (!wsmp_get_remote()) {
    return new WP_REST_Response(
      "Remote URL for Whitespace Media Proxy is not defined",
      400,
    );
  }
  return new WP_REST_Response("", 200);
}

function wsmp_get_remote() {
  $remote =
    defined("WSMP_REMOTE_URL") && constant("WSMP_REMOTE_URL")
      ? constant("WSMP_REMOTE_URL")
      : get_option("wsmp_remote_url");
  return $remote ? rtrim($remote, "/") : null;
}

function wsmp_rewrite_remote_path($path) {
  $remote_uploads_path =
    defined("WSMP_REMOTE_UPLOADS_PATH") && constant("WSMP_REMOTE_UPLOADS_PATH")
      ? constant("WSMP_REMOTE_UPLOADS_PATH")
      : get_option("wsmp_remote_uploads_path");
  if (empty($remote_uploads_path)) {
    $remote_uploads_path = "/app/uploads";
  }
  $remote_uploads_path = "/" . trim($remote_uploads_path, "/") . "/";
  return preg_replace("#/app/uploads/#", $remote_uploads_path, $path);
}

add_filter(
  "rest_pre_serve_request",
  function ($served, $result, $request, $server) {
    $route = $request->get_route();
    if (
      strpos($route, "/wsmp/v1/media-file") !== false &&
      $result->get_status() === 200
    ) {
      $path = $request->get_param("path");

      if (
        strpos($path, "/app/uploads/") !== 0 &&
        strpos($path, "/wp-content/uploads/") !== 0
      ) {
        wp_die(
          "This endpoint only serves files from the /app/uploads/ or /wp-content/uploads/ directories",
          "",
          400,
        );
      }

      $ext = pathinfo($path, PATHINFO_EXTENSION);

      $strategy = wsmp_get_strategy_for_extension($ext);

      switch ($strategy) {
        case "fetch":
          return wsmp_fetch_response($path, $ext, $result, $request, $server);
        case "pipe":
          return wsmp_pipe_response($path, $ext, $result, $request, $server);
        case "redirect":
          return wsmp_redirect_response(
            $path,
            $ext,
            $result,
            $request,
            $server,
          );
      }
      wp_die("No proxy strategy defined for file type: " . $ext, "", 400);
    }
    return $served;
  },
  10,
  4,
);
