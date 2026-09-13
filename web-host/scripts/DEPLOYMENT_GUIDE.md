# BroxLab Auto Deploy Guide

This repository uses a safe GitHub-based deployment flow for production.

## Deploy flow

1. Push to `main` triggers `.github/workflows/deploy.yml`.
2. The GitHub Action checks out the repo and prepares SSH credentials.
3. The action SSHes into production and runs `scripts/deploy.sh`.
4. The workflow file is also copied to `~/.github/workflows/deploy.yml` on the remote host.
5. The deploy script clones the requested `REF` into `app/releases/<timestamp>`.
6. Shared configuration and storage are symlinked into the release.
6. The release becomes active via `app/current` and `public_html`.
7. Laravel caches are refreshed with the new release; Node builds run only when Node/npm are available.
8. Old releases are pruned and a failed deploy does not replace the active release.

## Rollback

Use `scripts/rollback.sh` to revert to the previous release on the server.

## Remote deploy helper

Use `scripts/deploy.sh --base /home/tdhuedhn/broxlab --repo git@github.com:habibbrox2/broxlab-repo.git --ref main` on the server, or trigger `.github/workflows/deploy.yml` from GitHub Actions, to deploy a new release and activate it.
