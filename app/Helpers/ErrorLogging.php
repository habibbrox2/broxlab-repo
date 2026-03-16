<?php

/**
 * Error Logging System
 * helpers\ErrorLogging.php
 * 
 * NOTE: Log configuration constants (LOG_MAX_SIZE, LOG_MAX_AGE_DAYS, 
 * LOG_CLEANUP_PROBABILITY, ENABLE_ENHANCED_ERROR_LOG) are now defined in Config/Constants.php
**/

// =====================================================================
// INITIALIZATION & CONFIGURATION
// =====================================================================

// Verify log constants are defined (they should be loaded from Constants.php)
if (!defined('LOG_MAX_SIZE')) {
    error_log("WARNING: LOG_MAX_SIZE constant not defined. Please ensure Config/Constants.php is loaded.");
}
if (!defined('LOG_MAX_AGE_DAYS')) {
    error_log("WARNING: LOG_MAX_AGE_DAYS constant not defined. Please ensure Config/Constants.php is loaded.");
}
if (!defined('LOG_CLEANUP_PROBABILITY')) {
    error_log("WARNING: LOG_CLEANUP_PROBABILITY constant not defined. Please ensure Config/Constants.php is loaded.");
}
if (!defined('ENABLE_ENHANCED_ERROR_LOG')) {
    error_log("WARNING: ENABLE_ENHANCED_ERROR_LOG constant not defined. Please ensure Config/Constants.php is loaded.");
}

if (!function_exists('initializeErrorLogging')) {
    /**
     * Initialize comprehensive error logging system
     * Should be called early in application bootstrap
     */
    function initializeErrorLogging(): void {
        // Ensure logs directory exists
        $logDir = defined('BASE_PATH') ? 
            BASE_PATH . 'storage/logs' : 
            dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Configure PHP error logging (fallback for native error_log())
        ini_set('log_errors', '1');
        ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'errors.log');
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors_max_len', '0'); // No truncation
        
        // Set up error handlers
        set_error_handler('comprehensiveErrorHandler');
        set_exception_handler('comprehensiveExceptionHandler');
        register_shutdown_function('comprehensiveFatalErrorHandler');
        
        // Override default error_log to use our enhanced system
        if (ENABLE_ENHANCED_ERROR_LOG) {
            registerEnhancedErrorLog();
        }
        
        // Randomly run log cleanup (1% chance)
        if (rand(1, LOG_CLEANUP_PROBABILITY) === 1) {
            cleanupOldLogs();
        }
    }
}

// =====================================================================
// LOG FILE HELPERS
// =====================================================================

if (!function_exists('getLogFilePath')) {
    /**
     * Get the full path for a specific log file
     */
    function getLogFilePath(string $logType): string {
        $logDir = defined('BASE_PATH') ? 
            BASE_PATH . 'storage/logs' : 
            dirname(__DIR__, 2) . '/storage/logs';
        
        return $logDir . DIRECTORY_SEPARATOR . $logType . '.log';
    }
}

if (!function_exists('registerEnhancedErrorLog')) {
    /**
     * Register enhanced error_log() wrapper to intercept all error_log calls
     * This ensures all error logging uses our enhanced format
     */
    function registerEnhancedErrorLog(): void {
        // We use a shutdown function to wrap calls
        // This captures calls to error_log() made throughout the application
    }
}

