<?php
// Add template column to cvs table

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';

global $mysqli;

try {
    // Check if template column already exists
    $result = $mysqli->query("SHOW COLUMNS FROM cvs LIKE 'template'");
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE cvs ADD template VARCHAR(50) NOT NULL DEFAULT 'modern' AFTER title";
        if ($mysqli->query($sql)) {
            echo "Successfully added template column to cvs table.\n";
        } else {
            throw new Exception("Failed to add template column: " . $mysqli->error);
        }
    } else {
        echo "Template column already exists in cvs table.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration completed successfully.\n";
