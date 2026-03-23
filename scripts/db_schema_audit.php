<?php
declare(strict_types=1);

// Database schema audit: compare DB tables to Database/*.sql definitions.
// Outputs report JSON + migration SQL for missing tables/columns/indexes.
// Usage: php scripts/db_schema_audit.php

require_once __DIR__ . '/../config/Db.php';

if (!defined('DB_READY') || !isset($mysqli)) {
    fwrite(STDERR, "DB not ready. Check config/Db.php\n");
    exit(1);
}

function readSqlFiles(string $dir): array
{
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    $out = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            continue;
        }
        $out[$file] = $contents;
    }
    return $out;
}

function extractCreateTable(string $sql): ?string
{
    $pattern = '/CREATE\\s+TABLE\\s+(IF\\s+NOT\\s+EXISTS\\s+)?(`?)([a-zA-Z0-9_]+)\\2\\s*\\((.*)\\)\\s*[^;]*;/ims';
    if (preg_match($pattern, $sql, $m)) {
        return $m[0];
    }
    return null;
}

function extractTableName(string $sql): ?string
{
    $pattern = '/CREATE\\s+TABLE\\s+(IF\\s+NOT\\s+EXISTS\\s+)?(`?)([a-zA-Z0-9_]+)\\2/ims';
    if (preg_match($pattern, $sql, $m)) {
        return $m[3];
    }
    return null;
}

function splitDefinitions(string $sql): array
{
    $start = strpos($sql, '(');
    if ($start === false) {
        return [];
    }
    $depth = 0;
    $buffer = '';
    $defs = [];
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $len = strlen($sql);
    for ($i = $start + 1; $i < $len; $i++) {
        $ch = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($ch === "'" && !$inDouble && !$inBacktick && $prev !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($ch === '"' && !$inSingle && !$inBacktick && $prev !== '\\') {
            $inDouble = !$inDouble;
        } elseif ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        if (!$inSingle && !$inDouble && !$inBacktick && $ch === '(') {
            $depth++;
        } elseif (!$inSingle && !$inDouble && !$inBacktick && $ch === ')') {
            if ($depth === 0) {
                $trim = trim($buffer);
                if ($trim !== '') {
                    $defs[] = $trim;
                }
                break;
            }
            $depth--;
        }
        if (!$inSingle && !$inDouble && !$inBacktick && $ch === ',' && $depth === 0) {
            $trim = trim($buffer);
            if ($trim !== '') {
                $defs[] = $trim;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    return $defs;
}

function parseDefinitions(string $createSql): array
{
    $defs = splitDefinitions($createSql);
    $columns = [];
    $indexes = [];
    foreach ($defs as $def) {
        $trim = trim($def);
        if ($trim === '') {
            continue;
        }

        if (preg_match('/^`([a-zA-Z0-9_]+)`\\s+(.+)$/s', $trim, $m)) {
            $columns[$m[1]] = $trim;
            continue;
        }
        if (preg_match('/^([a-zA-Z0-9_]+)\\s+(.+)$/s', $trim, $m)) {
            $keyword = strtoupper($m[1]);
            if ($keyword !== 'PRIMARY' && $keyword !== 'UNIQUE' && $keyword !== 'KEY' && $keyword !== 'INDEX' && $keyword !== 'CONSTRAINT' && $keyword !== 'FOREIGN') {
                $columns[$m[1]] = $trim;
                continue;
            }
        }

        $indexes[] = $trim;
    }
    return [$columns, $indexes];
}

function fetchTables(mysqli $mysqli): array
{
    $res = $mysqli->query('SHOW TABLES');
    $tables = [];
    if ($res) {
        while ($row = $res->fetch_array()) {
            $tables[] = $row[0];
        }
        $res->free();
    }
    return $tables;
}

function fetchColumns(mysqli $mysqli, string $table): array
{
    $stmt = $mysqli->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $cols = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['COLUMN_NAME']] = $row;
        }
        $res->free();
    }
    $stmt->close();
    return $cols;
}

function fetchIndexes(mysqli $mysqli, string $table): array
{
    $stmt = $mysqli->prepare('SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $idx = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $idx[$row['INDEX_NAME']] = (int)$row['NON_UNIQUE'];
        }
        $res->free();
    }
    $stmt->close();
    return $idx;
}