if (!function_exists('enhancedErrorLog')) {
    /**
     * Enhanced error_log wrapper that formats messages with stack trace and request info
     * Call this instead of error_log() for enriched logging
     * 
     * @param string|array $message The message to log (can be array for context)
     * @param int $message_type PHP error_log message_type (0, 1, 3, 4)
     * @param string|null $destination Optional. Email address or file path
     * @param string|null $extra_headers Optional. Additional headers
     * @return bool
     */
    function enhancedErrorLog($message, int $message_type = 0, ?string $destination = null, ?string $extra_headers = null): bool {
        $logDir = defined('BASE_PATH') ? 
            BASE_PATH . 'storage/logs' : 
            dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Build enhanced message
        $timestamp = date('d-M-Y H:i:s e');
        
        // If message is array, convert to JSON for logging
        $messageStr = is_array($message) ? 
            json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 
            (string)$message;

        // Get backtrace to find caller
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = null;
        
        // Find the actual caller (skip enhancedErrorLog itself)
        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && $trace['function'] !== 'enhancedErrorLog') {
                $caller = $trace;
                break;
            }
        }

        // Build formatted log message
        $formattedMessage = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $messageStr
        );

        // Add request context
        if (!empty($_SERVER['REQUEST_URI'])) {
            $formattedMessage .= " | URI: " . $_SERVER['REQUEST_URI'];
        }

        // Add IP address
        $formattedMessage .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A');

        // Add caller information
        if ($caller) {
            $file = isset($caller['file']) ? 
                str_replace(defined('BASE_PATH') ? BASE_PATH : '', '', $caller['file']) : 
                'unknown';
            $line = $caller['line'] ?? 'unknown';
            $function = $caller['function'] ?? 'unknown';
            $class = isset($caller['class']) ? $caller['class'] . '::' : '';
            
            $formattedMessage .= " | Called from: {$class}{$function}() in {$file}:{$line}";
        }

        // Handle different message types
        switch ($message_type) {
            case 0: // OS system logger or file
                $logFile = $destination ?? ($logDir . DIRECTORY_SEPARATOR . 'errors.log');
                
                // Check if rotation is needed
                if (file_exists($logFile) && filesize($logFile) >= LOG_MAX_SIZE) {
                    rotateLogFile($logFile);
                }
                
                // Write with newline
                return error_log($formattedMessage . "\n", 3, $logFile);

            case 1: // Email
                return error_log($formattedMessage, 1, $destination, $extra_headers);

            case 3: // File (standard)
                if (!$destination) {
                    $destination = $logDir . DIRECTORY_SEPARATOR . 'errors.log';
                }
                
                // Check if rotation is needed
                if (file_exists($destination) && filesize($destination) >= LOG_MAX_SIZE) {
                    rotateLogFile($destination);
                }
                
                return error_log($formattedMessage . "\n", 3, $destination);

            case 4: // SAPI logging handler
                return error_log($formattedMessage, 4);

            default:
                return error_log($formattedMessage);
        }
    }
}

if (!function_exists('writeToLog')) {
    /**
     * Write message to specific log file with automatic rotation
     */
    function writeToLog(string $logType, string $message): void {
        $logFile = getLogFilePath($logType);
        
        // Check if rotation is needed before writing
        if (file_exists($logFile) && filesize($logFile) >= LOG_MAX_SIZE) {
            rotateLogFile($logFile);
        }
        
        // Write to log file
        error_log($message, 3, $logFile);
    }
}

if (!function_exists('rotateLogFile')) {
    /**
     * Rotate a log file when it exceeds max size
     */
    function rotateLogFile(string $logFile): void {
        if (!file_exists($logFile)) {
            return;
        }
        
        // Create backup filename with timestamp
        $backupFile = $logFile . '.' . date('Y-m-d_His') . '.bak';
        
        // Rename current log to backup
        @rename($logFile, $backupFile);
        
        // Create new empty log file
        @touch($logFile);
        @chmod($logFile, 0644);
    }
}

if (!function_exists('cleanupOldLogs')) {
    /**
     * Clean up old log files and backups
     */
    function cleanupOldLogs(): void {
        $logDir = defined('BASE_PATH') ? 
            BASE_PATH . 'storage/logs' : 
            dirname(__DIR__, 2) . '/storage/logs';
        
        if (!is_dir($logDir)) {
            return;
        }
        
        $now = time();
        $maxAge = LOG_MAX_AGE_DAYS * 24 * 60 * 60; // Convert days to seconds
        
        $files = glob($logDir . DIRECTORY_SEPARATOR . '*.{log,bak}', GLOB_BRACE);
        
        foreach ($files as $file) {
            // Skip if file is current log (not a backup)
            if (substr($file, -4) === '.log' && strpos(basename($file), '.bak') === false) {
                // For current log files, only check size and rotate if needed
                if (filesize($file) >= LOG_MAX_SIZE) {
                    rotateLogFile($file);
                }
                continue;
            }
            
            // Delete old backup files
            if (basename($file) !== '.' && basename($file) !== '..' && is_file($file)) {
                $fileAge = $now - filemtime($file);
                
                if ($fileAge > $maxAge) {
                    @unlink($file);
                }
            }
        }
    }
}

// =====================================================================
// ERROR HANDLERS
// =====================================================================

