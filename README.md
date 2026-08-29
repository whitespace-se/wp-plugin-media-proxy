# Whitespace Media Proxy

A WordPress plugin to proxy media files through the production site on demand.

## Installation

1. Add the following to your `composer.json` file:
   ```json
   {
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/whitespace-se/wp-plugin-media-proxy.git",
         "only": ["whitespace-se/wp-plugin-media-proxy"],
         "no-api": true
       }
     ]
   }
   ```
2. Install the stable package:
   ```bash
   composer require whitespace-se/wp-plugin-media-proxy:^1.0
   ```
3. For a single-origin installation, define `WSMP_REMOTE_URL` in the
   environment-specific WordPress configuration:
   ```php
   define('WSMP_REMOTE_URL', 'https://your-remote-url.com');
   define('WSMP_REMOTE_UPLOADS_PATH', '/app/uploads');
   ```
   The value must be a different origin from the current site to prevent
   redirect loops. `WSMP_REMOTE_UPLOADS_PATH` is the public uploads path at the
   remote origin and defaults to `/app/uploads`. These constants apply to the
   whole WordPress process.
4. If the source requires HTTP Basic Auth, define an exact HTTPS-origin map in
   environment-specific configuration:
   ```php
   define('WSMP_REMOTE_BASIC_AUTH', [
     'https://production.example.com' => [
       'username' => getenv('WSMP_REMOTE_USERNAME'),
       'password' => getenv('WSMP_REMOTE_PASSWORD'),
     ],
   ]);
   ```
   Credentials are sent only to an exact matching origin. Authenticated sources
   never redirect the browser to the remote file, and remote HTTP redirects are
   rejected without a follow-up request.

Rotate credentials in the environment-specific secret source and regenerate the
runtime configuration. Remove an origin from `WSMP_REMOTE_BASIC_AUTH` to return
it to public-source behavior; never keep empty placeholder credentials. Remote
`401`, `403`, redirects, empty responses, and transport failures return a
controlled gateway error without publishing a partial local file. Confirm that
the configured origin is the source's final canonical origin before debugging
credentials, because authenticated requests intentionally do not follow
redirects. 5. Configure your HTTP server or local Valet driver to rewrite
missing uploads to `/wp-json/wsmp/v1/media-file?path=<file_path>`. The
`<file_path>` must preserve the local request path, including its uploads
prefix. The plugin combines the validated relative file path with
`WSMP_REMOTE_UPLOADS_PATH`.

### Recommended config for Nginx

```
# App uploads location with media proxy fallback
location ~ ^/app/uploads/ {
  try_files $uri @media_proxy;
}

# Media proxy fallback location
location @media_proxy {
  rewrite ^(.*)$ /wp-json/wsmp/v1/media-file?path=$1 redirect;
}
```

### Recommended config for OpenLiteSpeed

Put the rule in a dedicated static `/wp-content/uploads/` context. The `!-f`
condition lets existing files stay on the static fast path, while the endpoint
validates a captured path before using it for a remote or local file.

```apacheconf
context /wp-content/uploads/ {
  allowBrowse 1
  location $DOC_ROOT/wp-content/uploads/

  rewrite {
    enable 1
    inherit 0
    rules <<<END_rules
RewriteCond /srv/www/<site-id>/current%{REQUEST_URI} !-f
RewriteCond %{REQUEST_URI} ^/wp-content/uploads/(.+)$
RewriteRule ^ /index.php?rest_route=/wsmp/v1/media-file&path=/wp-content/uploads/%1 [R=307,L]
END_rules
  }
}
```

The local `307` is intentional: it preserves the `GET` request while moving a
missing static file into WordPress' REST routing. Existing files never take that
redirect and remain on OpenLiteSpeed's static path.

The REST endpoint only accepts paths below `/app/uploads/` or
`/wp-content/uploads/`. Fetch responses resolve the destination through
WordPress' configured uploads directory, so installations that symlink uploads
to shared runtime storage keep that storage contract.

The first request for an image or font normally follows `307 → 302 → 200`:
OpenLiteSpeed redirects the missing static request into WordPress, the plugin
atomically publishes the fetched file and redirects to its local upload URL, and
the web server returns the new static file. Later requests use the static path
directly and return `200` without running the plugin.

In multisite networks where blogs use different source sites, leave the
`WSMP_REMOTE_URL` and `WSMP_REMOTE_UPLOADS_PATH` constants undefined and store
the `wsmp_remote_url` and `wsmp_remote_uploads_path` options on each blog
instead. WordPress' per-blog uploads configuration determines the local
destination, including `sites/<blog-id>` where applicable.

Media Proxy is a migration-readiness and fallback tool. It does not replace a
controlled transfer of the complete media library before a stage or production
cutover.

## Compatibility

Version `1.1.0` is verified in Municipio Cloud reference environments using PHP
8.3 and WordPress 6.x. The package requires PHP 8.0 or newer. No broader
WordPress compatibility range is claimed without corresponding test evidence.

## Strategies

There are three proxy strategies:

1. **Fetch**: Downloads the file from the remote server and serves it locally.
   Default for image files.
2. **Pipe**: Streams the file from the remote server to the client without
   saving it locally. Not used by default.
3. **Redirect**: Redirects the client to the remote server to fetch the file
   directly. Default for other files.
