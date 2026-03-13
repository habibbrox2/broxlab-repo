<?php

/**
 * BroxBhai Auto-Deploy Setup Script
 * 
 * Run this on your webhost ONCE to set up deployment.
 * 
 * Usage: Upload to public_html and visit:
 * https://yoursite.com/setup-deploy.php
 */

$config = [
    'repo_url' => 'https://github.com/habibbrox2/broxlab-repo.git',
    'branch' => 'main',
    'deploy_path' => '/home/username/public_html',
    'db_host' => 'localhost',
    'db_name' => 'your_database_name',
    'db_user' => 'your_database_user',
    'db_pass' => 'your_database_password',
    'app_env' => 'production',
    'app_timezone' => 'Asia/Dhaka',
];

$isWeb = php_sapi_name() !== 'cli';

function logMsg($msg, $type = 'info')
{
    global $isWeb;
    $colors = [
        'info' => 'color: green',
        'error' => 'color: red',
        'warning' => 'color: orange',
    ];
    $style = $colors[$type] ?? $colors['info'];
    if ($isWeb) {
        echo '<div style="' . $style . '; margin: 5px 0;">➜ ' . htmlspecialchars($msg) . '</div>';
    } else {
        echo "➜ " . $msg . "\n";
    }
}

if ($isWeb) {
    echo '<!DOCTYPE html>
<html>
<head>
    <title>BroxBhai Deploy Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>BroxBhai Auto-Deploy Setup</h4>
            </div>
            <div class="card-body">';
}

logMsg("Starting BroxBhai Setup...", "info");
echo "<br>";

// Check Git
logMsg("Step 1: Checking Git...");
$gitCheck = shell_exec('git --version');
if (strpos($gitCheck, 'git version') !== false) {
    logMsg("✓ Git is installed: " . trim($gitCheck), "info");
} else {
    logMsg("✗ Git is not installed. Install via: yum install git", "error");
}

// Create .env
logMsg("Step 2: Creating .env file...", "info");
$envContent = "APP_ENV=" . $config['app_env'] . "\n";
$envContent .= "APP_DEBUG=false\n";
$envContent .= "APP_TIMEZONE=" . $config['app_timezone'] . "\n\n";
$envContent .= "DB_HOST=" . $config['db_host'] . "\n";
$envContent .= "DB_NAME=" . $config['db_name'] . "\n";
$envContent .= "DB_USER=" . $config['db_user'] . "\n";
$envContent .= "DB_PASS=" . $config['db_pass'] . "\n";
$envContent .= "DB_CHARSET=utf8mb4\n";

$envPath = dirname(__FILE__) . '/.env';
if (file_exists($envPath)) {
    logMsg(".env already exists", "warning");
} else {
    file_put_contents($envPath, $envContent);
    logMsg("✓ .env file created", "info");
}

// Clone repo
logMsg("Step 3: Setting up repository...", "info");
$deployPath = $config['deploy_path'];

if (!is_dir($deployPath . '/.git')) {
    $cmd = "git clone -b " . $config['branch'] . " " . $config['repo_url'] . " " . escapeshellarg($deployPath) . " 2>&1";
    logMsg("Cloning repository (may take a minute)...", "info");
    $output = shell_exec($cmd);
    logMsg("✓ Repository cloned", "info");
} else {
    logMsg("Repository already exists", "warning");
}

// Set permissions
logMsg("Step 4: Setting permissions...", "info");
$dirs = ['storage', 'storage/backups', 'storage/logs', 'public_html/uploads'];
foreach ($dirs as $dir) {
    $fullPath = dirname(__FILE__) . '/' . $dir;
    if (is_dir($fullPath)) {
        chmod($fullPath, 0755);
    }
}
logMsg("✓ Permissions set", "info");

echo "<br>";
logMsg("=========================================", "info");
logMsg("Setup Complete!", "info");
logMsg("=========================================", "info");

if ($isWeb) {
    echo '<hr>
        <div class="alert alert-success">
            <h5>Setup Complete!</h5>
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>Configure GitHub Webhook in Admin Panel</li>
                <li>Add webhook in GitHub: Payload URL = https://broxlab.online/webhook/github.php</li>
                <li>Push from local: <code>git push origin main</code></li>
            </ol>
        </div>
    </div>
</div>
</body>
</html>';
} else {
    echo "\nNext Steps:\n1. Configure GitHub Webhook in Admin Panel\n2. Add webhook in GitHub\n3. Push from local\n";
}