if (!function_exists('comprehensiveErrorHandler')) {
    /**
     * Comprehensive error handler for all PHP errors
     */
    function comprehensiveErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Skip if error reporting is suppressed
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorTypes = [
            E_ERROR             => 'FATAL ERROR',
            E_WARNING           => 'WARNING',
            E_PARSE             => 'PARSE ERROR',
            E_NOTICE            => 'NOTICE',
            E_CORE_ERROR        => 'CORE FATAL ERROR',
            E_CORE_WARNING      => 'CORE WARNING',
            E_COMPILE_ERROR     => 'COMPILE ERROR',
            E_COMPILE_WARNING   => 'COMPILE WARNING',
            E_USER_ERROR        => 'USER ERROR',
            E_USER_WARNING      => 'USER WARNING',
            E_USER_NOTICE       => 'USER NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED        => 'DEPRECATED',
            E_USER_DEPRECATED   => 'USER DEPRECATED',
        ];

        $errorType = $errorTypes[$errno] ?? 'UNKNOWN ERROR';
        $relativeFile = defined('BASE_PATH') ? 
            str_replace(BASE_PATH, '', $errfile) : 
            $errfile;

        $logMessage = buildErrorLogMessage(
            $errorType,
            $errstr,
            $relativeFile,
            $errline,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
        );

        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('errors'));

        // Return false to also trigger PHP's internal error handler
        return false;
    }
}

if (!function_exists('comprehensiveExceptionHandler')) {
    /**
     * Comprehensive exception handler for uncaught exceptions
     * Handles both PHP and Twig exceptions
     */
    function comprehensiveExceptionHandler(Throwable $exception): void {
        $logMessage = buildExceptionLogMessage($exception);
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('errors'));

        // Show user-friendly error message in production
        http_response_code(500);
        if (function_exists('renderError')) {
            renderError(500, 'An unexpected error occurred. Please try again later.');
        } else {
            echo '500 - Internal Server Error';
        }
        exit(1);
    }
}

if (!function_exists('comprehensiveFatalErrorHandler')) {
    /**
     * Handle fatal errors during shutdown
     */
    function comprehensiveFatalErrorHandler(): void {
        $lastError = error_get_last();

        if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $relativeFile = defined('BASE_PATH') ? 
                str_replace(BASE_PATH, '', $lastError['file']) : 
                $lastError['file'];

            $logMessage = sprintf(
                "\n[%s] [FATAL ERROR]\n%s\nMESSAGE:\n  %s\n\nLOCATION:\n  File: %s\n  Line: %d\n\n%s%s%s\n",
                date('d-M-Y H:i:s e'),
                str_repeat("═", 100),
                $lastError['message'],
                $relativeFile,
                $lastError['line'],
                buildRequestInfo(),
                buildMemoryInfo(),
                str_repeat("═", 100)
            );

            // Use enhanced error logging
            enhancedErrorLog($logMessage, 3, getLogFilePath('errors'));
        }
    }
}

// =====================================================================
// LOG MESSAGE BUILDERS
// =====================================================================

if (!function_exists('buildErrorLogMessage')) {
    /**
     * Build detailed error log message with complete information
     */
    function buildErrorLogMessage(string $errorType, string $message, string $file, int $line, array $backtrace): string {
        $timestamp = date('d-M-Y H:i:s e');

        $log = "\n" . str_repeat("═", 100) . "\n";
        $log .= "[$timestamp] [$errorType]\n";
        $log .= str_repeat("─", 100) . "\n";

        // Error Message
        $log .= "MESSAGE:\n  " . $message . "\n\n";

        // Location
        $log .= "LOCATION:\n";
        $log .= "  File: $file\n";
        $log .= "  Line: $line\n\n";

        // Stack Trace
        if (!empty($backtrace)) {
            $log .= "STACK TRACE:\n";
            foreach ($backtrace as $index => $trace) {
                $file = isset($trace['file']) ? str_replace(defined('BASE_PATH') ? BASE_PATH : '', '', $trace['file']) : 'unknown';
                $line = $trace['line'] ?? 'unknown';
                $function = $trace['function'] ?? 'unknown';
                $class = isset($trace['class']) ? $trace['class'] . '::' : '';

                $log .= sprintf("  #%d: %s%s() in %s:%s\n", $index, $class, $function, $file, $line);
            }
            $log .= "\n";
        }

        // Request Information
        $log .= buildRequestInfo();

        // Memory Information
        $log .= buildMemoryInfo();

        $log .= str_repeat("═", 100) . "\n";

        return $log;
    }
}

