<?php

/**
 * Migration script to add AI enhancement columns to web_scraping_articles table
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

echo "Adding AI enhancement columns to web_scraping_articles table...\n";

$columns = [
    "summary TEXT DEFAULT NULL",
    "excerpt VARCHAR(255) DEFAULT NULL",
    "seo_title VARCHAR(255) DEFAULT NULL",
    "seo_description TEXT DEFAULT NULL",
    "seo_keywords VARCHAR(255) DEFAULT NULL",
    "seo_score INT DEFAULT 0",
    "categories JSON DEFAULT NULL",
    "tags JSON DEFAULT NULL",
    "reading_time VARCHAR(50) DEFAULT NULL",
    "word_count INT DEFAULT 0",
    "enhanced_at DATETIME DEFAULT NULL",
    "enhancement_version VARCHAR(10) DEFAULT NULL"
];

// Get existing columns
$result = $mysqli->query("SHOW COLUMNS FROM web_scraping_articles");
$existingColumns = [];
while ($row = $result->fetch_assoc()) {
    $existingColumns[] = $row['Field'];
}

$columnNames = [];
foreach ($columns as $columnDef) {
    // Extract column name from definition (e.g., "summary TEXT DEFAULT NULL" -> "summary")
    $columnName = trim(explode(' ', $columnDef)[0]);
    $columnNames[] = $columnName;
}

foreach ($columns as $index => $column) {
    $columnName = $columnNames[$index];
    echo "Checking column: $columnName\n";

    if (in_array($columnName, $existingColumns)) {
        echo "✓ Column already exists\n";
        continue;
    }

    $sql = "ALTER TABLE web_scraping_articles ADD COLUMN $column";
    echo "Adding column: $column\n";

    if ($mysqli->query($sql)) {
        echo "✓ Successfully added column\n";
    } else {
        echo "✗ Error adding column: " . $mysqli->error . "\n";
    }
}

echo "\nMigration completed!\n";
$mysqli->close();

?>