$sqlFiles = readSqlFiles(__DIR__ . '/../Database');
$expected = [];

foreach ($sqlFiles as $path => $sql) {
    $create = extractCreateTable($sql);
    $table = $create ? extractTableName($create) : null;
    if (!$create || !$table) {
        continue;
    }
    [$columns, $indexes] = parseDefinitions($create);
    $expected[$table] = [
        'create_sql' => $create,
        'columns' => $columns,
        'indexes' => $indexes,
        'file' => $path,
    ];
}

$actualTables = fetchTables($mysqli);
$actualTableSet = array_flip($actualTables);

$report = [
    'generated_at' => date('c'),
    'expected_tables' => array_keys($expected),
    'actual_tables' => $actualTables,
    'missing_tables' => [],
    'extra_tables' => [],
    'table_diffs' => [],
];

$migrationSql = [];
$migrationSql[] = "-- Auto-generated migration (schema audit)";
$migrationSql[] = "-- Generated at: " . date('c');
$migrationSql[] = "START TRANSACTION;";

foreach ($expected as $table => $info) {
    if (!isset($actualTableSet[$table])) {
        $report['missing_tables'][] = $table;
        $migrationSql[] = "";
        $migrationSql[] = "-- Missing table: {$table}";
        $migrationSql[] = $info['create_sql'];
        continue;
    }

    $actualCols = fetchColumns($mysqli, $table);
    $actualIdx = fetchIndexes($mysqli, $table);

    $missingCols = [];
    $missingIdx = [];

    foreach ($info['columns'] as $col => $def) {
        if (!isset($actualCols[$col])) {
            $missingCols[$col] = $def;
        }
    }

    foreach ($info['indexes'] as $idxDef) {
        $idxName = null;
        if (preg_match('/^PRIMARY\\s+KEY/i', $idxDef)) {
            $idxName = 'PRIMARY';
        } elseif (preg_match('/^UNIQUE\\s+KEY\\s+`?([a-zA-Z0-9_]+)`?/i', $idxDef, $m)) {
            $idxName = $m[1];
        } elseif (preg_match('/^KEY\\s+`?([a-zA-Z0-9_]+)`?/i', $idxDef, $m)) {
            $idxName = $m[1];
        } elseif (preg_match('/^INDEX\\s+`?([a-zA-Z0-9_]+)`?/i', $idxDef, $m)) {
            $idxName = $m[1];
        }

        if ($idxName !== null && !isset($actualIdx[$idxName])) {
            $missingIdx[] = $idxDef;
        }
    }

    if (!empty($missingCols) || !empty($missingIdx)) {
        $report['table_diffs'][$table] = [
            'missing_columns' => array_keys($missingCols),
            'missing_indexes' => $missingIdx,
            'file' => $info['file'],
        ];
        foreach ($missingCols as $col => $def) {
            $migrationSql[] = "";
            $migrationSql[] = "-- Missing column: {$table}.{$col}";
            $migrationSql[] = "ALTER TABLE `{$table}` ADD COLUMN {$def};";
        }
        foreach ($missingIdx as $idxDef) {
            $migrationSql[] = "";
            $migrationSql[] = "-- Missing index on {$table}";
            $migrationSql[] = "ALTER TABLE `{$table}` ADD {$idxDef};";
        }
    }
}

// Extra tables in DB not present in expected files
foreach ($actualTables as $table) {
    if (!isset($expected[$table])) {
        $report['extra_tables'][] = $table;
    }
}

$migrationSql[] = "";
$migrationSql[] = "COMMIT;";

$ts = date('Ymd_His');
$reportPath = __DIR__ . '/../storage/db_schema_audit_' . $ts . '.json';
$migrationPath = __DIR__ . '/../Database/migrations/auto_schema_fix_' . $ts . '.sql';

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($migrationPath, implode(PHP_EOL, $migrationSql) . PHP_EOL);

echo "DB schema audit complete.\n";
echo "Report: {$reportPath}\n";
echo "Migration SQL: {$migrationPath}\n";
echo "Missing tables: " . count($report['missing_tables']) . "\n";
echo "Tables with diffs: " . count($report['table_diffs']) . "\n";
echo "Extra tables: " . count($report['extra_tables']) . "\n";