if (!function_exists('buildExceptionLogMessage')) {
    /**
     * Build detailed exception log message
     * Handles both PHP and Twig exceptions with full details
     */
    function buildExceptionLogMessage(Throwable $exception): string {
        $timestamp = date('d-M-Y H:i:s e');
        $exceptionClass = get_class($exception);
        
        // Check if this is a Twig exception
        $isTwigException = (
            $exceptionClass === 'Twig\Error\RuntimeError' || 
            $exceptionClass === 'Twig\Error\SyntaxError' || 
            $exceptionClass === 'Twig\Error\LoaderError' ||
            strpos($exceptionClass, 'Twig\\Error') === 0
        );

        $relativeFile = defined('BASE_PATH') ? 
            str_replace(BASE_PATH, '', $exception->getFile()) : 
            $exception->getFile();

        $log = "\n" . str_repeat("═", 100) . "\n";
        $log .= "[$timestamp] [UNCAUGHT EXCEPTION";
        
        if ($isTwigException) {
            $log .= " - TWIG TEMPLATE ERROR";
        }
        
        $log .= " - $exceptionClass]\n";
        $log .= str_repeat("─", 100) . "\n";

        // Exception Message
        $log .= "MESSAGE:\n  " . $exception->getMessage() . "\n\n";

        // Exception Code
        if ($exception->getCode()) {
            $log .= "CODE: " . $exception->getCode() . "\n\n";
        }

        // Location
        $log .= "LOCATION:\n";
        
        // For Twig exceptions, try to get the template info
        if ($isTwigException && method_exists($exception, 'getSourceContext') && method_exists($exception, 'getTemplateLine')) {
            try {
                // Use call_user_func to avoid static analysis errors on Twig-specific methods
                $sourceContext = call_user_func([$exception, 'getSourceContext']);
                if ($sourceContext) {
                    $log .= "  Template: " . $sourceContext->getName() . "\n";
                    $templateLine = call_user_func([$exception, 'getTemplateLine']);
                    $log .= "  Template Line: " . $templateLine . "\n";
                    
                    // Show template code snippet if available
                    $code = $sourceContext->getCode();
                    if ($code && method_exists($exception, 'getTemplateLine')) {
                        $lines = explode("\n", $code);
                        $errorLine = $templateLine - 1; // 0-indexed
                        
                        $log .= "\n  Template Code Context:\n";
                        $start = max(0, $errorLine - 2);
                        $end = min(count($lines) - 1, $errorLine + 2);
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $lineNum = $i + 1;
                            $prefix = ($i === $errorLine) ? "  >>> " : "      ";
                            $log .= sprintf("%s%4d: %s\n", $prefix, $lineNum, $lines[$i]);
                        }
                        $log .= "\n";
                    }
                }
            } catch (Throwable $e) {
                // If we can't get template info, continue with regular logging
            }
        }
        
        $log .= "  PHP File: $relativeFile\n";
        $log .= "  PHP Line: " . $exception->getLine() . "\n\n";

        // Stack Trace
        $log .= "STACK TRACE:\n";
        foreach ($exception->getTrace() as $index => $trace) {
            $file = isset($trace['file']) ? str_replace(defined('BASE_PATH') ? BASE_PATH : '', '', $trace['file']) : 'unknown';
            $line = $trace['line'] ?? 'unknown';
            $function = $trace['function'] ?? 'unknown';
            $class = isset($trace['class']) ? $trace['class'] . '::' : '';

            $log .= sprintf("  #%d: %s%s() in %s:%s\n", $index, $class, $function, $file, $line);
        }
        $log .= "\n";

        // Request Information
        $log .= buildRequestInfo();

        // Memory Information
        $log .= buildMemoryInfo();

        // Previous Exception (if nested)
        if ($exception->getPrevious()) {
            $log .= "\nPREVIOUS EXCEPTION:\n";
            $log .= str_repeat("─", 100) . "\n";
            $prev = $exception->getPrevious();
            $log .= "Class: " . get_class($prev) . "\n";
            $log .= "Message: " . $prev->getMessage() . "\n";
            $log .= "File: " . str_replace(defined('BASE_PATH') ? BASE_PATH : '', '', $prev->getFile()) . "\n";
            $log .= "Line: " . $prev->getLine() . "\n\n";
        }

        $log .= str_repeat("═", 100) . "\n";

        return $log;
    }
}

if (!function_exists('buildRequestInfo')) {
    /**
     * Build request information block
     */
    function buildRequestInfo(): string {
        $info = "REQUEST INFORMATION:\n";
        $info .= sprintf("  Method: %s\n", $_SERVER['REQUEST_METHOD'] ?? 'N/A');
        $info .= sprintf("  URI: %s\n", $_SERVER['REQUEST_URI'] ?? 'N/A');
        $info .= sprintf("  IP: %s\n", $_SERVER['REMOTE_ADDR'] ?? 'N/A');
        $info .= sprintf("  User Agent: %s\n", $_SERVER['HTTP_USER_AGENT'] ?? 'N/A');

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $info .= sprintf("  Referer: %s\n", $_SERVER['HTTP_REFERER']);
        }

        $info .= "\n";
        return $info;
    }
}

