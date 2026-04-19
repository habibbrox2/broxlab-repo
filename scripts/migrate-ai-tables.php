<?php

/**
 * AI Module Database Migration Runner
 * Path: /scripts/migrate-ai-tables.php
 * 
 * Run: php scripts/migrate-ai-tables.php
 * 
 * Creates AI module database tables:
 * - ai_conversations
 * - ai_messages
 * - ai_usage_logs
 * - ai_command_registry
 * - ai_tool_registry
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$env = $_ENV + $_SERVER;
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';
$dbCharset = $env['DB_CHARSET'] ?? 'utf8mb4';

// Validate configuration
if (!$dbName || !$dbUser) {
    die("❌ Error: Database configuration missing (DB_NAME / DB_USER)\n");
}

// Connect to database
try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $mysqli->set_charset($dbCharset);
    echo "✅ Connected to database: $dbName\n\n";
} catch (Exception $e) {
    die("❌ Connection failed: " . $e->getMessage() . "\n");
}

// Read migration SQL file
$sqlFile = __DIR__ . '/../Database/ai_tables_migration.sql';
if (!file_exists($sqlFile)) {
    die("❌ Error: Migration file not found: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Split SQL statements by semicolon (simple parser)
$statements = array_filter(
    array_map('trim', explode(";\n", $sql)),
    function ($s) {
        return !empty($s) && strpos($s, '--') !== 0;
    }
);

$successCount = 0;
$errorCount = 0;

echo "🔄 Running " . count($statements) . " migration statements...\n";
echo str_repeat("-", 60) . "\n";

foreach ($statements as $i => $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;

    try {
        // Add semicolon if missing
        if (!str_ends_with($statement, ';')) {
            $statement .= ';';
        }

        // Extract table/operation name for display
        preg_match('/(?:CREATE|INSERT|ALTER)\s+(?:TABLE|INTO)?\s+(?:`)?(\w+)/i', $statement, $matches);
        $operationName = $matches[1] ?? "Statement " . ($i + 1);

        if ($mysqli->multi_query($statement)) {
            // Consume all results
            while ($mysqli->more_results()) {
                $mysqli->next_result();
            }
            echo "✅ [$operationName] Success\n";
            $successCount++;
        } else {
            echo "❌ [$operationName] Failed: " . $mysqli->error . "\n";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo str_repeat("-", 60) . "\n\n";

// Summary
echo "📊 Migration Complete:\n";
echo "   ✅ Successful: $successCount\n";
echo "   ❌ Failed: $errorCount\n";
echo "   ⏱️  Total: " . ($successCount + $errorCount) . "\n\n";

if ($errorCount > 0) {
    echo "⚠️  Some migrations failed. Check error messages above.\n";
    exit(1);
} else {
    echo "🎉 All migrations completed successfully!\n";

    // Display table info
    echo "\n📋 Created Tables:\n";
    $result = $mysqli->query("SHOW TABLES LIKE 'ai_%'");
    while ($row = $result->fetch_row()) {
        echo "   • {$row[0]}\n";
    }
}

$mysqli->close();
exit(0);
