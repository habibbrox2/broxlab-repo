<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/Db.php';
global $mysqli;

if (!$mysqli) {
    echo "No database connection\n";
    exit;
}

echo "Connected to database\n";

// Check if tables exist
$tables = ['roles', 'user_roles'];
foreach ($tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table $table exists\n";
    } else {
        echo "Table $table does not exist\n";
    }
}

// Check roles table
$result = $mysqli->query('SELECT COUNT(*) as count FROM roles');
if ($result) {
    $row = $result->fetch_assoc();
    echo "Roles count: " . $row['count'] . "\n";
} else {
    echo "Query failed: " . $mysqli->error . "\n";
}

// Check user_roles table
$result = $mysqli->query('SELECT COUNT(*) as count FROM user_roles');
if ($result) {
    $row = $result->fetch_assoc();
    echo "User roles count: " . $row['count'] . "\n";
} else {
    echo "Query failed: " . $mysqli->error . "\n";
}
