# BroxLab Auto Deploy Guide

This repository uses a safe GitHub-based deployment flow for production.

## Deploy flow

1. Push to `main` triggers `.github/workflows/deploy.yml`.
2. The GitHub Action checks out the repo and prepares SSH credentials.
3. The action uses `rsync` to upload only changed files to a staging path on the server.
4. The action SSHes into production and runs `web-host/scripts/deploy.sh --source-dir <staging-path>`.
5. The deploy script imports the staged source into a new `app/releases/<timestamp>` release.
6. Shared configuration and storage are symlinked into the release.
7. The release becomes active via `app/current` and `public_html`.
7. The Node server restarts with the new release, while prior releases remain available.
8. Old releases are pruned and a failed deploy does not replace the active release.

## Rollback

Use `scripts/rollback.sh` to revert to the previous release on the server.

## Remote deploy helper

`deploy.sh` now supports deploying from a pre-synced source directory on the server.

Use `web-host/scripts/deploy.sh --source-dir /home/<user>/broxlab/build_source/<timestamp>` to import a staged source tree instead of cloning from Git.

Use `scripts/deploy.sh` on the server, or trigger `.github/workflows/deploy.yml` from GitHub Actions, to deploy a new release and activate it.
