<?php

/**
 * Plugin Name: Whitespace Media Proxy
 * Description: Proxies media files through the production site on demand.
 * Version: 1.0.0
 * Author: Whitespace Dev
 * Text Domain: whitespace-media-proxy
 * Domain Path: /languages/
 */

define("WHITESPACE_MEDIA_PROXY_PLUGIN_FILE", __FILE__);
define("WHITESPACE_MEDIA_PROXY_PATH", dirname(__FILE__));
define("WHITESPACE_MEDIA_PROXY_URL", rtrim(plugin_dir_url(__FILE__), "/"));
define(
  "WHITESPACE_MEDIA_PROXY_AUTOLOAD_PATH",
  WHITESPACE_MEDIA_PROXY_PATH . "/autoload",
);
define(
  "WHITESPACE_MEDIA_PROXY_LANGUAGES_PATH",
  plugin_basename(dirname(__FILE__)) . "/languages",
);

load_plugin_textdomain(
  "whitespace-media-proxy",
  false,
  WHITESPACE_MEDIA_PROXY_LANGUAGES_PATH,
);

load_muplugin_textdomain(
  "whitespace-media-proxy",
  WHITESPACE_MEDIA_PROXY_LANGUAGES_PATH,
);

array_map(static function () {
  include_once func_get_args()[0];
}, glob(WHITESPACE_MEDIA_PROXY_AUTOLOAD_PATH . "/*.php"));
