<?php

/**
 * AI Usage Logs Fix Script
 * 
 * Run this script to fix the ai_usage_logs table AUTO_INCREMENT issue.
 * Usage: Upload to public_html and visit: https://yoursite.com/fix-ai-usage-logs.php
 * OR run via CLI: php fix-ai-usage-logs.php
 */

$isWeb = php_sapi_name() !== 'cli';

function logMsg($msg, $type = 'info')
{
    global $isWeb;
    $colors = [
        'info' => 'color: green',
        'error' => 'color: red',
        'warning' => 'color: orange',
        'success' => 'color: blue'
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
    <title>AI Usage Logs Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>AI Usage Logs Fix</h4>
            </div>
            <div class="card-body">';
}

logMsg("Starting AI Usage Logs fix...", "info");
echo "<br>";

// Load database configuration
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    logMsg(".env file not found!", "error");
    exit(1);
}

$env = parse_ini_file($envFile);
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

if (empty($dbName) || empty($dbUser)) {
    logMsg("Database configuration not found in .env!", "error");
    exit(1);
}

logMsg("Connecting to database...", "info");

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($mysqli->connect_error) {
    logMsg("Database connection failed: " . $mysqli->connect_error, "error");
    exit(1);
}

logMsg("Connected successfully!", "success");

// Check if table exists
$result = $mysqli->query("SHOW TABLES LIKE 'ai_usage_logs'");
if ($result->num_rows === 0) {
    logMsg("Table ai_usage_logs does not exist. Creating table...", "warning");

    $createSQL = "CREATE TABLE IF NOT EXISTS ai_usage_logs (
        id INT NOT NULL AUTO_INCREMENT,
        provider_name VARCHAR(100) NOT NULL,
        model_name VARCHAR(100) NOT NULL,
        prompt_tokens INT DEFAULT 0,
        completion_tokens INT DEFAULT 0,
        total_tokens INT DEFAULT 0,
        cost DECIMAL(10, 6) DEFAULT 0,
        request_type VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL,
        error_message TEXT,
        user_id INT,
        metadata JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_provider (provider_name),
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($mysqli->query($createSQL)) {
        logMsg("Table created successfully!", "success");
    } else {
        logMsg("Failed to create table: " . $mysqli->error, "error");
        exit(1);
    }
} else {
    logMsg("Table ai_usage_logs exists. Checking structure...", "info");

    // Get table structure
    $result = $mysqli->query("DESCRIBE ai_usage_logs");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row;
    }

    // Check if id column exists and has AUTO_INCREMENT
    if (isset($columns['id'])) {
        $idColumn = $columns['id'];

        if (strpos($idColumn['Extra'], 'auto_increment') === false) {
            logMsg("id column found but AUTO_INCREMENT is missing. Fixing...", "warning");

            // Try to add AUTO_INCREMENT
            $fixSQL = "ALTER TABLE ai_usage_logs MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT";

            if ($mysqli->query($fixSQL)) {
                logMsg("AUTO_INCREMENT added successfully!", "success");
            } else {
                // Try alternative syntax
                $fixSQL2 = "ALTER TABLE ai_usage_logs CHANGE COLUMN id id INT NOT NULL AUTO_INCREMENT";
                if ($mysqli->query($fixSQL2)) {
                    logMsg("AUTO_INCREMENT added successfully!", "success");
                } else {
                    logMsg("Failed to add AUTO_INCREMENT: " . $mysqli->error, "error");
                    logMsg("Please run this SQL manually in phpMyAdmin:", "error");
                    logMsg("ALTER TABLE ai_usage_logs MODIFY id INT NOT NULL AUTO_INCREMENT;", "error");
                }
            }
        } else {
            logMsg("id column already has AUTO_INCREMENT. No changes needed!", "success");
        }
    } else {
        logMsg("id column not found in table!", "error");
    }
}

$mysqli->close();

echo "<br>";
logMsg("=========================================", "info");
logMsg("Fix Complete!", "success");
logMsg("=========================================", "info");

if ($isWeb) {
    echo '<hr>
        <div class="alert alert-success">
            <h5>Fix Complete!</h5>
            <p>The ai_usage_logs table has been fixed.</p>
            <p>You can now try the AI chat again.</p>
        </div>
        <a href="/admin/ai-system" class="btn btn-primary">Go to AI System</a>
    </div>
</div>
</body>
</html>';
} else {
    echo "\nFix complete! You can now try the AI chat again.\n";
}
