<?php
require_once __DIR__ . '/../Config/Db.php';

if (!defined('DB_READY')) {
    die("Database not ready\n");
}

echo "Cleaning duplicate content_hash in web_scraping_articles...\n";

// Find duplicates
$result = $mysqli->query("
    SELECT content_hash, COUNT(*) as count
    FROM web_scraping_articles
    WHERE content_hash IS NOT NULL
    GROUP BY content_hash
    HAVING count > 1
");

$duplicates = [];
while ($row = $result->fetch_assoc()) {
    $duplicates[] = $row['content_hash'];
}

echo "Found " . count($duplicates) . " duplicate content_hash values\n";

foreach ($duplicates as $hash) {
    // Keep the first one (oldest), delete others
    $stmt = $mysqli->prepare("
        DELETE t1 FROM web_scraping_articles t1
        INNER JOIN web_scraping_articles t2
        WHERE t1.id > t2.id
        AND t1.content_hash = t2.content_hash
        AND t1.content_hash = ?
    ");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    echo "Deleted $deleted duplicates for hash $hash\n";
    $stmt->close();
}

echo "Duplicate cleanup completed.\n";

// Now try to add the unique key
try {
    $mysqli->query("ALTER TABLE web_scraping_articles ADD UNIQUE KEY uk_content_hash (content_hash)");
    echo "Added unique key on content_hash\n";
} catch (Exception $e) {
    echo "Failed to add unique key: " . $e->getMessage() . "\n";
}
?>