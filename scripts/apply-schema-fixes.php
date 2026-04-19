<?php
require_once __DIR__ . '/../Config/Db.php';

if (!defined('DB_READY')) {
    die("Database not ready\n");
}

echo "Applying schema fixes...\n";

// Add slug and order columns to web_scraping_categories
try {
    $mysqli->query("ALTER TABLE web_scraping_categories ADD COLUMN slug VARCHAR(255) UNIQUE AFTER name");
    echo "Added slug column to web_scraping_categories\n";
} catch (Exception $e) {
    echo "Slug column may already exist: " . $e->getMessage() . "\n";
}

try {
    $mysqli->query("ALTER TABLE web_scraping_categories ADD COLUMN `order` INT DEFAULT 0 AFTER slug");
    echo "Added order column to web_scraping_categories\n";
} catch (Exception $e) {
    echo "Order column may already exist: " . $e->getMessage() . "\n";
}

// Add unique constraint on content_hash for web_scraping_articles
try {
    $mysqli->query("ALTER TABLE web_scraping_articles ADD UNIQUE KEY uk_content_hash (content_hash)");
    echo "Added unique key on content_hash for web_scraping_articles\n";
} catch (Exception $e) {
    echo "Unique key on content_hash may already exist: " . $e->getMessage() . "\n";
}

// Add unique constraint on source_url for web_scraping_mobiles
try {
    $mysqli->query("ALTER TABLE web_scraping_mobiles ADD UNIQUE KEY uk_source_url (source_url)");
    echo "Added unique key on source_url for web_scraping_mobiles\n";
} catch (Exception $e) {
    echo "Unique key on source_url may already exist: " . $e->getMessage() . "\n";
}

// Add missing stat columns to web_scraping_jobs
$statColumns = [
    'items_found' => 'INT DEFAULT 0',
    'items_saved' => 'INT DEFAULT 0',
    'items_failed' => 'INT DEFAULT 0',
    'avg_response_time' => 'DECIMAL(10,2) DEFAULT 0.00',
    'total_response_time' => 'DECIMAL(10,2) DEFAULT 0.00',
    'error_message' => 'TEXT',
    'result_data' => 'JSON'
];

foreach ($statColumns as $col => $type) {
    try {
        $mysqli->query("ALTER TABLE web_scraping_jobs ADD COLUMN `$col` $type");
        echo "Added $col column to web_scraping_jobs\n";
    } catch (Exception $e) {
        echo "$col column may already exist: " . $e->getMessage() . "\n";
    }
}

echo "Schema fixes completed.\n";
?>