<?php

/**
 * Create Scraper Database Tables
 * Run this script to create all required tables for the web scraping system
 */

declare(strict_types=1);

// Load environment and database connection
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Load database configuration
require_once __DIR__ . '/../Config/Db.php';

echo "=== Creating Web Scraping Database Tables ===\n\n";

try {
    global $mysqli;
    if (!$mysqli) {
        throw new Exception("Database connection not available");
    }

    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/create_scraper_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $created = 0;
    $skipped = 0;

    foreach ($statements as $statement) {
        if (empty($statement)) continue;

        try {
            if ($mysqli->query($statement)) {
                echo "✓ Table created or already exists\n";
                $created++;
            } else {
                echo "✗ Failed to create table: " . $mysqli->error . "\n";
            }
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== Summary ===\n";
    echo "Tables processed: $created\n";

    // Verify tables exist
    echo "\n=== Verification ===\n";
    $tables = ['web_scraping_sources', 'web_scraping_articles', 'web_scraping_queue',
               'web_scraping_jobs', 'web_scraping_logs', 'web_scraping_stats',
               'web_scraping_seen_urls', 'web_scraping_settings', 'web_scraping_categories',
               'web_scraping_mobiles', 'collection_jobs'];

    foreach ($tables as $table) {
        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ $table exists\n";
        } else {
            echo "✗ $table missing\n";
        }
    }

    echo "\n=== Setup Complete ===\n";
    echo "You can now use the web scraping system.\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}