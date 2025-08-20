<?php

// Admin settings page for Whitespace Media Proxy

// Add admin menu
add_action("admin_menu", "wsmp_add_admin_menu");

function wsmp_add_admin_menu() {
  add_options_page(
    __("Whitespace Media Proxy Settings", "whitespace-media-proxy"),
    __("Media Proxy", "whitespace-media-proxy"),
    "manage_options",
    "whitespace-media-proxy",
    "wsmp_settings_page",
  );
}

// Register settings
add_action("admin_init", "wsmp_settings_init");

function wsmp_settings_init() {
  register_setting("wsmp_settings", "wsmp_remote_url", [
    "type" => "string",
    "sanitize_callback" => "wsmp_sanitize_url",
    "default" => "",
  ]);

  add_settings_section(
    "wsmp_settings_section",
    __("Media Proxy Configuration", "whitespace-media-proxy"),
    "wsmp_settings_section_callback",
    "wsmp_settings",
  );

  add_settings_field(
    "wsmp_remote_url",
    __("Remote URL", "whitespace-media-proxy"),
    "wsmp_remote_url_render",
    "wsmp_settings",
    "wsmp_settings_section",
  );
}

function wsmp_sanitize_url($url) {
  // If constant is defined, don't allow changes via the form
  if (defined("WSMP_REMOTE_URL") && constant("WSMP_REMOTE_URL")) {
    return get_option("wsmp_remote_url", "");
  }

  if (empty($url)) {
    return "";
  }

  // Remove trailing slash and validate URL
  $url = rtrim(esc_url_raw($url), "/");

  // Basic validation
  if (!filter_var($url, FILTER_VALIDATE_URL)) {
    add_settings_error(
      "wsmp_remote_url",
      "invalid_url",
      __("Please enter a valid URL.", "whitespace-media-proxy"),
    );
    return get_option("wsmp_remote_url", "");
  }

  return $url;
}

function wsmp_settings_section_callback() {
  echo "<p>" .
    __(
      "Configure the remote URL that will be used to proxy media files when they are not available locally. This is typically your production site URL.",
      "whitespace-media-proxy",
    ) .
    "</p>";
  echo "<p>" .
    __(
      "The plugin will attempt to fetch missing media files from this remote location using different strategies based on file type.",
      "whitespace-media-proxy",
    ) .
    "</p>";

  if (defined("WSMP_REMOTE_URL") && constant("WSMP_REMOTE_URL")) {
    echo "<p><strong>" .
      __(
        "Note: The remote URL is currently set via the WSMP_REMOTE_URL constant in your configuration.",
        "whitespace-media-proxy",
      ) .
      "</strong></p>";
  }
}

function wsmp_remote_url_render() {
  $is_constant_defined =
    defined("WSMP_REMOTE_URL") && constant("WSMP_REMOTE_URL");
  $remote_url = $is_constant_defined
    ? constant("WSMP_REMOTE_URL")
    : get_option("wsmp_remote_url", "");
  ?>
    <input type="url" 
           name="wsmp_remote_url" 
           value="<?php echo esc_attr($remote_url); ?>" 
           class="regular-text"
           placeholder="https://example.com"
           <?php echo $is_constant_defined ? "disabled" : ""; ?> />
    <?php if ($is_constant_defined): ?>
    <p class="description">
        <?php _e(
          "The remote URL is set via the WSMP_REMOTE_URL constant and cannot be changed here.",
          "whitespace-media-proxy",
        ); ?>
    </p>
    <?php else: ?>
    <p class="description">
        <?php _e(
          "Enter the URL of the remote site to proxy media files from. Do not include a trailing slash.",
          "whitespace-media-proxy",
        ); ?>
    </p>
    <?php endif; ?>
    <?php
}

function wsmp_settings_page() {
  ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php
        // Show settings errors
        settings_errors();

        // Show info notice if no URL is set
        $remote_url = get_option("wsmp_remote_url", "");
        if (empty($remote_url)) {
          echo '<div class="notice notice-info"><p>';
          _e(
            "Please set a remote URL below to enable media proxying functionality.",
            "whitespace-media-proxy",
          );
          echo "</p></div>";
        }
        ?>
        
        <form action="options.php" method="post">
            <?php
            settings_fields("wsmp_settings");
            do_settings_sections("wsmp_settings");
            submit_button(__("Save Settings", "whitespace-media-proxy"));
            ?>
        </form>
        
        <?php if (!empty($remote_url)): ?>
        <h2><?php _e("Plugin Information", "whitespace-media-proxy"); ?></h2>
        
        <h3><?php _e("Endpoint URL", "whitespace-media-proxy"); ?></h3>
        <p><?php _e(
          "The media proxy is available at:",
          "whitespace-media-proxy",
        ); ?></p>
        <p><code><?php echo esc_html(
          home_url("/wp-json/wsmp/v1/media-file?path=/app/uploads/[file-path]"),
        ); ?></code></p>
        <p><em><?php _e(
          "Replace [file-path] with the actual path to the media file you want to proxy.",
          "whitespace-media-proxy",
        ); ?></em></p>
        
        <h3><?php _e("Proxy Strategies", "whitespace-media-proxy"); ?></h3>
        <p><?php _e(
          "The plugin uses different strategies for different file types:",
          "whitespace-media-proxy",
        ); ?></p>
        <ul>
            <?php
            $strategies = wsmp_get_extension_strategies();
            $strategy_descriptions = [
              "fetch" => __(
                "Downloads the file locally and serves it",
                "whitespace-media-proxy",
              ),
              "pipe" => __(
                "Streams the file directly from remote",
                "whitespace-media-proxy",
              ),
              "redirect" => __(
                "Redirects to the remote file URL",
                "whitespace-media-proxy",
              ),
            ];

            foreach ($strategies as $ext => $strategy): ?>
            <li><strong>.<?php echo esc_html(
              $ext,
            ); ?>:</strong> <?php echo esc_html(
  $strategy,
); ?> - <?php echo esc_html($strategy_descriptions[$strategy] ?? ""); ?></li>
            <?php endforeach;
            ?>
            <li><strong><?php _e(
              "Other files",
              "whitespace-media-proxy",
            ); ?>:</strong> <?php echo esc_html(
  wsmp_get_default_strategy(),
); ?> - <?php echo esc_html(
   $strategy_descriptions[wsmp_get_default_strategy()] ?? "",
 ); ?></li>
        </ul>
        <?php endif; ?>
    </div>
    <?php
}

// Add settings link to plugin actions
add_filter(
  "plugin_action_links_" . plugin_basename(WHITESPACE_MEDIA_PROXY_PLUGIN_FILE),
  "wsmp_add_settings_link",
);

function wsmp_add_settings_link($links) {
  $settings_link =
    '<a href="' .
    admin_url("options-general.php?page=whitespace-media-proxy") .
    '">' .
    __("Settings", "whitespace-media-proxy") .
    "</a>";
  array_unshift($links, $settings_link);
  return $links;
}