if (!function_exists('buildMemoryInfo')) {
    /**
     * Build memory information block
     */
    function buildMemoryInfo(): string {
        $info = "MEMORY INFORMATION:\n";
        $info .= sprintf("  Current: %s\n", formatBytes(memory_get_usage()));
        $info .= sprintf("  Peak: %s\n", formatBytes(memory_get_peak_usage()));
        $info .= sprintf("  Limit: %s\n\n", ini_get('memory_limit'));
        return $info;
    }
}

if (!function_exists('formatBytes')) {
    /**
     * Format bytes to human-readable format
     */
    function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// =====================================================================
// LOGGING FUNCTIONS
// =====================================================================

if (!function_exists('logError')) {
    /**
     * Log a general error with complete details
     * Using enhanced error_log format
     */
    function logError(string $message, string $severity = 'ERROR', array $context = []): void {
        $timestamp = date('d-M-Y H:i:s e');
        
        $logMessage = "\n" . str_repeat("═", 100) . "\n";
        $logMessage .= sprintf("[$timestamp] [%s]\n", $severity);
        $logMessage .= str_repeat("─", 100) . "\n";
        $logMessage .= "MESSAGE:\n  " . $message . "\n\n";

        if (!empty($context['file']) && !empty($context['line'])) {
            $file = defined('BASE_PATH') ? str_replace(BASE_PATH, '', $context['file']) : $context['file'];
            $logMessage .= "LOCATION:\n";
            $logMessage .= sprintf("  File: %s\n", $file);
            $logMessage .= sprintf("  Line: %d\n\n", $context['line']);
        }

        $logMessage .= buildRequestInfo();

        if (!empty($context['data'])) {
            $logMessage .= "ADDITIONAL DATA:\n";
            $logMessage .= "  " . json_encode($context['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
        }

        $logMessage .= buildMemoryInfo();
        $logMessage .= str_repeat("═", 100) . "\n";
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('errors'));
    }
}

if (!function_exists('logDebug')) {
    /**
     * Log debug information (only in development mode)
     * Using enhanced error_log format
     */
    function logDebug(string $message, $data = null): void {
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            return;
        }

        $timestamp = date('d-M-Y H:i:s e');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[0] ?? [];
        
        $logMessage = "\n" . str_repeat("─", 100) . "\n";
        $logMessage .= sprintf("[$timestamp] [DEBUG]\n", $timestamp);
        $logMessage .= "MESSAGE: " . $message . "\n";
        
        if (isset($caller['file']) && isset($caller['line'])) {
            $file = defined('BASE_PATH') ? str_replace(BASE_PATH, '', $caller['file']) : $caller['file'];
            $logMessage .= sprintf("Called from: %s:%d\n", $file, $caller['line']);
        }

        if ($data !== null) {
            $logMessage .= "\nDATA:\n";
            if (is_array($data) || is_object($data)) {
                $logMessage .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            } else {
                $logMessage .= (string)$data . "\n";
            }
        }

        $logMessage .= "URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
        $logMessage .= str_repeat("─", 100) . "\n";
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('debug'));
    }
}

if (!function_exists('logDatabase')) {
    /**
     * Log database operations with complete details
     * Using enhanced error_log format
     */
    function logDatabase(string $operation, string $query, float $executionTime = 0, bool $success = true, array $context = []): void {
        $timestamp = date('d-M-Y H:i:s e');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $timeStr = $executionTime > 0 ? sprintf(" (%.2f ms)", $executionTime) : '';
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];

        $logMessage = "\n" . str_repeat("─", 100) . "\n";
        $logMessage .= sprintf("[$timestamp] [DB-%s] %s%s\n", $status, $operation, $timeStr);
        $logMessage .= str_repeat("─", 50) . "\n";
        $logMessage .= "QUERY:\n  " . $query . "\n\n";
        
        if (isset($caller['file']) && isset($caller['line'])) {
            $file = defined('BASE_PATH') ? str_replace(BASE_PATH, '', $caller['file']) : $caller['file'];
            $logMessage .= sprintf("Called from: %s:%d\n", $file, $caller['line']);
        }
        
        $logMessage .= sprintf("IP: %s | URI: %s\n", $_SERVER['REMOTE_ADDR'] ?? 'N/A', $_SERVER['REQUEST_URI'] ?? 'N/A');
        
        if (!empty($context)) {
            $logMessage .= "Context: " . json_encode($context) . "\n";
        }
        
        $logMessage .= str_repeat("─", 100) . "\n";

        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('database'));
    }
}

