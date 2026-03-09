<?php

/**
 * CLI helper: create a full database export (structure + data) and write a stable
 * copy to Database/full/latest.sql.
 *
 * Usage:
 *   php scripts/db-backup.php [--structure-only] [--output full/latest.sql] [--keep-archives]
 */

declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
array_shift($argv); // drop script path

$structureOnly = false;
$outputPath = 'full/latest.sql';
$keepArchives = false;

while (count($argv) > 0) {
    $arg = array_shift($argv);
    if ($arg === '--structure-only') {
        $structureOnly = true;
    } elseif ($arg === '--keep-archives') {
        $keepArchives = true;
    } elseif ($arg === '--output' && count($argv) > 0) {
        $outputPath = array_shift($argv);
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php scripts/db-backup.php [--structure-only] [--output <path>] [--keep-archives]\n";
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
}

// Ensure CLI mode has minimal server vars to avoid warnings in _db.php
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

// Load the existing export helpers
require_once __DIR__ . '/../public_html/_db.php';

if (!defined('BACKUP_DIR')) {
    fwrite(STDERR, "ERROR: BACKUP_DIR is not defined (failed to load public_html/_db.php)\n");
    exit(1);
}

// Ensure the full/ folder exists
@mkdir(BACKUP_DIR . 'full', 0755, true);

$timestamp = date('Y-m-d_H-i-s');
$archiveFilename = "full/full_database_{$timestamp}.sql";

echo "Starting database export...\n";
$init = fullDatabaseSingleFileInit(/* allowDrop */false, $structureOnly);
$filename = $init['filename'] ?? $archiveFilename;
$tasks = $init['tasks'] ?? [];

foreach ($tasks as $task) {
    $table = $task['table'];
    $offset = 0;

    while (true) {
        $result = exportTableChunkToSingleFile($table, $filename, $offset, /* allowDrop */ false, $structureOnly);
        if (empty($result['success'])) {
            fwrite(STDERR, "Export failed for table {$table}\n");
            exit(1);
        }

        $offset += $result['processed'] ?? 0;
        if (!empty($result['finished'])) {
            break;
        }
    }
}

finalizeSingleFile($filename);

$sourcePath = BACKUP_DIR . $filename;
$targetPath = BACKUP_DIR . ltrim($outputPath, '/\\');

$targetDir = dirname($targetPath);
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}

if (!copy($sourcePath, $targetPath)) {
    fwrite(STDERR, "Failed to copy {$sourcePath} -> {$targetPath}\n");
    exit(1);
}

echo "✅ Export complete:\n";
echo " - Archive: {$sourcePath}\n";
echo " - Stable latest copy: {$targetPath}\n";

exit(0);
