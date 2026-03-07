<?php
// ============================================================================
// /config/db.php
// Fully self-contained, safe for Production & Development
// All database errors are logged, not displayed
// ============================================================================

// ---------------------------------------------------------------------------
// Environment detection
// ---------------------------------------------------------------------------
$env = $_ENV + $_SERVER;
$APP_ENV = $env['APP_ENV'] ?? 'production';
$IS_DEV  = ($APP_ENV === 'development');

// ---------------------------------------------------------------------------
// Error handling (NO other file needed)
// ---------------------------------------------------------------------------
if ($IS_DEV) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

// Catch fatal errors
register_shutdown_function(function () use ($IS_DEV) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        error_log(print_r($error, true));
        if ($IS_DEV) {
            echo "<pre>FATAL ERROR:\n" . print_r($error, true) . "</pre>";
        } else {
            echo "An unexpected error occurred. Please try again later.";
        }
    }
});

// Catch uncaught exceptions
set_exception_handler(function ($e) use ($IS_DEV) {
    error_log($e);
    if ($IS_DEV) {
        echo "<pre>UNCAUGHT EXCEPTION:\n{$e}</pre>";
    } else {
        echo "An unexpected error occurred. Please try again later.";
    }
});

// ---------------------------------------------------------------------------
// Database configuration
// ---------------------------------------------------------------------------
$dbHost    = $env['DB_HOST']    ?? 'localhost';
$dbName    = $env['DB_NAME']    ?? '';
$dbUser    = $env['DB_USER']    ?? '';
$dbPass    = $env['DB_PASS']    ?? '';
$dbCharset = $env['DB_CHARSET'] ?? 'utf8mb4';

// Validate required config
if (!$dbName || !$dbUser) {
    error_log('Database configuration missing');
    die($IS_DEV ? 'Database config missing (DB_NAME / DB_USER)' : 'App error');
}

// ---------------------------------------------------------------------------
// Database connection with enhanced error logging
// ---------------------------------------------------------------------------
// Configure mysqli to throw exceptions instead of warnings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $mysqli->set_charset($dbCharset);
    
    // Log successful connection in development mode
    if ($IS_DEV && function_exists('logDebug')) {
        logDebug('Database connection successful', [
            'host' => $dbHost,
            'database' => $dbName,
            'charset' => $dbCharset
        ]);
    }
    
} catch (mysqli_sql_exception $e) {
    // Log the detailed database connection error
    $errorDetails = [
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage(),
        'host' => $dbHost,
        'database' => $dbName,
        'user' => $dbUser,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Log to error log file
    if (function_exists('logError')) {
        logError('Database connection failed', 'CRITICAL', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'data' => $errorDetails
        ]);
    } else {
        // Fallback if logError is not available
        error_log('[DB CONNECTION ERROR] ' . json_encode($errorDetails, JSON_PRETTY_PRINT));
    }
    
    // Show generic error message (no database details exposed)
    if ($IS_DEV) {
        die('Database connection failed. Check error logs for details.');
    } else {
        die('Unable to connect to database. Please try again later.');
    }
    
} catch (Throwable $e) {
    // Catch any other unexpected errors
    error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
    die($IS_DEV ? 'Database connection error. Check logs.' : 'Database connection failed.');
}

// ---------------------------------------------------------------------------
// Set custom error handler for all mysqli queries
// ---------------------------------------------------------------------------
// This will catch all database query errors and log them without displaying
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($IS_DEV) {
    // Check if this is a mysqli error
    if (strpos($errstr, 'mysqli') !== false || strpos($errfile, 'mysqli') !== false) {
        // Log the database error
        if (function_exists('logDatabase')) {
            // logDatabase() expects: (string $operation, string $query, float $executionTime, bool $success, array $context)
            logDatabase('ERROR', $errstr, 0.0, false, [
                'file' => $errfile,
                'line' => $errline,
                'error_number' => $errno
            ]);
        } else {
            error_log("[DB ERROR] $errstr in $errfile:$errline");
        }
        
        // Don't show the error on frontend
        if ($IS_DEV) {
            // In development, show generic message
            echo "<!-- Database error logged. Check error logs. -->";
        }
        
        return true; // Prevent default error handler
    }
    
    // For non-database errors, use default handler
    return false;
}, E_ALL);

// ---------------------------------------------------------------------------
// Safe SQL session settings
// ---------------------------------------------------------------------------
try {
    $mysqli->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} catch (mysqli_sql_exception $e) {
    // Log SQL mode error but don't crash
    if (function_exists('logError')) {
        logError('SQL mode configuration failed', 'WARNING', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'data' => [
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage()
            ]
        ]);
    } else {
        error_log('[SQL MODE ERROR] ' . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log('[SQL MODE ERROR] ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// AppSettings (optional but safe)
// ---------------------------------------------------------------------------
$appSettings = null;

$settingsFile = __DIR__ . '/../app/Models/AppSettings.php';
if (file_exists($settingsFile)) {
    require_once $settingsFile;
    try {
        $appSettings = new AppSettings($mysqli);
    } catch (mysqli_sql_exception $e) {
        // Log database error from AppSettings
        if (function_exists('logError')) {
            logError('AppSettings initialization failed', 'WARNING', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => [
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage()
                ]
            ]);
        } else {
            error_log('[APP SETTINGS ERROR] ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        error_log('[APP SETTINGS ERROR] ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Timezone handling (NO crash guaranteed)
// ---------------------------------------------------------------------------
$timezone = $env['APP_TIMEZONE'] ?? null;

if (!$timezone && $appSettings) {
    try {
        $timezone = $appSettings->get('timezone', 'Asia/Dhaka');
    } catch (mysqli_sql_exception $e) {
        // Log database error - using logError instead of logDatabase for settings fetch
        if (function_exists('logError')) {
            logError('Failed to get timezone setting from database', 'WARNING', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => [
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage()
                ]
            ]);
        } else {
            error_log('[TIMEZONE FETCH ERROR] ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        error_log('[TIMEZONE FETCH ERROR] ' . $e->getMessage());
    }
}

if (!$timezone || !@date_default_timezone_set($timezone)) {
    date_default_timezone_set('Asia/Dhaka');
    error_log("Invalid or missing timezone, fallback used");
}

// ---------------------------------------------------------------------------
// Mark system ready
// ---------------------------------------------------------------------------
define('DB_READY', true);

// Optional: Log that database is ready
if ($IS_DEV && function_exists('logDebug')) {
    logDebug('Database system initialized successfully');
}