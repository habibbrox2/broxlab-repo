# GitHub Actions Deploy Secrets Example

This file lists the expected secrets for the remote deploy workflow.

- `HOST`
  - Remote server host or IP address.
- `USER`
  - SSH username for the remote server.
- `SSH_KEY_BASE64`
  - Base64-encoded SSH private key used to log in as `USER`.
- `REMOTE_BASE`
  - Remote deployment base path, e.g. `/home/deploy/broxlab`.
  - If not set, the workflow defaults to `/home/$USER/broxlab`.
- `SSH_PORT`
  - SSH port on the remote server.
  - If not set, the workflow defaults to `22`.
- `KEEP_RELEASES`
  - Number of releases to keep on the remote server.
  - Default is `3` if not provided.

Optional environment values for deploy behavior:
- `NODE_ENV`
  - Typically `production`.
- `KEEP_RELEASES`
  - Controls cleanup of old releases on the remote server.
