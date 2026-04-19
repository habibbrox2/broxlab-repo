<?php
require_once __DIR__ . '/../Config/Db.php';

if (!defined('DB_READY')) {
    die("Database not ready\n");
}

echo "Database connected successfully\n";

// Get all tables
$result = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "Tables found: " . count($tables) . "\n";

foreach ($tables as $table) {
    echo "\n=== TABLE: $table ===\n";
    $result = $mysqli->query("SHOW CREATE TABLE `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        echo $row['Create Table'] . "\n";
    } else {
        echo "Error getting schema for $table\n";
    }
}

echo "\nDone.\n";
?>