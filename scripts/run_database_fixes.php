<?php
/**
 * Run database_fixes.sql migration
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');

$sqlFile = __DIR__ . '/../database_fixes.sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found: $sqlFile\n");
}

$content = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(';', $content)));

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) continue;
    echo "Executing: " . substr($statement, 0, 50) . "...\n";
    if ($mysqli->query($statement)) {
        echo "✓ Success\n";
    } else {
        echo "✗ Error: " . $mysqli->error . "\n";
    }
}

echo "Migration completed.\n";
?>