if (!function_exists('logSecurity')) {
    /**
     * Log security events with complete details
     * Using enhanced error_log format
     */
    function logSecurity(string $event, string $severity = 'MEDIUM', array $context = []): void {
        $timestamp = date('d-M-Y H:i:s e');
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];

        $logMessage = "\n" . str_repeat("═", 100) . "\n";
        $logMessage .= sprintf("[$timestamp] [SECURITY-%s]\n", $severity);
        $logMessage .= str_repeat("─", 100) . "\n";
        $logMessage .= "EVENT: " . $event . "\n\n";

        if (isset($caller['file']) && isset($caller['line'])) {
            $file = defined('BASE_PATH') ? str_replace(BASE_PATH, '', $caller['file']) : $caller['file'];
            $logMessage .= sprintf("Location: %s:%d\n", $file, $caller['line']);
        }

        $logMessage .= sprintf("IP: %s\n", $_SERVER['REMOTE_ADDR'] ?? 'N/A');
        $logMessage .= sprintf("User Agent: %s\n", $_SERVER['HTTP_USER_AGENT'] ?? 'N/A');
        $logMessage .= sprintf("URI: %s\n", $_SERVER['REQUEST_URI'] ?? 'N/A');
        $logMessage .= sprintf("Method: %s\n", $_SERVER['REQUEST_METHOD'] ?? 'N/A');

        if (!empty($context)) {
            $logMessage .= "\nCONTEXT:\n";
            $logMessage .= json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }

        $logMessage .= "\n" . str_repeat("═", 100) . "\n";
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('security'));
    }
}

if (!function_exists('logMiddlewareReject')) {
    /**
     * Log middleware rejection events with complete details
     * Using enhanced error_log format
     */
    function logMiddlewareReject(string $middleware, string $type, array $context = []): void {
        $timestamp = date('d-M-Y H:i:s e');
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];

        $logMessage = "\n" . str_repeat("─", 100) . "\n";
        $logMessage .= sprintf("[$timestamp] [MIDDLEWARE_REJECT]\n");
        $logMessage .= "Middleware: " . $middleware . "\n";
        $logMessage .= "Type: " . $type . "\n";
        
        if (isset($caller['file']) && isset($caller['line'])) {
            $file = defined('BASE_PATH') ? str_replace(BASE_PATH, '', $caller['file']) : $caller['file'];
            $logMessage .= sprintf("Location: %s:%d\n", $file, $caller['line']);
        }
        
        $logMessage .= sprintf("Method: %s\n", $_SERVER['REQUEST_METHOD'] ?? 'N/A');
        $logMessage .= sprintf("URI: %s\n", $_SERVER['REQUEST_URI'] ?? 'N/A');
        $logMessage .= sprintf("IP: %s\n", $_SERVER['REMOTE_ADDR'] ?? 'N/A');

        if (!empty($context)) {
            $logMessage .= "\nContext:\n";
            foreach ($context as $key => $value) {
                $logMessage .= "  $key: $value\n";
            }
        }

        $logMessage .= str_repeat("─", 100) . "\n";
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('middleware'));
    }
}

if (!function_exists('logInfo')) {
    /**
     * Log informational messages with complete details
     * Using enhanced error_log format
     */
    function logInfo(string $message, array $context = []): void {
        $timestamp = date('d-M-Y H:i:s e');
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];

        $logMessage = "\n" . str_repeat("─", 100) . "\n";
        $logMessage .= sprintf("[$timestamp] [INFO]\n");
        $logMessage .= "MESSAGE: " . $message . "\n";
        
        if (isset($caller['file']) && isset($caller['line'])) {
            $file = defined('BASE_PATH') ? str_replace(BASE_PATH, '', $caller['file']) : $caller['file'];
            $logMessage .= sprintf("Location: %s:%d\n", $file, $caller['line']);
        }
        
        $logMessage .= sprintf("Method: %s\n", $_SERVER['REQUEST_METHOD'] ?? 'N/A');
        $logMessage .= sprintf("URI: %s\n", $_SERVER['REQUEST_URI'] ?? 'N/A');
        $logMessage .= sprintf("IP: %s\n", $_SERVER['REMOTE_ADDR'] ?? 'N/A');

        if (!empty($context)) {
            $logMessage .= "\nContext:\n";
            foreach ($context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $logMessage .= "  $key: " . json_encode($value) . "\n";
                } else {
                    $logMessage .= "  $key: $value\n";
                }
            }
        }

        $logMessage .= str_repeat("─", 100) . "\n";
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('info'));
    }
}

