<?php

/**
 * Deploy Configuration
 * Location: /home/tdhuedhn/broxlab/webhook/deploy_config.php
 *
 * ⚠️  IMPORTANT:
 *  - This file must be in your .gitignore (contains secrets)
 *  - After every  git reset --hard  deploy.php auto-restores this file
 *  - Never hardcode these values anywhere else
 */

return [

    // ──────────────────────────────────────────────────────────────────────────
    // Security
    // Must EXACTLY match the secret set in:
    // GitHub → Repo → Settings → Webhooks → Edit → Secret
    // No leading/trailing spaces or newlines — they break HMAC silently.
    // ──────────────────────────────────────────────────────────────────────────
    'secret' => 'qqTE_YO9974wiuweTH4463EBHOOK44HERE',

    // Branch that triggers deploy (must match GitHub webhook push target)
    'branch' => 'main',

    // ──────────────────────────────────────────────────────────────────────────
    // Paths
    // All directories must be writable by the web-server user.
    // ──────────────────────────────────────────────────────────────────────────

    // Root of your git repository (where .git folder lives)
    'project_dir'   => '/home/tdhuedhn/broxlab',

    // Where timestamped backups are stored before each deploy
    'backup_dir'    => '/home/tdhuedhn/deploys/backups',

    // JSON file tracking version history and applied migrations
    'version_file'  => '/home/tdhuedhn/deploys/version.json',

    // Plain-text deploy log (appended on every deploy)
    'log_file'      => '/home/tdhuedhn/deploys/deploy.log',

    // Folder containing *.sql migration files (set to '' to disable)
    'migration_dir' => '/home/tdhuedhn/broxlab/migrations',

    // ──────────────────────────────────────────────────────────────────────────
    // Database
    // Used for mysqldump backup before deploy and PDO migration runner.
    // ──────────────────────────────────────────────────────────────────────────
    'db_host' => 'localhost',
    'db_name' => 'tdhuedhn_broxbhai',
    'db_user' => 'tdhuedhn_broxbhai',
    'db_pass' => ',EnTio1PtqI-&M&D',

    // ──────────────────────────────────────────────────────────────────────────
    // Behaviour
    // ──────────────────────────────────────────────────────────────────────────

    // How many backup folders to keep (oldest deleted automatically)
    'keep_backups' => 5,

    // ──────────────────────────────────────────────────────────────────────────
    // Debug  (set true → writes sig-debug.log next to deploy.log)
    //
    // When true, every webhook request logs:
    //   • Received vs computed signature  (and whether they match)
    //   • Secret length                   (catches accidental empty secret)
    //   • Raw body length + hex dump      (catches proxy body modification)
    //   • BOM / CRLF detection            (catches proxy encoding changes)
    //   • All headers from getallheaders() + $_SERVER HTTP_* keys
    //   • HMAC self-test against GitHub's official test vector
    //
    // ⚠️  Disable in production — log contains header values.
    // ──────────────────────────────────────────────────────────────────────────
    'debug' => false,

];
