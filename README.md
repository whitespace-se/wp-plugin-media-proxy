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
2. Install the package and its dependencies:
   ```bash
   composer require whitespace-se/wp-plugin-media-proxy:dev-main
   ```
3. Define the constant `WSMP_REMOTE_URL` in your `wp-config.php` file:
   ```php
   define('WSMP_REMOTE_URL', 'https://your-remote-url.com');
   ```
4. Configure your HTTP server to redirect missing files in `/app/uploads` to
   `/wp-json/wsmp/v1/media-file?path=<file_path>`. The `<file_path>` must
   correspond to the full path to the file, including `/app/uploads`, e.g.,
   `/app/uploads/my-image.jpg`.

## Strategies

There are three proxy strategies:

1. **Fetch**: Downloads the file from the remote server and serves it locally.
   Default for image files.
2. **Pipe**: Streams the file from the remote server to the client without
   saving it locally. Not used by default.
3. **Redirect**: Redirects the client to the remote server to fetch the file
   directly. Default for other files.