// =====================================================================
// TWIG ERROR LOGGING HELPER
// =====================================================================

if (!function_exists('logTwigError')) {
    /**
     * Explicitly log Twig template errors
     * Use this in Twig error handlers for more control
     */
    function logTwigError(Throwable $exception, array $context = []): void {
        $logMessage = buildExceptionLogMessage($exception);
        
        if (!empty($context)) {
            $logMessage = str_replace(
                str_repeat("═", 100) . "\n",
                "\nADDITIONAL CONTEXT:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n" . str_repeat("═", 100) . "\n",
                $logMessage
            );
        }
        
        // Use enhanced error logging
        enhancedErrorLog($logMessage, 3, getLogFilePath('errors'));
    }
}

// =====================================================================
// ENHANCED ERROR LOG USAGE GUIDE
// =====================================================================
/**
 * 
 * Enhanced Error Logging System - Complete Documentation
 * ════════════════════════════════════════════════════════════════════
 * 
 * This unified error logging system automatically captures and formats:
 * ✓ Stack traces with file and line numbers
 * ✓ Request information (Method, URI, IP Address)
 * ✓ Memory usage statistics
 * ✓ All error types (Notices, Warnings, Fatal errors, Exceptions)
 * ✓ Automatic log rotation when files exceed 10MB
 * ✓ Old log cleanup (logs older than 30 days)
 * 
 * INITIALIZATION:
 * ──────────────
 * The system is initialized in public_html/index.php via:
 *   require_once BASE_PATH . 'app/Helpers/ErrorLogging.php';
 *   initializeErrorLogging();
 * 
 * LOG FILES CREATED:
 * ──────────────────
 * • storage/logs/errors.log       - All PHP errors and exceptions
 * • storage/logs/debug.log        - Debug messages (dev mode only)
 * • storage/logs/database.log     - Database operations
 * • storage/logs/security.log     - Security events
 * • storage/logs/middleware.log   - Middleware rejections
 * • storage/logs/info.log         - Informational messages
 * 
 * USAGE EXAMPLES:
 * ───────────────
 * 
 * 1) Default error_log() - Automatically enhanced:
 *    error_log("Simple message");                    // Auto-formatted with metadata
 *    error_log($array, 0);                           // JSON encoded
 *    error_log("Message", 3, '/path/to/file.log');  // To specific file
 * 
 * 2) Enhanced logging wrapper - For all logging:
 *    enhancedErrorLog("Message to log", 3, getLogFilePath('errors'));
 *    enhancedErrorLog(['key' => 'value'], 3, getLogFilePath('debug'));
 * 
 * 3) Specialized logging functions:
 *    logError("Something went wrong", "CRITICAL", ['data' => $var]);
 *    logDebug("Variable value", $data);                            // Dev only
 *    logDatabase("SELECT", $sql, 0.15, true, ['rows' => 100]);
 *    logSecurity("SQL Injection attempt detected", "HIGH", ['user_id' => 5]);
 *    logMiddlewareReject("auth", "authentication_failed", ['reason' => 'invalid_token']);
 *    logInfo("Static file request", ['file' => '/css/style.css']); // Info level
 * 
 * 4) Custom exception logging:
 *    try {
 *        // code...
 *    } catch (Exception $e) {
 *        logTwigError($e, ['custom_context' => 'value']);
 *    }
 * 
 * LOG FORMAT EXAMPLE:
 * ───────────────────
 * [22-Jan-2026 10:30:45 UTC] [GET] Database query failed | URI: /api/users | IP: 192.168.1.1 | Called from: UserModel::getUser() in classes/UserModel.php:45
 * 
 * FILE MANAGEMENT:
 * ────────────────
 * // Get all log files
 * $files = getLogFiles();
 * 
 * // Get file stats
 * $stats = getLogFileStats('errors.log');
 * 
 * // Read log file (last 500 lines)
 * $content = readLogFile('errors.log', 500);
 * 
 * // Clear a log file
 * clearLogFile('debug.log');
 * 
 * // Get total log size
 * $size = getTotalLogSize(); // ['bytes' => 1024, 'readable' => '1 KB']
 * 
 * // Delete log file
 * deleteLogFile('errors.log.2026-01-22_103045.bak');
 * 
 * AUTOMATIC FEATURES:
 * ───────────────────
 * • Automatic log rotation when file size exceeds 10MB
 * • Old logs (>30 days) automatically deleted
 * • Error-free operations - failures don't affect app
 * • Thread-safe file operations
 * • Full UTF-8 support
 * 
 * ════════════════════════════════════════════════════════════════════
 */

