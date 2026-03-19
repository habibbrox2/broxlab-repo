<?php
// Migration script for Telegram rate limit table
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'Config/Db.php';

// Create rate limit table
$sql = "CREATE TABLE IF NOT EXISTS telegram_rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_hash VARCHAR(64) NOT NULL,
    created_at INT NOT NULL,
    expires_at INT NOT NULL,
    INDEX idx_ip_hash (ip_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql)) {
    echo "✅ Telegram rate limit table created successfully\n";
} else {
    echo "❌ Failed to create table: " . $mysqli->error . "\n";
}