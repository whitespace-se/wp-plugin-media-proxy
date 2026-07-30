# Changelog

All notable changes to Whitespace Media Proxy are documented in this file.

## 1.0.0 - 2026-07-30

- Validate and normalize requests below both `/app/uploads/` and
  `/wp-content/uploads/`.
- Map the local uploads prefix independently from the configured remote uploads
  root.
- Reject path traversal, repeated separators, encoded separators and recursively
  encoded traversal attempts.
- Protect against proxy loops by requiring a remote origin distinct from the
  current WordPress site.
- Resolve local destinations through WordPress' uploads configuration, including
  multisite paths that already contain `sites/<blog-id>`.
- Preserve Municipio Cloud's shared uploads storage and group-writable directory
  contract.
- Publish fetched files atomically with WordPress-compatible file permissions.
- Redirect successful fetches to the newly available static upload URL so
  OpenLiteSpeed serves the response without buffering the file through PHP.
