<?php

/**
 * Check working source configurations
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

echo "Checking working source configurations...\n\n";

// Get working sources
$workingSources = [21, 18, 17, 31]; // BBC Bangla, BBC Food, BBC Travel, BD News 24

foreach ($workingSources as $sourceId) {
    $stmt = $mysqli->prepare("SELECT id, name, url, selectors, advance_config, type, content_type FROM web_scraping_sources WHERE id = ?");
    $stmt->bind_param("i", $sourceId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo "Source ID {$row['id']}: {$row['name']}\n";
        echo "URL: {$row['url']}\n";
        echo "Type: {$row['type']}\n";
        echo "Content Type: {$row['content_type']}\n";

        $selectors = json_decode($row['selectors'], true);
        if ($selectors) {
            echo "Selectors: " . json_encode($selectors, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Selectors: NULL\n";
        }

        $config = json_decode($row['advance_config'], true);
        if ($config) {
            echo "Advance Config: " . json_encode($config, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Advance Config: NULL\n";
        }

        echo "\n";
    }
}

$mysqli->close();

?>