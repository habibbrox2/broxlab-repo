<?php
/* Debug: Check db() function behavior */
require_once __DIR__ . '/../public_html/_db.php';

// First file
$file = dirname(__DIR__) . '/Database/user_roles.sql';
echo "Reading $file\n\n";

$mysqli = db();
$mysqli->query('SET FOREIGN_KEY_CHECKS=0');
$mysqli->query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO,ALLOW_INVALID_DATES'");

$handle = fopen($file, 'r');
$stmt = '';
while (($line = fgets($handle)) !== false) {
    $stmt .= $line;
    $trimmed = trim($line);
    
    if ($stmt === trim($line) && (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0)) {
        $stmt = '';
        continue;
    }
    
    if (substr($trimmed, -1) === ';') {
        $stmt = trim($stmt);
        echo "Executing: " . substr($stmt, 0, 100) . "...\n";
        if (!empty($stmt) && !preg_match('/^\-\-/', $stmt)) {
            if (!$mysqli->query($stmt)) {
                echo "ERROR: " . $mysqli->error . "\n\n";
            } else {
                echo "OK\n";
            }
        }
        $stmt = '';
    }
}
fclose($handle);