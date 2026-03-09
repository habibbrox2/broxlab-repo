<?php
// Temporary script to drop the unused admins table.

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables (similar to other scripts)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';

try {
    $mysqli->query('DROP TABLE IF EXISTS `admins`');
    echo "Dropped admins table\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
