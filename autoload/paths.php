<?php

/**
 * Returns a validated uploads request path and its path relative to uploads.
 *
 * The endpoint is public, so accepting a prefix alone is insufficient: encoded
 * dot segments could otherwise escape the shared uploads directory or make the
 * remote server return a file outside its uploads tree.
 */
function wsmp_parse_upload_path($path) {
  if (!is_string($path) || str_contains($path, "\0")) {
    return null;
  }

  $prefix = null;
  foreach (["/app/uploads/", "/wp-content/uploads/"] as $candidate) {
    if (str_starts_with($path, $candidate)) {
      $prefix = $candidate;
      break;
    }
  }

  if ($prefix === null) {
    return null;
  }

  $relative_path = substr($path, strlen($prefix));
  $segments = explode("/", $relative_path);
  if ($relative_path === "" || in_array("", $segments, true)) {
    return null;
  }

  foreach ($segments as $segment) {
    $decoded_segment = $segment;
    for ($iteration = 0; $iteration <= strlen($segment); $iteration++) {
      $next_segment = rawurldecode($decoded_segment);
      if ($next_segment === $decoded_segment) {
        break;
      }
      $decoded_segment = $next_segment;
    }

    if (
      $decoded_segment === "." ||
      $decoded_segment === ".." ||
      str_contains($decoded_segment, "/") ||
      str_contains($decoded_segment, "\\") ||
      str_contains($decoded_segment, "\0")
    ) {
      return null;
    }
  }

  return [
    "path" => $prefix . $relative_path,
    "relative_path" => $relative_path,
  ];
}

/**
 * Resolves a validated public uploads path below WordPress' uploads directory.
 *
 * Municipio Cloud exposes that directory through current/wp-content/uploads,
 * which is a symlink to shared/uploads. Using WordPress' configured base path
 * keeps writes inside that shared runtime state instead of depending on PHP's
 * current working directory.
 */
function wsmp_get_local_upload_path($path) {
  $upload_path = wsmp_parse_upload_path($path);
  if ($upload_path === null) {
    return null;
  }

  $upload_dir = wp_get_upload_dir();
  if (!empty($upload_dir["error"]) || empty($upload_dir["basedir"])) {
    return null;
  }

  return rtrim($upload_dir["basedir"], "/\\") .
    "/" .
    $upload_path["relative_path"];
}

function wsmp_rewrite_remote_path($path) {
  $upload_path = wsmp_parse_upload_path($path);
  if ($upload_path === null) {
    return null;
  }

  $remote_uploads_path =
    defined("WSMP_REMOTE_UPLOADS_PATH") && constant("WSMP_REMOTE_UPLOADS_PATH")
      ? constant("WSMP_REMOTE_UPLOADS_PATH")
      : get_option("wsmp_remote_uploads_path");
  if (empty($remote_uploads_path)) {
    $remote_uploads_path = "/app/uploads";
  }

  $remote_uploads_path = "/" . trim($remote_uploads_path, "/") . "/";
  if (str_starts_with($upload_path["path"], "/app/uploads/")) {
    return $remote_uploads_path . $upload_path["relative_path"];
  }

  return $upload_path["path"];
}

function wsmp_urls_share_origin($first_url, $second_url) {
  $first = parse_url($first_url);
  $second = parse_url($second_url);
  if (
    !is_array($first) ||
    !is_array($second) ||
    empty($first["scheme"]) ||
    empty($first["host"]) ||
    empty($second["scheme"]) ||
    empty($second["host"])
  ) {
    return false;
  }

  $default_port = static function ($url) {
    if (isset($url["port"])) {
      return (int) $url["port"];
    }
    return strtolower($url["scheme"]) === "https" ? 443 : 80;
  };

  return strtolower($first["scheme"]) === strtolower($second["scheme"]) &&
    strtolower($first["host"]) === strtolower($second["host"]) &&
    $default_port($first) === $default_port($second);
}
