<?php
/**
 * CLI helper: import a SQL dump into the configured database.
 *
 * Usage:
 *   php scripts/db-restore.php [--file Database/full/latest.sql] [--allow-drop] [--enable-fk] [--yes]
 */

declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv); // drop script path

$dbFile = __DIR__ . '/../Database/full/latest.sql';
$allowDrop = false;
$enableFK = false;
$autoYes = false;

while (count($argv) > 0) {
    $arg = array_shift($argv);
    if ($arg === '--file' && count($argv) > 0) {
        $dbFile = array_shift($argv);
    } elseif ($arg === '--allow-drop') {
        $allowDrop = true;
    } elseif ($arg === '--enable-fk') {
        $enableFK = true;
    } elseif ($arg === '--yes' || $arg === '-y') {
        $autoYes = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php scripts/db-restore.php [--file <path>] [--allow-drop] [--enable-fk] [--yes]\n";
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
}

$dbFile = realpath($dbFile) ?: $dbFile;

if (!file_exists($dbFile)) {
    fwrite(STDERR, "ERROR: SQL file not found: {$dbFile}\n");
    exit(1);
}

if (!$autoYes) {
    fwrite(STDOUT, "About to import: {$dbFile}\n");
    fwrite(STDOUT, "This will modify the database configured in public_html/_db.php. Continue? [y/N]: ");
    $handle = fopen('php://stdin', 'r');
    $line = fgets($handle);
    $line = trim(strtolower((string)$line));
    if ($line !== 'y' && $line !== 'yes') {
        fwrite(STDOUT, "Aborted.\n");
        exit(0);
    }
}

require_once __DIR__ . '/../public_html/_db.php';

try {
    echo "Importing SQL file...\n";
    $result = importSQLFile($dbFile, $allowDrop, $enableFK);
    if (!empty($result['success'])) {
        echo "✅ Import completed.\n";
        if (isset($result['errors']) && count($result['errors']) > 0) {
            echo "Warnings / errors:\n";
            foreach ($result['errors'] as $err) {
                echo " - {$err}\n";
            }
        }
        exit(0);
    }

    fwrite(STDERR, "Import failed.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}
