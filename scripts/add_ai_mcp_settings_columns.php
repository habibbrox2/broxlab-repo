<?php
/**
 * Migration: Add AI and MCP settings columns to app_settings table
 *
 * Run this script once to add the necessary columns.
 * Usage: php scripts/add_ai_mcp_settings_columns.php
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

// Create direct connection (bypassing _db.php auth check)
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');

$columns = [
    'ai_provider' => "VARCHAR(50) DEFAULT 'openrouter'",
    'ai_api_key' => 'TEXT DEFAULT NULL',
    'ai_model' => "VARCHAR(255) DEFAULT 'openrouter/auto'",
    'mcp_server_url' => 'VARCHAR(255) DEFAULT NULL',
    'mcp_api_key' => 'TEXT DEFAULT NULL',
];

$results = [];
foreach ($columns as $column => $definition) {
    // Check if column exists
    $result = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE '$column'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE app_settings ADD COLUMN `$column` $definition";
        if ($mysqli->query($sql)) {
            $results[] = "Added column: $column";
        } else {
            $results[] = "Error adding column $column: " . $mysqli->error;
        }
    } else {
        $results[] = "Column $column already exists.";
    }
}

foreach ($results as $msg) {
    echo $msg . "\n";
}

echo "Migration completed.\n";