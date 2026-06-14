<?php
/* Import all SQL files from Database directory */
require_once __DIR__ . '/../public_html/_db.php';

$databaseDir = dirname(__DIR__) . '/Database';
$files = glob($databaseDir . '/*.sql');

if (empty($files)) {
    echo "No SQL files found in $databaseDir\n";
    exit(0);
}

usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

echo "Found " . count($files) . " SQL files to import\n\n";

// Drop all existing tables first with a fresh connection
$conn = freshConn();
$conn->query('SET FOREIGN_KEY_CHECKS=0');
$tables = $conn->query("SHOW TABLES");
while ($row = $tables->fetch_array()) {
    $conn->query("DROP TABLE IF EXISTS `{$row[0]}`");
}
$conn->query('SET FOREIGN_KEY_CHECKS=1');
$conn->close();
echo "Dropped existing tables\n\n";

// Set global max_allowed_packet to 512MB (safe value)
try {
    $conn = freshConn();
    $conn->query("SET GLOBAL max_allowed_packet=536870912");
    $conn->close();
    echo "Increased max_allowed_packet to 512MB\n";
} catch (Throwable $e) {
    echo "Note: could not increase max_allowed_packet (may need SUPER privilege): " . $e->getMessage() . "\n";
}

$imported = 0;
$failed = [];
$retryFiles = [];

foreach ($files as $file) {
    $tableName = basename($file, '.sql');
    echo "Importing: $tableName (" . round(filesize($file)/1024/1024, 2) . " MB)... ";
    
    $result = importSingleFile($file);
    if ($result === true) {
        $imported++;
        echo "OK\n";
    } else {
        echo "FAILED: $result\n";
        $failed[] = $tableName;
        $retryFiles[] = $file;
    }
}

echo "\n=== First pass: $imported/".count($files)." tables imported ===\n";
if (!empty($failed)) {
    echo "Failed: " . implode(', ', $failed) . "\n\n";
    echo "=== Retrying failed files with fresh connections ===\n";
    
    $retryImported = 0;
    foreach ($retryFiles as $file) {
        $tableName = basename($file, '.sql');
        echo "Retrying: $tableName... ";
        
        $result = importSingleFile($file);
        if ($result === true) {
            $retryImported++;
            echo "OK\n";
        } else {
            echo "FAILED again: $result\n";
        }
    }
    
    $totalImported = $imported + $retryImported;
    echo "\nFinal: $totalImported/".count($files)." tables imported\n";
}

/**
 * Import a single SQL file using statement-by-statement processing.
 * Each call gets a fresh connection, avoiding stale cached connections.
 */
function importSingleFile($file) {
    $mysqli = freshConn();
    
    try {
        $mysqli->query('SET autocommit=0');
        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $mysqli->query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO,ALLOW_INVALID_DATES'");
        $mysqli->query("SET SESSION net_read_timeout=600");
        $mysqli->query("SET SESSION net_write_timeout=600");
        $mysqli->begin_transaction();
        
        $handle = fopen($file, 'r');
        if (!$handle) {
            $mysqli->close();
            return 'Cannot open file';
        }
        
        $current_query = '';
        $statement_count = 0;
        
        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            
            if ($current_query === '' && (empty($trimmed) || strpos($trimmed, '--') === 0)) {
                continue;
            }
            
            $current_query .= $line;
            
            if (substr(rtrim($trimmed), -1) === ';') {
                $q = trim($current_query);
                $current_query = '';
                if (empty($q)) continue;
                
                try {
                    $mysqli->query($q);
                    $statement_count++;
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 2006 || $e->getCode() === 2013) {
                        // MySQL gone away - reconnect and retry once
                        $mysqli->close();
                        $mysqli = freshConn();
                        $mysqli->query('SET autocommit=0');
                        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
                        $mysqli->query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO,ALLOW_INVALID_DATES'");
                        $mysqli->query("SET SESSION net_read_timeout=600");
                        $mysqli->query("SET SESSION net_write_timeout=600");
                        $mysqli->begin_transaction();
                        $mysqli->query($q);
                        $statement_count++;
                    } else {
                        // Other MySQL error - skip this single statement and continue
                        fwrite(STDERR, "  [WARN] Statement #$statement_count failed: " . $e->getMessage() . "\n");
                        continue;
                    }
                }
            }
        }
        
        fclose($handle);
        
        $mysqli->commit();
        $mysqli->query('SET autocommit=1');
        $mysqli->close();
        return true;
        
    } catch (Throwable $e) {
        try { @$mysqli->rollback(); } catch (Throwable $ignored) {}
        try { @$mysqli->close(); } catch (Throwable $ignored) {}
        return $e->getMessage();
    }
}

/**
 * Create a completely fresh database connection, bypassing db()'s static cache.
 */
function freshConn() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}