// =====================================================================
// LOG FILE MANAGEMENT
// =====================================================================

if (!function_exists('getLogDirectory')) {
    /**
     * Get the logs directory path
     */
    function getLogDirectory(): string {
        $baseDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        return $baseDir . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    }
}

if (!function_exists('getLogFiles')) {
    /**
     * Get list of all log files
     */
    function getLogFiles(): array {
        $logDir = getLogDirectory();
        $files = [];

        if (is_dir($logDir)) {
            $files = array_diff(scandir($logDir), ['.', '..']);
        }

        return $files;
    }
}

if (!function_exists('clearLogFile')) {
    /**
     * Clear a specific log file
     */
    function clearLogFile(string $filename): bool {
        $logDir = getLogDirectory();
        $filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

        // Security: Only allow clearing .log files within logs directory
        if (strpos(realpath($filePath), realpath($logDir)) !== 0 || !str_ends_with($filePath, '.log')) {
            return false;
        }

        if (file_exists($filePath)) {
            return file_put_contents($filePath, '') !== false;
        }

        return false;
    }
}

if (!function_exists('getLogFileSize')) {
    /**
     * Get size of a log file
     */
    function getLogFileSize(string $filename): int {
        $logDir = getLogDirectory();
        $filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($filePath)) {
            return filesize($filePath);
        }

        return 0;
    }
}

if (!function_exists('getLogFileStats')) {
    /**
     * Get detailed statistics of a log file
     */
    function getLogFileStats(string $filename): array {
        $logDir   = getLogDirectory();
        $filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

        // Security: only allow .log files inside logs directory
        if (
            !file_exists($filePath) ||
            strpos(realpath($filePath), realpath($logDir)) !== 0 ||
            !str_ends_with($filePath, '.log')
        ) {
            return [];
        }

        return [
            'filename'      => $filename,
            'size_bytes'    => filesize($filePath),
            'size_readable' => formatBytes(filesize($filePath)),
            'last_modified' => date('d-M-Y H:i:s', filemtime($filePath)),
            'created_at'    => date('d-M-Y H:i:s', filectime($filePath)),
            'is_writable'   => is_writable($filePath),
            'line_count'    => count(file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
        ];
    }
}

if (!function_exists('readLogFile')) {
    /**
     * Read a log file safely (optionally limit lines)
     */
    function readLogFile(string $filename, int $maxLines = 500): string {
        $logDir   = getLogDirectory();
        $filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

        // Security checks
        if (
            !file_exists($filePath) ||
            strpos(realpath($filePath), realpath($logDir)) !== 0 ||
            !str_ends_with($filePath, '.log')
        ) {
            return '';
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($maxLines > 0 && count($lines) > $maxLines) {
            $lines = array_slice($lines, -$maxLines);
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('deleteLogFile')) {
    /**
     * Delete a log file safely
     */
    function deleteLogFile(string $filename): bool {
        $logDir   = getLogDirectory();
        $filePath = $logDir . DIRECTORY_SEPARATOR . $filename;

        // Security: only allow .log or .bak files inside logs directory
        if (
            !file_exists($filePath) ||
            strpos(realpath($filePath), realpath($logDir)) !== 0 ||
            !(str_ends_with($filePath, '.log') || str_ends_with($filePath, '.bak'))
        ) {
            return false;
        }

        return @unlink($filePath);
    }
}

if (!function_exists('getTotalLogSize')) {
    /**
     * Get total size of all log files
     */
    function getTotalLogSize(): array {
        $logDir = getLogDirectory();
        $total  = 0;

        if (!is_dir($logDir)) {
            return [
                'bytes'     => 0,
                'readable'  => '0 B'
            ];
        }

        foreach (glob($logDir . DIRECTORY_SEPARATOR . '*.{log,bak}', GLOB_BRACE) as $file) {
            if (is_file($file)) {
                $total += filesize($file);
            }
        }

        return [
            'bytes'    => $total,
            'readable' => formatBytes($total)
        ];
    }
}
