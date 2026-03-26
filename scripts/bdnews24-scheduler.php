<?php

/**
 * bdnews24 Scheduler Script
 * CLI script to run the scraper at regular intervals
 * 
 * Usage:
 *   php bdnews24-scheduler.php [--continuous] [--interval=SECONDS] [--cycles=N]
 * 
 * Cron setup:
 *   * * * * * php /path/to/scripts/bdnews24-scheduler.php >> /var/log/scraper.log 2>&1
 */

declare(strict_types=1);

// Get script directory
$scriptDir = __DIR__;

// Base path (parent of scripts/)
$basePath = dirname($scriptDir);

// Change to base directory
chdir($basePath);

// CLI options
$options = getopt('', [
    'continuous',
    'interval::',
    'cycles::',
    'source::',
    'help'
]);

// Show help
if (isset($options['help'])) {
    echo "bdnews24 Scheduler Script\n";
    echo "========================\n\n";
    echo "Usage: php bdnews24-scheduler.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --continuous     Run continuously (default: run once)\n";
    echo "  --interval=SEC   Interval between cycles in seconds (default: 20)\n";
    echo "  --cycles=N        Maximum number of cycles (0 = infinite, default: 0)\n";
    echo "  --source=SOURCE   Source to scrape (default: bdnews24)\n";
    echo "  --help            Show this help message\n\n";
    echo "Examples:\n";
    echo "  php bdnews24-scheduler.php                    # Run once\n";
    echo "  php bdnews24-scheduler.php --continuous        # Run continuously\n";
    echo "  php bdnews24-scheduler.php --interval=30      # Run every 30 seconds\n";
    echo "  php bdnews24-scheduler.php --cycles=10        # Run 10 cycles\n";
    exit(0);
}

// Configuration
$config = [
    'source' => $options['source'] ?? 'bdnews24',
    'continuous' => isset($options['continuous']),
    'interval' => isset($options['interval']) ? (int)$options['interval'] : 20,
    'cycles' => isset($options['cycles']) ? (int)$options['cycles'] : 0,
    'log_file' => $basePath . '/storage/logs/scraper.log',
    'node_path' => findNodePath()
];

// Ensure log directory exists
$logDir = dirname($config['log_file']);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * Find Node.js executable path
 */
function findNodePath(): ?string
{
    // 1. Explicit environment variables
    $envNodeBinary = getenv('NODE_BINARY');
    if ($envNodeBinary !== false && trim($envNodeBinary) !== '' && isNodeExecutable(trim($envNodeBinary))) {
        return trim($envNodeBinary);
    }

    $envNodePath = getenv('NODE_PATH');
    if ($envNodePath !== false && trim($envNodePath) !== '' && isNodeExecutable(trim($envNodePath))) {
        return trim($envNodePath);
    }

    // 2. Path resolver
    $command = (stripos(PHP_OS_FAMILY, 'Windows') === 0) ? 'where node' : 'command -v node';
    $output = [];
    $exitCode = 1;
    @exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode === 0 && !empty($output)) {
        foreach ($output as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && isNodeExecutable($candidate)) {
                return $candidate;
            }
        }
    }

    // 3. Common locations
    $paths = [
        'C:\\Program Files\\nodejs\\node.exe',
        '/usr/local/bin/node',
        '/usr/bin/node',
        '/opt/nodejs/bin/node'
    ];

    foreach ($paths as $path) {
        if (isNodeExecutable($path)) {
            return $path;
        }
    }

    return null;
}

function isNodeExecutable(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows: executable if file exists and ends with node.exe
        if (stripos($path, 'node.exe') !== false && file_exists($path) && is_file($path)) {
            return true;
        }
        // fallback check by checking PATH name
        return ($path === 'node');
    }

    if ($path === 'node') {
        return true;
    }

    return file_exists($path) && is_file($path) && is_executable($path);
}

/**
 * Log message
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    global $config;

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

    // Console output
    echo $logMessage;

    // File output
    if ($config['log_file']) {
        file_put_contents($config['log_file'], $logMessage, FILE_APPEND);
    }
}

/**
 * Log activity to database
 */
