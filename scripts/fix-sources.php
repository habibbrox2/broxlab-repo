<?php

/**
 * Fix scraper source configurations
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

echo "Fixing scraper source configurations...\n\n";

// Sources to fix: change type from 'scrape' to 'html' for sources without selectors
$sourcesToFix = [
    7 => 'Ittefaq',
    25 => 'Ittefaq Latest',
    1 => 'Prothom Alo Latest',
    32 => 'Teletalk Government Jobs'
];

foreach ($sourcesToFix as $sourceId => $name) {
    echo "Fixing source: $name (ID: $sourceId)\n";

    $stmt = $mysqli->prepare("UPDATE web_scraping_sources SET type = 'html' WHERE id = ?");
    $stmt->bind_param("i", $sourceId);

    if ($stmt->execute()) {
        echo "✓ Successfully changed type to 'html'\n";
    } else {
        echo "✗ Failed to update: " . $stmt->error . "\n";
    }

    echo "\n";
}

// Fix Stack Overflow user agent
echo "Fixing Stack Overflow user agent...\n";
$soUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
$stmt = $mysqli->prepare("UPDATE web_scraping_sources SET advance_config = JSON_SET(COALESCE(advance_config, '{}'), '$.user_agent', ?) WHERE id = 37");
$stmt->bind_param("s", $soUserAgent);

if ($stmt->execute()) {
    echo "✓ Successfully updated Stack Overflow user agent\n";
} else {
    echo "✗ Failed to update Stack Overflow: " . $stmt->error . "\n";
}

echo "\nConfiguration fixes completed!\n";

$mysqli->close();

?>