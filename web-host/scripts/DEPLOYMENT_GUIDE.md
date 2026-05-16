# BroxLab Auto Deploy Guide

This repository uses a safe GitHub-based deployment flow for production.

## Deploy flow

1. Push to `main` triggers `.github/workflows/deploy.yml`.
2. The GitHub Action checks out the repo and prepares SSH credentials.
3. The action SSHes into production and runs `web-host/scripts/deploy.sh`.
4. The deploy script clones the repo into `app/releases/<timestamp>`.
5. Shared configuration and storage are symlinked into the release.
6. The release becomes active via `app/current` and `public_html`.
7. The Node server restarts with the new release, while prior releases remain available.
8. Old releases are pruned and a failed deploy does not replace the active release.

## Rollback

Use `web-host/scripts/rollback.sh` to revert to the previous release on the server.

## Remote deploy helper

Use `web-host/scripts/deploy.sh` on the server, or trigger `.github/workflows/deploy.yml` from GitHub Actions, to deploy a new release and activate it.