function logActivity(string $message, string $status = 'success'): void
{
    global $basePath;

    // Include database connection if available
    $dbFile = $basePath . '/public_html/_db.php';

    if (file_exists($dbFile)) {
        try {
            // Only log to system activities - minimal impact
            $logFile = $basePath . '/storage/logs/scraper_activity.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[{$timestamp}] {$message} - {$status}\n", FILE_APPEND);
        } catch (Exception $e) {
            // Silently ignore logging errors
        }
    }
}

/**
 * Run the scraper
 */
function runScraper(array $config): array
{
    global $basePath;

    $nodePath = $config['node_path'];
    $scraperPath = $basePath . '/src/scraper/index.js';

    if (empty($nodePath)) {
        return [
            'success' => false,
            'return_code' => null,
            'error' => 'node_not_found',
            'output' => [
                'stdout' => '',
                'stderr' => 'Node.js executable not found. Set NODE_BINARY or NODE_PATH to a valid path.',
            ],
        ];
    }

    // Build command
    $cmd = escapeshellarg($nodePath) . ' ' . escapeshellarg($scraperPath);

    // Add options
    if ($config['continuous']) {
        $cmd .= ' --continuous';
    }

    if ($config['interval'] > 0) {
        $cmd .= ' --interval=' . $config['interval'];
    }

    if ($config['cycles'] > 0) {
        $cmd .= ' --cycles=' . $config['cycles'];
    }

    if (!empty($config['source'])) {
        $cmd .= ' --source=' . escapeshellarg($config['source']);
    }

    logMessage("Executing: {$cmd}");

    // Set timeout
    $timeout = 300; // 5 minutes max per run

    // Execute
    $output = [];
    $returnCode = 0;

    // Use proc_open for better control
    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];

    $process = proc_open($cmd, $descriptors, $pipes, $basePath);

    if (!is_resource($process)) {
        return [
            'success' => false,
            'error' => 'Failed to start Node.js process',
            'output' => []
        ];
    }

    // Set timeout
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $startTime = time();
    $stdout = '';
    $stderr = '';

    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        // Check if process ended
        $status = proc_get_status($process);

        if (!$status['running']) {
            $returnCode = $status['exitcode'];
            break;
        }

        // Check timeout
        if (time() - $startTime > $timeout) {
            proc_terminate($process);
            $returnCode = -1;
            $stderr .= "\nProcess terminated due to timeout";
            break;
        }

        usleep(100000); // 100ms
    }

    // Close pipes
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $output = [
        'stdout' => $stdout,
        'stderr' => $stderr,
        'return_code' => $returnCode
    ];

    return [
        'success' => $returnCode === 0,
        'return_code' => $returnCode,
        'output' => $output
    ];
}

// Main execution
logMessage("=== Starting bdnews24 Scheduler ===");
logMessage("Configuration: " . json_encode($config));

// Verify Node.js is available
if (empty($config['node_path'])) {
    logMessage('ERROR: Node.js binary path could not be resolved. Please set NODE_BINARY or NODE_PATH.', 'ERROR');
    exit(1);
}

$nodeCheck = @shell_exec(escapeshellarg($config['node_path']) . ' --version 2>&1');
if (!$nodeCheck || stripos($nodeCheck, 'command not found') !== false || stripos($nodeCheck, 'not recognized') !== false) {
    logMessage("ERROR: Node.js not executable at: " . $config['node_path'] . " (" . trim($nodeCheck ?: 'unknown') . ")", 'ERROR');
    exit(1);
}

logMessage("Node.js version: " . trim($nodeCheck));

// Run scraper
$result = runScraper($config);

if ($result['success']) {
    logMessage("Scraper completed successfully");
    logActivity('Scraper cycle completed', 'success');

    // Parse output for stats
    if (!empty($result['output']['stdout'])) {
        $output = json_decode($result['output']['stdout'], true);
        if ($output && isset($output['newArticles'])) {
            $articleCount = count($output['newArticles']);
            logMessage("New articles found: " . $articleCount);
        }
    }
} else {
    logMessage("Scraper failed with code: " . $result['return_code'], 'ERROR');
    logMessage("Error: " . ($result['output']['stderr'] ?? 'Unknown'), 'ERROR');
    logActivity('Scraper cycle failed', 'error');
}

logMessage("=== Scheduler Cycle Complete ===");

exit($result['success'] ? 0 : 1);
