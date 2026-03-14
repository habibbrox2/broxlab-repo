<?php

/**
 * Deploy Configuration for cPanel Web Hosting
 * 
 * এই ফাইলটি GitHub Webhook থেকে auto-deploy করার জন্য প্রয়োজনীয় সকল কনফিগারেশন ধারণ করে।
 * 
 * @usage GitHub → Settings → Webhooks → Add webhook
 * URL: https://yourdomain.com/webhook/deploy.php
 * Content type: application/json
 * Secret: আপনার webhook secret (নিচে 'secret' এ দেওয়া)
 */

return [
    // ═══════════════════════════════════════════════════════════
    // Security - GitHub Webhook Secret
    // ═══════════════════════════════════════════════════════════
    // GitHub-এ webhook তৈরি করার সময় যে secret দেবেন, এখানে সেটা দিন
    'secret' => 'your_github_webhook_secret_here',

    // Deploy করার জন্য allowed branch
    'branch' => 'main',

    // ═══════════════════════════════════════════════════════════
    // Server Paths - cPanel এর জন্য সঠিক পাথ দিন
    // ═══════════════════════════════════════════════════════════

    // আপনার project যে directory-তে আছে (public_html এর বাইরে থাকলে ভালো)
    // সাধারণত: /home/username/public_html অথবা /home/username/project
    'project_dir' => '/home/tdhuedhn/broxlab',

    // Backup রাখার জন্য directory (public_html এর বাইরে হওয়া উচিত)
    'backup_dir' => '/home/tdhuedhn/backups/webhook',

    // Version info রাখার জন্য JSON file
    'version_file' => '/home/tdhuedhn/backups/webhook/version.json',

    // Deploy log রাখার জন্য file
    'log_file' => '/home/tdhuedhn/backups/webhook/deploy.log',

    // Database migration SQL files এর directory (যদি থাকে)
    'migration_dir' => '/home/tdhuedhn/broxlab/migrations',

    // ═══════════════════════════════════════════════════════════
    // Backup Settings
    // ═══════════════════════════════════════════════════════════

    // কতটি old backup রাখবে
    'keep_backups' => 5,

    // ═══════════════════════════════════════════════════════════
    // Database Configuration
    // ═══════════════════════════════════════════════════════════

    'db_host'        => '65.21.174.100',
    'db_name'        => 'tdhuedhn_broxbhai',
    'db_user'        => 'tdhuedhn_broxbhai',
    'db_pass'        => ',EnTio1PtqI-&M&D',

    // ═══════════════════════════════════════════════════════════
    // Advanced Settings
    // ═══════════════════════════════════════════════════════════

    // Auto deploy enable করবে কিনা (false থাকলে শুধু log হবে, deploy হবে না)
    'auto_deploy' => true,

    // Dry run mode - true থাকলে শুধু simulate হবে, আসলে deploy হবে না
    'dry_run' => false,
];
