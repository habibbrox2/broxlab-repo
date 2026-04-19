<?php
// Run the full migration SQL file (Database/migrations/001_full_migration.sql)
// This script will also ensure the `categories` table has the columns needed by the migration inserts.

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../Config/Db.php';

global $mysqli;

function columnExists(mysqli $mysqli, string $table, string $column): bool
{
    $escTable = $mysqli->real_escape_string($table);
    $escColumn = $mysqli->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escTable' AND COLUMN_NAME = '$escColumn' LIMIT 1";
    $res = $mysqli->query($sql);
    return $res && $res->num_rows > 0;
}

function ensureTableColumns(mysqli $mysqli, string $table, array $requiredColumns, array $requiredIndexes = []): void
{
    $columnsToAdd = [];
    foreach ($requiredColumns as $col => $def) {
        // strip backticks for check
        $cleanCol = trim($col, "`");
        if (!columnExists($mysqli, $table, $cleanCol)) {
            $columnsToAdd[$col] = $def;
        }
    }

    if (!empty($columnsToAdd)) {
        $sql = "ALTER TABLE `" . $mysqli->real_escape_string($table) . "`";
        $first = true;
        foreach ($columnsToAdd as $col => $def) {
            if (!$first) {
                $sql .= ",\n  ADD ";
            } else {
                $sql .= "\n  ADD ";
                $first = false;
            }
            $sql .= "$col $def";
        }
        $sql .= ";";

        echo "Applying schema changes to $table...\n";
        if (!$mysqli->query($sql)) {
            throw new RuntimeException("Failed to alter $table: " . $mysqli->error);
        }
    }

    // Ensure indexes
    foreach ($requiredIndexes as $index) {
        $res = $mysqli->query("SHOW INDEX FROM `" . $mysqli->real_escape_string($table) . "` WHERE Key_name = '" . $mysqli->real_escape_string($index) . "'");
        if ($res && $res->num_rows === 0) {
            $mysqli->query("ALTER TABLE `" . $mysqli->real_escape_string($table) . "` ADD KEY `" . $mysqli->real_escape_string($index) . "` (`" . $mysqli->real_escape_string($index) . "`)");
        }
    }

    echo ucfirst($table) . " schema updated.\n";
}

function ensureCategoriesColumns(mysqli $mysqli): void
{
    $required = [
        'parent_id' => "int(11) DEFAULT NULL",
        'image' => "varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        'icon' => "varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        '`order`' => "int(11) DEFAULT 0",
        'is_featured' => "tinyint(1) DEFAULT 0",
        'meta_title' => "varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        'meta_description' => "text COLLATE utf8mb4_unicode_ci",
        'status' => "enum('active','inactive') DEFAULT 'active'",
        'created_at' => "datetime DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    ensureTableColumns($mysqli, 'categories', $required, ['parent_id']);
}

function runMigrationFile(mysqli $mysqli, string $filePath): void
{
    if (!file_exists($filePath)) {
        throw new RuntimeException("Migration file not found: $filePath");
    }

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new RuntimeException("Failed to read migration file: $filePath");
    }

    echo "Running migration file: $filePath\n";

    if (!$mysqli->multi_query($sql)) {
        throw new RuntimeException('Migration failed: ' . $mysqli->error);
    }

    // Drain results
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    echo "Migration completed.\n";
}

try {
    ensureCategoriesColumns($mysqli);

    // Ensure autocontent_sources has the columns required by the migration inserts
    ensureTableColumns($mysqli, 'autocontent_sources', [
        'selector_list_container' => 'text',
        'selector_list_item' => 'varchar(255) DEFAULT NULL',
        'selector_list_title' => 'varchar(255) DEFAULT NULL',
        'selector_list_date' => 'varchar(255) DEFAULT NULL',
        'selector_list_url' => 'varchar(255) DEFAULT NULL',
        'selector_title' => 'varchar(255) DEFAULT NULL',
        'selector_content' => 'text',
        'selector_image' => 'varchar(255) DEFAULT NULL',
        'selector_excerpt' => 'text',
        'selector_date' => 'varchar(255) DEFAULT NULL',
        'selector_author' => 'varchar(255) DEFAULT NULL',
        'pagination_type' => "varchar(50) DEFAULT 'none'",
        'pagination_selector' => 'varchar(255) DEFAULT NULL',
        'pagination_pattern' => 'varchar(255) DEFAULT NULL',
        'max_pages' => 'int(11) DEFAULT 10',
        'proxy_enabled' => 'tinyint(1) DEFAULT 0',
        'proxy_provider' => 'varchar(50) DEFAULT NULL',
        'proxy_config' => 'text',
        'fetch_interval' => 'int(11) DEFAULT 3600',
        'is_active' => 'tinyint(1) DEFAULT 1',
        'last_fetch' => 'datetime DEFAULT NULL',
        'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ], ['category_id', 'is_active', 'last_fetch']);

    // Ensure posts table has required columns for seed data
    ensureTableColumns($mysqli, 'posts', [
        'content' => 'longtext',
        'excerpt' => 'text',
        'category_id' => 'int(11) DEFAULT NULL',
        'author_id' => 'int(11) DEFAULT NULL',
        'image' => 'varchar(255) DEFAULT NULL',
        'image_caption' => 'varchar(255) DEFAULT NULL',
        'video_url' => 'varchar(255) DEFAULT NULL',
        'view_count' => 'int(11) DEFAULT 0',
        'comment_count' => 'int(11) DEFAULT 0',
        'is_featured' => 'tinyint(1) DEFAULT 0',
        'is_sticky' => 'tinyint(1) DEFAULT 0',
        'meta_title' => 'varchar(200) DEFAULT NULL',
        'meta_description' => 'text',
        'meta_keywords' => 'text',
        'status' => "enum('draft','published','archived','scheduled') DEFAULT 'draft'",
        'published_at' => 'datetime DEFAULT NULL',
        'scheduled_at' => 'datetime DEFAULT NULL',
        'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ], ['category_id', 'author_id', 'status']);

    runMigrationFile($mysqli, __DIR__ . '/../Database/migrations/001_full_migration.sql');
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
