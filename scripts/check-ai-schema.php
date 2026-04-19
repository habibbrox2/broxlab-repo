<?php

/**
 * Check AI Module Database Schema
 * Path: /scripts/check-ai-schema.php
 * 
 * Run: php scripts/check-ai-schema.php
 * 
 * Checks for AI module tables and creates missing ones
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

if (!$dbName || !$dbUser) {
    die("❌ Error: Database configuration missing\n");
}

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $mysqli->set_charset($dbCharset);
} catch (Exception $e) {
    die("❌ Connection failed: " . $e->getMessage() . "\n");
}

echo "📋 Checking AI Module Database Schema\n";
echo "Database: $dbName\n";
echo str_repeat("=", 60) . "\n\n";

// Check required tables
$requiredTables = [
    'ai_conversations' => 'User conversations',
    'ai_messages' => 'Conversation messages',
    'ai_usage_logs' => 'Usage tracking',
    'ai_command_registry' => 'Command registry',
    'ai_tool_registry' => 'Tool registry'
];

$missingTables = [];
$existingTables = [];

// Check which tables exist
$result = $mysqli->query("SHOW TABLES LIKE 'ai_%'");
$existingTableNames = [];
while ($row = $result->fetch_row()) {
    $existingTableNames[] = $row[0];
}

echo "✅ Existing AI Tables:\n";
foreach ($existingTableNames as $table) {
    if (isset($requiredTables[$table])) {
        echo "   ✓ $table - {$requiredTables[$table]}\n";
        $existingTables[$table] = true;
    }
}

echo "\n";

// Identify missing tables
foreach ($requiredTables as $table => $desc) {
    if (!isset($existingTables[$table])) {
        $missingTables[$table] = $desc;
    }
}

if (!empty($missingTables)) {
    echo "⚠️  Missing AI Tables:\n";
    foreach ($missingTables as $table => $desc) {
        echo "   ✗ $table - $desc\n";
    }
    echo "\n";
} else {
    echo "✓ All required tables exist!\n\n";
}

// Check ai_conversations structure
if (isset($existingTables['ai_conversations'])) {
    echo "📊 ai_conversations columns:\n";
    $result = $mysqli->query("DESCRIBE ai_conversations");
    while ($row = $result->fetch_assoc()) {
        echo "   • {$row['Field']} ({$row['Type']})\n";
    }
    echo "\n";
}

// Check ai_messages structure
if (isset($existingTables['ai_messages'])) {
    echo "📊 ai_messages columns:\n";
    $result = $mysqli->query("DESCRIBE ai_messages");
    while ($row = $result->fetch_assoc()) {
        echo "   • {$row['Field']} ({$row['Type']})\n";
    }
    echo "\n";
}

// Create missing registries if needed
if (isset($missingTables['ai_command_registry']) || isset($missingTables['ai_tool_registry'])) {
    echo "🔧 Creating missing registry tables...\n";

    if (isset($missingTables['ai_command_registry'])) {
        $sql = "
        CREATE TABLE IF NOT EXISTS `ai_command_registry` (
          `id` INT PRIMARY KEY AUTO_INCREMENT,
          `name` VARCHAR(100) NOT NULL UNIQUE,
          `description` TEXT,
          `category` VARCHAR(50),
          `type` ENUM('ai', 'tool', 'system') DEFAULT 'ai',
          `enabled` BOOLEAN DEFAULT TRUE,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          
          INDEX `idx_name` (`name`),
          INDEX `idx_category` (`category`),
          INDEX `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($mysqli->query($sql)) {
            echo "   ✅ Created ai_command_registry\n";
        } else {
            echo "   ❌ Failed: " . $mysqli->error . "\n";
        }
    }

    if (isset($missingTables['ai_tool_registry'])) {
        $sql = "
        CREATE TABLE IF NOT EXISTS `ai_tool_registry` (
          `id` INT PRIMARY KEY AUTO_INCREMENT,
          `name` VARCHAR(100) NOT NULL UNIQUE,
          `description` TEXT,
          `enabled` BOOLEAN DEFAULT TRUE,
          `timeout` INT DEFAULT 30,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          
          INDEX `idx_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        if ($mysqli->query($sql)) {
            echo "   ✅ Created ai_tool_registry\n";
        } else {
            echo "   ❌ Failed: " . $mysqli->error . "\n";
        }
    }
    echo "\n";
}

// Populate registries if empty
echo "🔄 Populating registries...\n";

// Check and populate commands
$result = $mysqli->query("SELECT COUNT(*) FROM ai_command_registry");
$row = $result->fetch_row();
if ($row[0] == 0) {
    echo "   Adding commands...\n";
    $commands = [
        ['summarize', 'Generate AI summary', 'Admin', 'ai'],
        ['analyze-logs', 'Analyze system logs', 'System', 'ai'],
        ['check-security', 'Security check', 'System', 'ai'],
        ['health-check', 'System health check', 'System', 'ai'],
        ['web-search', 'Search the web', 'Web', 'ai'],
        ['generate-report', 'Generate report', 'Admin', 'ai'],
        ['calculate', 'Math calculation', 'Tools', 'tool'],
        ['scrape', 'Web scraping', 'Web', 'tool'],
        ['search', 'Local search', 'Tools', 'tool'],
        ['extract-entities', 'Extract entities', 'Content', 'tool'],
        ['translate', 'Translate text', 'Tools', 'tool'],
    ];

    $stmt = $mysqli->prepare("INSERT INTO ai_command_registry (name, description, category, type) VALUES (?, ?, ?, ?)");
    foreach ($commands as $cmd) {
        $stmt->bind_param("ssss", ...$cmd);
        if ($stmt->execute()) {
            echo "      ✓ {$cmd[0]}\n";
        } else {
            echo "      ✗ {$cmd[0]}: " . $stmt->error . "\n";
        }
    }
    $stmt->close();
} else {
    echo "   Commands already populated (" . $row[0] . " records)\n";
}

// Check and populate tools
$result = $mysqli->query("SELECT COUNT(*) FROM ai_tool_registry");
$row = $result->fetch_row();
if ($row[0] == 0) {
    echo "   Adding tools...\n";
    $tools = [
        ['calculate', 'Safe mathematical expression evaluation', 10],
        ['scrape', 'Web page scraping with HTML parsing', 30],
        ['search', 'Local content search', 10],
        ['extract-entities', 'Extract emails, URLs, mentions, hashtags', 5],
        ['translate', 'Text translation service', 20],
    ];

    $stmt = $mysqli->prepare("INSERT INTO ai_tool_registry (name, description, timeout) VALUES (?, ?, ?)");
    foreach ($tools as $tool) {
        $stmt->bind_param("ssi", ...$tool);
        if ($stmt->execute()) {
            echo "      ✓ {$tool[0]}\n";
        } else {
            echo "      ✗ {$tool[0]}: " . $stmt->error . "\n";
        }
    }
    $stmt->close();
} else {
    echo "   Tools already populated (" . $row[0] . " records)\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Schema check complete!\n\n";

// Summary
echo "📊 Final Status:\n";
$result = $mysqli->query("SHOW TABLES LIKE 'ai_%'");
$count = $result->num_rows;
echo "   Total AI tables: $count\n";

$result = $mysqli->query("SELECT COUNT(*) FROM ai_command_registry");
$row = $result->fetch_row();
echo "   Commands registered: {$row[0]}\n";

$result = $mysqli->query("SELECT COUNT(*) FROM ai_tool_registry");
$row = $result->fetch_row();
echo "   Tools registered: {$row[0]}\n";

$result = $mysqli->query("SELECT COUNT(*) FROM ai_conversations");
$row = $result->fetch_row();
echo "   Conversations: {$row[0]}\n";

$result = $mysqli->query("SELECT COUNT(*) FROM ai_messages");
$row = $result->fetch_row();
echo "   Messages: {$row[0]}\n";

$result = $mysqli->query("SELECT COUNT(*) FROM ai_usage_logs");
$row = $result->fetch_row();
echo "   Usage logs: {$row[0]}\n";

$mysqli->close();
