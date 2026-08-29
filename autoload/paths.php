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

  $relative_path = $upload_path["relative_path"];
  // Multisite base directories already include `/sites/<blog-id>`. Resolve
  // relative to the site's public uploads URL to avoid duplicating that suffix.
  $base_url_path = !empty($upload_dir["baseurl"])
    ? parse_url($upload_dir["baseurl"], PHP_URL_PATH)
    : null;
  if (
    is_string($base_url_path) &&
    str_starts_with($upload_path["path"], rtrim($base_url_path, "/") . "/")
  ) {
    $relative_path = substr(
      $upload_path["path"],
      strlen(rtrim($base_url_path, "/")) + 1,
    );
  }

  return rtrim($upload_dir["basedir"], "/\\") . "/" . $relative_path;
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
  return $remote_uploads_path . $upload_path["relative_path"];
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

/**
 * Returns the canonical HTTPS origin used for credential lookup.
 *
 * Credentials are deliberately matched on scheme, host, and effective port;
 * a path-prefix or parent-domain match could otherwise disclose them to a
 * different endpoint.
 */
function wsmp_get_https_origin($url) {
  $parts = parse_url($url);
  if (
    !is_array($parts) ||
    strtolower($parts["scheme"] ?? "") !== "https" ||
    empty($parts["host"]) ||
    isset($parts["user"]) ||
    isset($parts["pass"])
  ) {
    return null;
  }

  $origin = "https://" . strtolower($parts["host"]);
  if (isset($parts["port"]) && (int) $parts["port"] !== 443) {
    $origin .= ":" . (int) $parts["port"];
  }

  return $origin;
}

/**
 * Resolves Basic Auth only from the process-level secret map.
 *
 * The map is intentionally unavailable through WordPress options and admin UI
 * so credentials cannot enter database exports or rendered settings pages.
 */
function wsmp_get_remote_basic_auth($remote_url) {
  if (!defined("WSMP_REMOTE_BASIC_AUTH")) {
    return null;
  }

  $credentials_by_origin = constant("WSMP_REMOTE_BASIC_AUTH");
  $origin = wsmp_get_https_origin($remote_url);
  if (!is_array($credentials_by_origin) || $origin === null) {
    return null;
  }

  foreach ($credentials_by_origin as $configured_origin => $credentials) {
    if (
      !is_string($configured_origin) ||
      wsmp_get_https_origin($configured_origin) !== $origin ||
      rtrim($configured_origin, "/") !== $origin ||
      !is_array($credentials) ||
      !is_string($credentials["username"] ?? null) ||
      !is_string($credentials["password"] ?? null) ||
      $credentials["username"] === "" ||
      $credentials["password"] === ""
    ) {
      continue;
    }

    return [
      "username" => $credentials["username"],
      "password" => $credentials["password"],
    ];
  }

  return null;
}
