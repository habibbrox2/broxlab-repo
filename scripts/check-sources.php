<?php

/**
 * Check scraper source configurations
 */

// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

echo "Checking scraper source configurations...\n\n";

// Get sources that had issues
$problematicSources = [7, 25, 1, 32, 35, 36, 37]; // Ittefaq, Ittefaq Latest, Prothom Alo, Teletalk, GitHub, Reddit, Stack Overflow

foreach ($problematicSources as $sourceId) {
    $stmt = $mysqli->prepare("SELECT id, name, url, selectors, advance_config, type FROM web_scraping_sources WHERE id = ?");
    $stmt->bind_param("i", $sourceId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo "Source ID {$row['id']}: {$row['name']}\n";
        echo "URL: {$row['url']}\n";
        echo "Type: {$row['type']}\n";

        if ($row['selectors']) {
            $selectors = json_decode($row['selectors'], true);
            echo "Selectors: " . json_encode($selectors, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Selectors: NULL\n";
        }

        if ($row['advance_config']) {
            $config = json_decode($row['advance_config'], true);
            echo "Advance Config: " . json_encode($config, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Advance Config: NULL\n";
        }

        echo "\n";
    } else {
        echo "Source ID $sourceId not found\n\n";
    }
}

$mysqli->close();

?>