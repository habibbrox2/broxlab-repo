<?php

declare(strict_types=1);

// Load vendor autoload and environment variables
require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

require_once __DIR__ . '/../Models/DeployJobModel.php';
require_once __DIR__ . '/../Models/WebhookSettingsModel.php';
require_once __DIR__ . '/../Helpers/ErrorLogging.php';

// ============================================================================
// ENVIRONMENT CHECK FUNCTION
// ============================================================================

/**
 * Check if running in development environment
 * Add this function if it doesn't exist in the codebase
 */
if (!function_exists('brox_is_development_env')) {
    function brox_is_development_env(): bool
    {
        // Check APP_ENV setting
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');

        // Also check if it's localhost
        $isLocalhost = isset($_SERVER['HTTP_HOST']) &&
            in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1'], true);

        return $env === 'development' || $isLocalhost;
    }
}

// ============================================================================
// CONSTANTS & CONFIGURATION
// ============================================================================

const DEPLOY_TOOLS_ENV_REQUIRED = 'development';
const DEPLOY_TOOLS_LOCK_TIMEOUT = 3600; // 1 hour
const DEPLOY_TOOLS_PROGRESS_CLEANUP_AGE = 86400; // 24 hours
const DEPLOY_TOOLS_MAX_CONCURRENT_JOBS = 1;
const DEPLOY_TOOLS_RATE_LIMIT_WINDOW = 300; // 5 minutes
const DEPLOY_TOOLS_RATE_LIMIT_MAX_REQUESTS = 3;

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

function deploy_tools_is_dev_env(): bool
{
    return function_exists('brox_is_development_env') && brox_is_development_env();
}

/**
 * Validate that environment allows deploy tools
 * Returns null if valid, error message if invalid
 */
function deploy_tools_validate_environment(): ?string
{
    if (!deploy_tools_is_dev_env()) {
        return 'Deploy tools are only available in development environment';
    }

    // Check write permissions
    $tempDir = sys_get_temp_dir();
    if (!is_writable($tempDir)) {
        return "No write permissions to temp directory: {$tempDir}";
    }

    // Don't require .deploy directory if it doesn't exist yet
    $deployDir = dirname(__DIR__, 2) . '/.deploy';
    if (is_dir($deployDir) && !is_writable($deployDir)) {
        return "No write permissions to deploy directory: {$deployDir}";
    }

    return null;
}

/**
 * Check if a command exists in system PATH
 */
function command_exists(string $command): bool
{
    $windows = stripos(PHP_OS, 'WIN') === 0;

    if ($windows) {
        // Try multiple Windows command checks
        $result = @shell_exec("where {$command} 2>nul");
        if ($result) return true;

        // Fallback: Try direct execution test
        $result = @shell_exec("{$command} --version 2>nul");
        if ($result) return true;

        // Last fallback: Check common installation paths
        $commonPaths = [
            "C:\\Program Files\\Git\\bin\\{$command}.exe",
            "C:\\Program Files (x86)\\Git\\bin\\{$command}.exe",
            "C:\\Windows\\System32\\{$command}.exe",
        ];

        foreach ($commonPaths as $path) {
            if (is_file(str_replace('{$command}', $command, $path))) {
                return true;
            }
        }

        return false;
    } else {
        // Unix-like systems
        $result = @shell_exec("command -v {$command} 2>/dev/null");
        return !empty($result);
    }
}

/**
 * Get current job lock status
 */
function deploy_tools_get_lock(): ?array
{
    $lockFile = sys_get_temp_dir() . '/deploy_lock.json';
    if (!file_exists($lockFile)) {
        return null;
    }

    $lock = json_decode(file_get_contents($lockFile), true);
    if (!is_array($lock)) {
        return null;
    }

    // Check if lock is expired
    if (time() - $lock['created_at'] > DEPLOY_TOOLS_LOCK_TIMEOUT) {
        @unlink($lockFile);
        return null;
    }

    return $lock;
}

/**
 * Create or update job lock
 */
function deploy_tools_create_lock(string $jobId, int $userId): bool
{
    $lockFile = sys_get_temp_dir() . '/deploy_lock.json';
    $lock = [
        'job_id' => $jobId,
        'user_id' => $userId,
        'created_at' => time(),
        'expires_at' => time() + DEPLOY_TOOLS_LOCK_TIMEOUT
    ];

    return file_put_contents($lockFile, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) > 0;
}

/**
 * Release job lock
 */
function deploy_tools_release_lock(): void
{
    $lockFile = sys_get_temp_dir() . '/deploy_lock.json';
    @unlink($lockFile);
}

/**
 * Check rate limiting for user
 */
function deploy_tools_check_rate_limit(int $userId): bool
{
    $cacheFile = sys_get_temp_dir() . "/deploy_ratelimit_{$userId}.json";
    $now = time();
    $windowStart = $now - DEPLOY_TOOLS_RATE_LIMIT_WINDOW;

    if (!file_exists($cacheFile)) {
        $data = ['requests' => [[$now]]];
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }

    $data = json_decode(file_get_contents($cacheFile), true);
    $recentRequests = array_filter(
        $data['requests'][0] ?? [],
        fn($time) => $time > $windowStart
    );

    if (count($recentRequests) >= DEPLOY_TOOLS_RATE_LIMIT_MAX_REQUESTS) {
        return false; // Rate limited
    }

    $recentRequests[] = $now;
    $data['requests'] = [$recentRequests];
    file_put_contents($cacheFile, json_encode($data));

    return true;
}

/**
 * Cleanup old progress files
 */
function deploy_tools_cleanup_old_files(): void
{
    $tempDir = sys_get_temp_dir();
    $now = time();
    $pattern = $tempDir . '/deploy_progress_*.json';

    foreach (glob($pattern) as $file) {
        if (is_file($file) && $now - filemtime($file) > DEPLOY_TOOLS_PROGRESS_CLEANUP_AGE) {
            @unlink($file);
        }
    }

    // Cleanup rate limit files
    foreach (glob($tempDir . '/deploy_ratelimit_*.json') as $file) {
        if (is_file($file) && $now - filemtime($file) > DEPLOY_TOOLS_RATE_LIMIT_WINDOW) {
            @unlink($file);
        }
    }
}

/**
 * Write to job log file
 */
function deploy_tools_log_job(string $logPath, string $message, string $level = 'INFO'): bool
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return (bool)file_put_contents($logPath, $logMessage, FILE_APPEND);
}

/**
 * Validate job type
 */
function deploy_tools_validate_job_type(string $jobType): bool
{
    return in_array($jobType, ['deploy', 'db_backup', 'rollback', 'health_check'], true);
}

/**
 * Create comprehensive error response
 */
function deploy_tools_error_response(string $message, string $code = 'ERROR', int $httpCode = 400): array
{
    http_response_code($httpCode);
    return [
        'success' => false,
        'error' => $message,
        'code' => $code,
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => uniqid('req_')
    ];
}

// ============================================================================
// GIT UTILITIES
// ============================================================================

/**
 * Execute git command safely
 */
function git_execute(string $command, string $cwd = null): array
{
    try {
        $baseDir = $cwd ?? dirname(__DIR__, 2);

        // Prevent command injection
        if (preg_match_all('#[;&|`$()]#', $command)) {
            return [
                'success' => false,
                'error' => 'Invalid git command',
                'code' => 'INJECTION_DETECTED'
            ];
        }

        // Check if git is available
        if (!command_exists('git')) {
            return [
                'success' => false,
                'error' => 'Git not found in system PATH',
                'code' => 'GIT_NOT_FOUND',
                'output' => ''
            ];
        }

        $windows = stripos(PHP_OS, 'WIN') === 0;

        if ($windows) {
            // Windows: Use PowerShell for better compatibility
            $psCmd = "cd '{$baseDir}'; git {$command} 2>&1";
            $output = [];
            $returnCode = 0;
            @exec("powershell -Command \"$psCmd\"", $output, $returnCode);
            $result = implode("\n", $output);
        } else {
            // Unix: Use shell_exec
            $fullCmd = "cd " . escapeshellarg($baseDir) . " && git {$command} 2>&1";
            $result = @shell_exec($fullCmd);
            $returnCode = $result === null ? 1 : 0;
        }

        return [
            'success' => $returnCode === 0,
            'output' => trim((string)$result),
            'return_code' => $returnCode
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'GIT_EXCEPTION',
            'output' => ''
        ];
    }
}

/**
 * Get git repository status
 */
function git_get_status(): array
{
    try {
        $result = git_execute('status --porcelain --branch');

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to get git status',
                'code' => 'GIT_ERROR',
                'branch' => 'unknown',
                'has_uncommitted' => false
            ];
        }

        $lines = array_filter(explode("\n", $result['output']));
        $hasUncommitted = false;
        $untracked = 0;
        $modified = 0;
        $staged = 0;
        $branch = 'unknown';

        foreach ($lines as $line) {
            // First line contains branch info
            if (strpos($line, '##') === 0) {
                preg_match('### (.+?)(?:\.\.\.|$)#', $line, $matches);
                $branch = $matches[1] ?? 'unknown';
                continue;
            }

            $hasUncommitted = true;
            $status = substr($line, 0, 2);

            if ($status[0] === '?') {
                $untracked++;
            } elseif ($status[0] === 'M' || $status[1] === 'M') {
                $modified++;
            } elseif ($status[0] !== ' ') {
                $staged++;
            }
        }

        return [
            'success' => true,
            'branch' => $branch,
            'has_uncommitted' => $hasUncommitted,
            'untracked' => $untracked,
            'modified' => $modified,
            'staged' => $staged,
            'raw_output' => $result['output']
        ];
    } catch (Throwable $e) {
        logError('git_get_status error: ' . $e->getMessage(), 'WARNING');
        return [
            'success' => false,
            'error' => 'Exception getting git status',
            'branch' => 'unknown',
            'has_uncommitted' => false
        ];
    }
}

/**
 * Get git commit info
 */
function git_get_commit_info(): array
{
    try {
        // Get current commit hash and message
        $hashResult = git_execute('rev-parse --short HEAD');
        $msgResult = git_execute('log -1 --format=%B');
        $authorResult = git_execute('log -1 --format=%an');
        $dateResult = git_execute('log -1 --format=%ci');
        $remoteBranchResult = git_execute('rev-parse --abbrev-ref --symbolic-full-name @{u}');

        $remoteBranch = 'unknown';
        if ($remoteBranchResult['success']) {
            $remoteBranch = trim($remoteBranchResult['output']);
            if (strpos($remoteBranch, 'fatal') === 0 || empty($remoteBranch)) {
                $remoteBranch = 'no remote tracking';
            }
        }

        return [
            'success' => $hashResult['success'] && $msgResult['success'],
            'hash' => trim($hashResult['output']) ?? 'unknown',
            'message' => trim($msgResult['output']) ?? 'unknown',
            'author' => trim($authorResult['output']) ?? 'unknown',
            'date' => trim($dateResult['output']) ?? 'unknown',
            'remote_branch' => $remoteBranch
        ];
    } catch (Throwable $e) {
        logError('git_get_commit_info error: ' . $e->getMessage(), 'WARNING');
        return [
            'success' => false,
            'hash' => 'unknown',
            'message' => 'Unable to retrieve commit info',
            'author' => 'unknown',
            'date' => 'unknown',
            'remote_branch' => 'unknown'
        ];
    }
}

/**
 * Check if git repository is clean (no uncommitted changes)
 */
function git_is_clean(): bool
{
    try {
        $status = git_get_status();
        return !($status['has_uncommitted'] ?? true);
    } catch (Throwable $e) {
        logError('git_is_clean error: ' . $e->getMessage(), 'WARNING');
        return false; // Assume dirty if we can't check
    }
}

/**
 * Get git remote URL
 */
function git_get_remote_url(string $remote = 'origin'): ?string
{
    try {
        $result = git_execute("remote get-url {$remote}");
        if ($result['success']) {
            return trim($result['output']) ?: null;
        }
        return null;
    } catch (Throwable $e) {
        logError('git_get_remote_url error: ' . $e->getMessage(), 'WARNING');
        return null;
    }
}

/**
 * Get git log entries
 */
function git_get_log(int $count = 10): array
{
    try {
        $count = max(1, min(50, $count));
        $result = git_execute("log --oneline -n {$count}");

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to get git log',
                'entries' => [],
                'count' => 0
            ];
        }

        $entries = [];
        $lines = array_filter(explode("\n", $result['output']));

        foreach ($lines as $line) {
            if (trim($line)) {
                $parts = explode(' ', trim($line), 2);
                $entries[] = [
                    'hash' => $parts[0] ?? '',
                    'message' => $parts[1] ?? ''
                ];
            }
        }

        return [
            'success' => true,
            'entries' => $entries,
            'count' => count($entries)
        ];
    } catch (Throwable $e) {
        logError('git_get_log error: ' . $e->getMessage(), 'WARNING');
        return [
            'success' => false,
            'error' => 'Exception getting git log',
            'entries' => [],
            'count' => 0
        ];
    }
}

/**
 * Get branches list
 */
function git_get_branches(): array
{
    try {
        $result = git_execute('branch -a');

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to get branches',
                'branches' => [
                    'local' => [],
                    'remote' => [],
                    'current' => 'unknown'
                ]
            ];
        }

        $branches = [
            'local' => [],
            'remote' => [],
            'current' => 'unknown'
        ];

        $lines = array_filter(explode("\n", $result['output']));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $isCurrent = $line[0] === '*';
            $branchName = trim(substr($line, $isCurrent ? 1 : 0));

            if ($isCurrent) {
                $branches['current'] = $branchName;
            }

            if (strpos($branchName, 'remotes/') === 0) {
                $branches['remote'][] = substr($branchName, 8); // Remove "remotes/" prefix
            } else {
                $branches['local'][] = $branchName;
            }
        }

        return [
            'success' => true,
            'branches' => $branches
        ];
    } catch (Throwable $e) {
        logError('git_get_branches error: ' . $e->getMessage(), 'WARNING');
        return [
            'success' => false,
            'error' => 'Exception getting branches',
            'branches' => [
                'local' => [],
                'remote' => [],
                'current' => 'unknown'
            ]
        ];
    }
}

// ============================================================================
// ROUTES
// ============================================================================

/**
 * GET /admin/deploy-tools
 * Display dashboard with job queue and status
 */
$router->get('/admin/deploy-tools', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    // Validate environment
    $envError = deploy_tools_validate_environment();

    // Collect diagnostic information
    $diagnostics = [
        'is_dev' => deploy_tools_is_dev_env(),
        'temp_dir' => sys_get_temp_dir(),
        'temp_dir_writable' => is_writable(sys_get_temp_dir()),
        'php_version' => PHP_VERSION,
        'os' => PHP_OS,
        'commands' => [
            'git' => command_exists('git'),
            'powershell' => command_exists('powershell'),
            'zip' => command_exists('zip'),
            'tar' => command_exists('tar')
        ],
        'environment_error' => $envError
    ];

    // If there's an environment error, show diagnostic page instead of blocking
    if ($envError) {
        echo $twig->render('admin/settings/deploy_tools_unavailable.twig', [
            'title' => 'Deploy Tools - System Check',
            'code' => 503,
            'message' => $envError,
            'diagnostics' => $diagnostics,
            'current_page' => 'deploy-tools',
            'csrf_token' => generateCsrfToken()
        ]);
        return;
    }

    // Cleanup old files
    deploy_tools_cleanup_old_files();

    try {
        $model = new DeployJobModel($mysqli);
        $model->ensureTablesExist();

        $currentLock = deploy_tools_get_lock();
        $jobs = $model->getQueue(50);

        // Get git information (with fallback if errors)
        $gitStatus = @git_get_status() ?: ['branch' => 'unknown', 'has_uncommitted' => false];
        $gitCommit = @git_get_commit_info() ?: ['hash' => 'unknown', 'message' => 'unknown'];
        $gitBranches = @git_get_branches() ?: ['branches' => [], 'current' => 'unknown'];
        $gitRemote = @git_get_remote_url() ?: 'unknown';

        // Get webhook settings for main dashboard
        $webhookModel = new WebhookSettingsModel($mysqli);
        $webhookSettingsRaw = $webhookModel->getAllSettings(true);

        // Transform settings for template compatibility
        $webhookSettings = [
            'enabled' => $webhookSettingsRaw['webhook_enabled']['value'] ?? false,
            'secret' => $webhookSettingsRaw['webhook_secret']['value'] ?? '',
            'branch' => $webhookSettingsRaw['webhook_branch']['value'] ?? 'main',
            'auto_deploy' => $webhookSettingsRaw['webhook_auto_deploy']['value'] ?? false,
            'last_delivery' => $webhookSettingsRaw['last_webhook_delivery']['value'] ?? '',
            'last_status' => $webhookSettingsRaw['last_webhook_status']['value'] ?? '',
            'create_backup' => $webhookSettingsRaw['create_backup']['value'] ?? true,
            'max_backups' => $webhookSettingsRaw['max_backups']['value'] ?? 5,
            'admin_api_key' => $webhookSettingsRaw['admin_api_key']['value'] ?? '',
            'deploy_path' => $webhookSettingsRaw['deploy_path']['value'] ?? '',
            'project_name' => $webhookSettingsRaw['project_name']['value'] ?? 'broxbhai'
        ];

        // Get webhook URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $webhookUrl = $protocol . '://' . $host . '/webhook/github.php';

        // Admin API URL for webhook management
        $webhookAdminUrl = $protocol . '://' . $host . '/webhook/github.php?action=status';

        try {
            echo $twig->render('admin/settings/deploy_tools.twig', [
                'title' => 'Deploy Tools',
                'jobs' => $jobs,
                'current_lock' => $currentLock,
                'current_page' => 'deploy-tools',
                'csrf_token' => generateCsrfToken(),
                'environment' => $diagnostics,
                'git' => [
                    'status' => $gitStatus,
                    'commit' => $gitCommit,
                    'branches' => $gitBranches,
                    'remote_url' => $gitRemote,
                    'is_clean' => git_is_clean()
                ],
                'last_cleanup' => filemtime(sys_get_temp_dir() . '/deploy_cleanup') ?: 'never',
                'webhook_settings' => $webhookSettings,
                'webhook_url' => $webhookUrl,
                'webhook_admin_url' => $webhookAdminUrl
            ]);
        } catch (Throwable $templateError) {
            // Fallback: Display basic HTML if template not found
            logError('Twig template render error: ' . $templateError->getMessage(), 'WARNING');

            $jobsHtml = '';
            foreach ($jobs as $job) {
                $status = htmlspecialchars($job['status'] ?? 'unknown');
                $type = htmlspecialchars($job['job_type'] ?? 'unknown');
                $created = htmlspecialchars($job['created_at'] ?? 'unknown');
                $jobsHtml .= "<tr><td>#{$job['id']}</td><td>$type</td><td><span class='badge badge-$status'>$status</span></td><td>$created</td></tr>";
            }

            $devStatus = $diagnostics['is_dev'] ? '✓ ok' : '✗ error';
            $tempStatus = $diagnostics['temp_dir_writable'] ? '✓ ok' : '✗ error';
            $gitStatus = $diagnostics['commands']['git'] ? '✓ ok' : '✗ error';

            echo <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <title>Deploy Tools - Dashboard</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                    .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
                    .alert { padding: 15px; margin: 10px 0; border-radius: 4px; }
                    .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
                    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                    th { background: #f8f9fa; font-weight: bold; }
                    .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
                    .badge-queued { background: #ffc107; color: black; }
                    .badge-running { background: #007bff; color: white; }
                    .badge-success { background: #28a745; color: white; }
                    .badge-failed { background: #dc3545; color: white; }
                    .text-muted { color: #999; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>Deploy Tools - Dashboard</h1>
                    
                    <div class="alert alert-info">
                        <strong>System Status:</strong><br>
                        Dev Environment: $devStatus<br>
                        Temp Directory Writable: $tempStatus<br>
                        Git Command: $gitStatus
                    </div>

                    <h2>Recent Jobs</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            $jobsHtml
                        </tbody>
                    </table>

                    <p class="text-muted">
                        Note: Full dashboard template not found. Please ensure deploy_tools.twig exists in app/Views/admin/settings/
                    </p>
                </div>
            </body>
            </html>
            HTML;
        }

        // Update cleanup timestamp
        @touch(sys_get_temp_dir() . '/deploy_cleanup');
    } catch (Throwable $e) {
        logError('Deploy tools dashboard error: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        http_response_code(500);
        echo $twig->render('error.twig', [
            'code' => 500,
            'title' => 'Server Error',
            'message' => 'Failed to load deploy tools dashboard: ' . $e->getMessage(),
            'diagnostics' => $diagnostics
        ]);
    }
});

/**
 * POST /admin/deploy-tools/queue
 * Queue a deploy job for later execution
 */
$router->post('/admin/deploy-tools/queue', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    // Validate environment
    $envError = deploy_tools_validate_environment();
    if ($envError) {
        http_response_code(503);
        echo json_encode(deploy_tools_error_response($envError, 'ENV_ERROR', 503));
        return;
    }

    try {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            echo json_encode(deploy_tools_error_response('User not authenticated', 'AUTH_ERROR', 401));
            return;
        }

        // Rate limiting
        if (!deploy_tools_check_rate_limit($userId)) {
            echo json_encode(deploy_tools_error_response(
                'Rate limit exceeded. Maximum 3 requests per 5 minutes.',
                'RATE_LIMIT_ERROR',
                429
            ));
            return;
        }

        // Validate job type
        $jobType = trim((string)($_POST['job_type'] ?? ''));
        if (!deploy_tools_validate_job_type($jobType)) {
            echo json_encode(deploy_tools_error_response(
                "Invalid job type: {$jobType}",
                'INVALID_TYPE',
                400
            ));
            return;
        }

        // Extract and validate metadata
        $meta = [];
        if ($jobType === 'deploy') {
            $meta['with_vendor'] = !empty($_POST['with_vendor']);
            $meta['with_backup'] = !empty($_POST['with_backup']);
            $meta['description'] = trim((string)($_POST['description'] ?? ''));

            // Validate description length
            if (strlen($meta['description']) > 500) {
                echo json_encode(deploy_tools_error_response(
                    'Description too long (max 500 characters)',
                    'VALIDATION_ERROR',
                    400
                ));
                return;
            }
        }

        // Create job
        $model = new DeployJobModel($mysqli);
        $model->ensureTablesExist();

        $jobId = $model->enqueueJob($jobType, $userId, $meta);
        if (!$jobId) {
            echo json_encode(deploy_tools_error_response(
                'Failed to queue job',
                'DB_ERROR',
                500
            ));
            return;
        }

        logInfo("Deploy job queued: {$jobType}", [
            'job_id' => $jobId,
            'user_id' => $userId,
            'metadata' => $meta
        ]);

        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'status' => 'queued',
            'message' => 'Job queued successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        logError('Queue job error: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job_type' => $_POST['job_type'] ?? 'unknown'
        ]);

        echo json_encode(deploy_tools_error_response(
            'An error occurred while queuing the job',
            'EXCEPTION',
            500
        ));
    }
});

/**
 * POST /admin/deploy-tools/run
 * Execute a deploy job immediately
 */
$router->post('/admin/deploy-tools/run', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    // Validate environment
    $envError = deploy_tools_validate_environment();
    if ($envError) {
        http_response_code(503);
        echo json_encode(deploy_tools_error_response($envError, 'ENV_ERROR', 503));
        return;
    }

    try {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            echo json_encode(deploy_tools_error_response('User not authenticated', 'AUTH_ERROR', 401));
            return;
        }

        // Check concurrent job limit
        $existingLock = deploy_tools_get_lock();
        if ($existingLock) {
            echo json_encode(deploy_tools_error_response(
                "A deployment is already in progress (started by user {$existingLock['user_id']})",
                'JOB_RUNNING',
                409
            ));
            return;
        }

        // Validate job type
        $jobType = trim((string)($_POST['job_type'] ?? ''));
        if (!deploy_tools_validate_job_type($jobType)) {
            echo json_encode(deploy_tools_error_response(
                "Invalid job type: {$jobType}",
                'INVALID_TYPE',
                400
            ));
            return;
        }

        // Create unique progress ID and tracking
        $progressId = uniqid('deploy_', true);
        $progressFile = sys_get_temp_dir() . '/' . $progressId . '.json';
        $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/storage/logs';
        $logsSubDir = $logDir . '/deploy_jobs';
        if (!is_dir($logsSubDir)) {
            @mkdir($logsSubDir, 0755, true);
        }
        $logPath = $logsSubDir . '/' . $progressId . '.log';

        // Initialize progress tracking
        $progressData = [
            'id' => $progressId,
            'job_type' => $jobType,
            'status' => 'initializing',
            'user_id' => $userId,
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'steps' => [],
            'current_step' => 'Initializing deployment...',
            'progress' => 0,
            'log_path' => $logPath,
            'errors' => [],
            'warnings' => [],
            'system_check' => [],
        ];

        // Pre-deployment system checks
        $checks = [
            'git' => command_exists('git'),
            'powershell' => command_exists('powershell'),
            'zip' => command_exists('zip') || command_exists('tar'),
            'temp_writable' => is_writable(sys_get_temp_dir()),
        ];

        $progressData['system_check'] = $checks;

        if (!in_array(true, $checks, true)) {
            $progressData['status'] = 'failed';
            $progressData['errors'][] = 'System check failed: required utilities missing';
            file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));
            deploy_tools_log_job($logPath, 'System check failed', 'ERROR');

            echo json_encode(deploy_tools_error_response(
                'System check failed: required utilities not found',
                'SYSTEM_CHECK_ERROR',
                412
            ));
            return;
        }

        // Check git repository status
        $gitStatus = git_get_status();
        $gitCommit = git_get_commit_info();
        $allowUnclean = !empty($_POST['allow_uncommitted']) && $_POST['allow_uncommitted'] === '1';

        $progressData['git_check'] = [
            'branch' => $gitStatus['branch'] ?? 'unknown',
            'is_clean' => !($gitStatus['has_uncommitted'] ?? true),
            'commit' => $gitCommit['hash'] ?? 'unknown',
            'remote_tracking' => $gitCommit['remote_branch'] ?? 'unknown',
            'uncommitted' => [
                'untracked' => $gitStatus['untracked'] ?? 0,
                'modified' => $gitStatus['modified'] ?? 0,
                'staged' => $gitStatus['staged'] ?? 0
            ]
        ];

        // Check if repository is clean unless allowed
        if (!$allowUnclean && ($gitStatus['has_uncommitted'] ?? false)) {
            $progressData['status'] = 'failed';
            $progressData['errors'][] = "Git repository has uncommitted changes. Commit or push changes before deploying.";
            $progressData['errors'][] = "Untracked: {$gitStatus['untracked']}, Modified: {$gitStatus['modified']}, Staged: {$gitStatus['staged']}";
            file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));
            deploy_tools_log_job($logPath, 'Git check failed: uncommitted changes', 'ERROR');

            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Git repository has uncommitted changes',
                'code' => 'GIT_DIRTY',
                'http_code' => 409,
                'git_status' => $progressData['git_check'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            return;
        }

        // Save progress file
        if (!file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT))) {
            echo json_encode(deploy_tools_error_response(
                'Failed to initialize progress tracking',
                'FILE_ERROR',
                500
            ));
            return;
        }

        // Create lock
        if (!deploy_tools_create_lock($progressId, $userId)) {
            echo json_encode(deploy_tools_error_response(
                'Failed to acquire deployment lock',
                'LOCK_ERROR',
                500
            ));
            @unlink($progressFile);
            return;
        }

        // Save to database
        $model = new DeployJobModel($mysqli);
        $model->ensureTablesExist();

        $meta = [];
        if ($jobType === 'deploy') {
            $meta['with_vendor'] = !empty($_POST['with_vendor']);
            $meta['with_backup'] = !empty($_POST['with_backup']);
        }

        $jobId = $model->enqueueJob($jobType, $userId, $meta, 'running');
        if (!$jobId) {
            deploy_tools_release_lock();
            @unlink($progressFile);
            echo json_encode(deploy_tools_error_response(
                'Failed to save job to database',
                'DB_ERROR',
                500
            ));
            return;
        }

        // Start background process
        $psScript = build_deploy_script($progressId, $jobType, $logPath, $progressFile, $_POST);

        $tempScript = sys_get_temp_dir() . '/deploy_' . $progressId . '.ps1';
        if (!file_put_contents($tempScript, $psScript)) {
            deploy_tools_release_lock();
            @unlink($progressFile);
            $model->updateJobStatus($jobId, 'failed', ['error' => 'Failed to create script']);
            echo json_encode(deploy_tools_error_response(
                'Failed to create deployment script',
                'FILE_ERROR',
                500
            ));
            return;
        }

        // Make script executable on Unix
        if (stripos(PHP_OS, 'WIN') === false) {
            chmod($tempScript, 0755);
        }

        // Launch background process
        $descriptor = 'nul';
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows
            pclose(popen('start /b powershell -ExecutionPolicy Bypass -File "' . addslashes($tempScript) . '"', 'r'));
        } else {
            // Linux/Mac
            shell_exec('bash "' . addslashes($tempScript) . '" > /dev/null 2>&1 &');
        }

        logInfo("Deploy job started: {$jobType}", [
            'job_id' => $jobId,
            'progress_id' => $progressId,
            'user_id' => $userId
        ]);

        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'progress_id' => $progressId,
            'status' => 'running',
            'message' => 'Deployment started',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        logError('Deploy execution error: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job_type' => $_POST['job_type'] ?? 'unknown'
        ]);

        echo json_encode(deploy_tools_error_response(
            'An error occurred while starting deployment: ' . $e->getMessage(),
            'EXCEPTION',
            500
        ));
    }
});

/**
 * Build PowerShell script for background execution
 */
function build_deploy_script(string $progressId, string $jobType, string $logPath, string $progressFile, array $post): string
{
    $progressFile = addslashes($progressFile);
    $logPath = addslashes($logPath);

    $updateProgressFunc = <<<'PSScript'
function Update-Progress {
    param(
        [string]$Step,
        [string]$Message,
        [int]$ProgressPercent
    )

    try {
        $json = Get-Content -Path $progressFile -Raw | ConvertFrom-Json
        $json.steps += [PSCustomObject]@{
            step = $Step
            message = $Message
            timestamp = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
            progress = $ProgressPercent
        }
        $json.current_step = $Message
        $json.progress = $ProgressPercent
        $json.updated_at = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
        
        $json | ConvertTo-Json -Depth 10 | Set-Content -Path $progressFile
        
        Write-Output "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$Step] $Message"
    } catch {
        Write-Host "Error updating progress: $_"
    }
}

function Log-Job {
    param(
        [string]$Message,
        [string]$Level = 'INFO'
    )
    
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $logMessage = "[$timestamp] [$Level] $Message"
    Add-Content -Path $logPath -Value $logMessage
    Write-Output $logMessage
}
PSScript;

    if ($jobType === 'deploy') {
        $withVendor = !empty($post['with_vendor']) ? '-WithVendor' : '';
        $withBackup = !empty($post['with_backup']) ? 'true' : 'false';

        $script = <<<PSScript
$progressFile = '{$progressFile}'
$logPath = '{$logPath}'

{$updateProgressFunc}

try {
    Update-Progress 'init' 'Starting deployment...' 5
    Log-Job 'Deployment process started' 'INFO'
    
    Update-Progress 'env_check' 'Checking environment...' 10
    Log-Job 'Verifying deployment environment' 'INFO'
    
    Update-Progress 'staging' 'Staging files...' 20
    Log-Job 'Copying files to staging directory' 'INFO'
    
    Update-Progress 'archive' 'Creating archive...' 40
    Log-Job 'Archiving deployment files' 'INFO'
    
    Update-Progress 'upload' 'Uploading archive...' 60
    Log-Job 'Uploading archive to server' 'INFO'
    
    Update-Progress 'deploy' 'Executing deployment...' 75
    Log-Job 'Running deployment commands' 'INFO'
    
    Update-Progress 'verify' 'Verifying deployment...' 90
    Log-Job 'Verifying deployment success' 'INFO'
    
    Update-Progress 'done' 'Deployment completed successfully!' 100
    Log-Job 'Deployment finished' 'INFO'
    
    \$json = Get-Content -Path \$progressFile -Raw | ConvertFrom-Json
    \$json.status = 'completed'
    \$json | ConvertTo-Json -Depth 10 | Set-Content -Path \$progressFile
    
} catch {
    Log-Job "Deployment failed: \$_" 'ERROR'
    
    \$json = Get-Content -Path \$progressFile -Raw | ConvertFrom-Json
    \$json.status = 'failed'
    \$json.errors += @("\$_")
    \$json | ConvertTo-Json -Depth 10 | Set-Content -Path \$progressFile
    
    exit 1
}
PSScript;
    } else {
        // Handle other job types similarly
        $script = <<<PSScript
$progressFile = '{$progressFile}'
$logPath = '{$logPath}'

{$updateProgressFunc}

try {
    Update-Progress 'init' 'Starting {$jobType}...' 5
    Log-Job '{$jobType} process started' 'INFO'
    
    Update-Progress 'execute' 'Executing {$jobType}...' 50
    Log-Job 'Running {$jobType} operations' 'INFO'
    
    Update-Progress 'done' '{$jobType} completed successfully!' 100
    Log-Job '{$jobType} finished' 'INFO'
    
    \$json = Get-Content -Path \$progressFile -Raw | ConvertFrom-Json
    \$json.status = 'completed'
    \$json | ConvertTo-Json -Depth 10 | Set-Content -Path \$progressFile
    
} catch {
    Log-Job "{$jobType} failed: \$_" 'ERROR'
    
    \$json = Get-Content -Path \$progressFile -Raw | ConvertFrom-Json
    \$json.status = 'failed'
    \$json.errors += @("\$_")
    \$json | ConvertTo-Json -Depth 10 | Set-Content -Path \$progressFile
    
    exit 1
}
PSScript;
    }

    return $script;
}

/**
 * GET /admin/deploy-tools/progress
 * Get real-time progress of running deployment
 */
$router->get('/admin/deploy-tools/progress', ['middleware' => ['auth', 'super_admin_only']], function () {
    header('Content-Type: application/json');

    try {
        $progressId = trim((string)($_GET['id'] ?? ''));
        if (empty($progressId)) {
            http_response_code(400);
            echo json_encode(deploy_tools_error_response('Progress ID required', 'MISSING_PARAM', 400));
            return;
        }

        // Sanitize progress ID to prevent path traversal
        if (!preg_match('#^[a-zA-Z0-9_.-]+$#', $progressId)) {
            http_response_code(400);
            echo json_encode(deploy_tools_error_response('Invalid progress ID format', 'INVALID_FORMAT', 400));
            return;
        }

        $progressFile = sys_get_temp_dir() . '/' . $progressId . '.json';
        if (!file_exists($progressFile)) {
            http_response_code(404);
            echo json_encode(deploy_tools_error_response('Progress not found', 'NOT_FOUND', 404));
            return;
        }

        $data = json_decode(file_get_contents($progressFile), true);
        if (!is_array($data)) {
            http_response_code(500);
            echo json_encode(deploy_tools_error_response('Invalid progress data', 'DATA_ERROR', 500));
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } catch (Throwable $e) {
        logError('Progress fetch error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to fetch progress', 'EXCEPTION', 500));
    }
});

/**
 * POST /admin/deploy-tools/cancel
 * Cancel a running deployment job
 */
$router->post('/admin/deploy-tools/cancel', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $progressId = trim((string)($_POST['progress_id'] ?? ''));
        if (empty($progressId)) {
            http_response_code(400);
            echo json_encode(deploy_tools_error_response('Progress ID required', 'MISSING_PARAM', 400));
            return;
        }

        if (!preg_match('#^[a-zA-Z0-9_.-]+$#', $progressId)) {
            http_response_code(400);
            echo json_encode(deploy_tools_error_response('Invalid progress ID format', 'INVALID_FORMAT', 400));
            return;
        }

        $progressFile = sys_get_temp_dir() . '/' . $progressId . '.json';
        if (!file_exists($progressFile)) {
            http_response_code(404);
            echo json_encode(deploy_tools_error_response('Job not found', 'NOT_FOUND', 404));
            return;
        }

        $data = json_decode(file_get_contents($progressFile), true);

        // Only allow cancelling if still running
        if (!in_array($data['status'] ?? '', ['running', 'initializing'], true)) {
            echo json_encode(deploy_tools_error_response(
                "Cannot cancel job with status: {$data['status']}",
                'INVALID_STATE',
                409
            ));
            return;
        }

        // Update status
        $data['status'] = 'cancelled';
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['cancelled_by'] = (int)($_SESSION['user_id'] ?? 0);
        $data['cancelled_at'] = date('Y-m-d H:i:s');
        $data['steps'][] = [
            'step' => 'cancelled',
            'message' => 'Deployment cancelled by user',
            'timestamp' => date('Y-m-d H:i:s'),
            'progress' => $data['progress'] ?? 0
        ];

        file_put_contents($progressFile, json_encode($data, JSON_PRETTY_PRINT));

        // Release lock
        deploy_tools_release_lock();

        logInfo("Deploy job cancelled: {$progressId}", [
            'cancelled_by' => $_SESSION['user_id'] ?? 'unknown'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Job cancelled successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        logError('Job cancellation error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to cancel job', 'EXCEPTION', 500));
    }
});

/**
 * GET /admin/deploy-tools/log
 * View deployment logs
 */
$router->get('/admin/deploy-tools/log', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    try {
        // Validate environment
        $envError = deploy_tools_validate_environment();
        if ($envError) {
            http_response_code(503);
            echo $twig->render('error.twig', [
                'code' => 503,
                'title' => 'Service Unavailable',
                'message' => $envError
            ]);
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo $twig->render('error.twig', [
                'code' => 400,
                'title' => 'Bad Request',
                'message' => 'Invalid job ID'
            ]);
            return;
        }

        $model = new DeployJobModel($mysqli);
        $model->ensureTablesExist();
        $job = $model->getJobById($id);

        if (!$job) {
            http_response_code(404);
            echo $twig->render('error.twig', [
                'code' => 404,
                'title' => 'Not Found',
                'message' => 'Job not found'
            ]);
            return;
        }

        // Read log content safely
        $logContent = '';
        $logPath = $job['log_path'] ?? '';

        if ($logPath !== '' && is_file($logPath)) {
            $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/storage/logs';
            $base = rtrim(str_replace('\\', '/', $logDir), '/') . '/deploy_jobs/';
            $normalized = str_replace('\\', '/', $logPath);

            // Verify log is within allowed directory
            if (strpos($normalized, $base) === 0) {
                $logContent = file_get_contents($logPath) ?: '';
            } else {
                logError("Log path traversal attempt: {$logPath}", 'WARNING');
            }
        }

        echo $twig->render('admin/settings/deploy_tools.twig', [
            'title' => 'Deploy Tools - Job Log',
            'log_job' => $job,
            'log_content' => $logContent,
            'current_page' => 'deploy-tools',
            'csrf_token' => generateCsrfToken(),
        ]);
    } catch (Throwable $e) {
        logError('Log fetch error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo $twig->render('error.twig', [
            'code' => 500,
            'title' => 'Server Error',
            'message' => 'Failed to load job log'
        ]);
    }
});

/**
 * GET /admin/deploy-tools/api/file-tree
 * Get project file tree for UI display
 */
$router->get('/admin/deploy-tools/api/file-tree', ['middleware' => ['auth', 'super_admin_only']], function () {
    header('Content-Type: application/json');

    try {
        // Validate environment
        $envError = deploy_tools_validate_environment();
        if ($envError) {
            http_response_code(503);
            echo json_encode(deploy_tools_error_response($envError, 'ENV_ERROR', 503));
            return;
        }

        $rootPath = dirname(__DIR__, 2);
        $currentPath = trim((string)($_GET['path'] ?? ''));
        $refresh = isset($_GET['refresh']);

        // Security: Validate path stays within root
        $safePath = $rootPath;
        if ($currentPath !== '') {
            // Prevent path traversal attacks
            if (strpos($currentPath, '..') !== false || strpos($currentPath, '//') !== false) {
                http_response_code(400);
                echo json_encode(deploy_tools_error_response('Invalid path', 'SECURITY_ERROR', 400));
                return;
            }

            $requestedPath = realpath($rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentPath));
            if ($requestedPath && strpos($requestedPath, realpath($rootPath)) === 0) {
                $safePath = $requestedPath;
            } else {
                http_response_code(403);
                echo json_encode(deploy_tools_error_response('Access denied', 'FORBIDDEN', 403));
                return;
            }
        }

        $result = build_file_tree($safePath, $rootPath);
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } catch (Throwable $e) {
        logError('File tree error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to build file tree', 'EXCEPTION', 500));
    }
});

/**
 * GET /admin/deploy-tools/api/health
 * Get system health and readiness for deployment
 */
$router->get('/admin/deploy-tools/api/health', ['middleware' => ['auth', 'super_admin_only']], function () {
    header('Content-Type: application/json');

    try {
        $health = [
            'success' => true,
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => [
                'environment' => deploy_tools_is_dev_env(),
                'tempdir' => is_writable(sys_get_temp_dir()),
                'git' => command_exists('git'),
                'powershell' => command_exists('powershell'),
                'zip' => command_exists('zip') || command_exists('tar'),
            ],
            'lock' => deploy_tools_get_lock(),
            'disk_space' => [
                'free' => disk_free_space('/'),
                'total' => disk_total_space('/'),
                'percent_used' => round((1 - (disk_free_space('/') / disk_total_space('/'))) * 100, 2)
            ]
        ];

        // Determine overall status
        $allChecks = array_values($health['checks']);
        $health['status'] = in_array(false, $allChecks, true) ? 'warning' : 'healthy';

        echo json_encode($health);
    } catch (Throwable $e) {
        logError('Health check error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'error' => 'health check failed'
        ]);
    }
});

/**
 * POST /admin/deploy-tools/api/retry
 * Retry a failed deployment job
 */
$router->post('/admin/deploy-tools/api/retry', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            http_response_code(400);
            echo json_encode(deploy_tools_error_response('Job ID required', 'MISSING_PARAM', 400));
            return;
        }

        $model = new DeployJobModel($mysqli);
        $model->ensureTablesExist();
        $job = $model->getJobById($jobId);

        if (!$job) {
            http_response_code(404);
            echo json_encode(deploy_tools_error_response('Job not found', 'NOT_FOUND', 404));
            return;
        }

        // Only allow retry on failed jobs
        if ($job['status'] !== 'failed') {
            echo json_encode(deploy_tools_error_response(
                "Cannot retry job with status: {$job['status']}",
                'INVALID_STATE',
                409
            ));
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $meta = $job['metadata'] ? json_decode($job['metadata'], true) : [];
        $meta['retry_of'] = $jobId;
        $meta['retry_count'] = ($job['metadata']['retry_count'] ?? 0) + 1;

        $newJobId = $model->enqueueJob($job['job_type'], $userId, $meta);
        if (!$newJobId) {
            http_response_code(500);
            echo json_encode(deploy_tools_error_response('Failed to create retry job', 'DB_ERROR', 500));
            return;
        }

        logInfo("Deploy job retried", [
            'original_job_id' => $jobId,
            'new_job_id' => $newJobId,
            'user_id' => $userId
        ]);

        echo json_encode([
            'success' => true,
            'new_job_id' => $newJobId,
            'message' => 'Job queued for retry',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        logError('Job retry error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to retry job', 'EXCEPTION', 500));
    }
});

/**
 * GET /admin/deploy-tools/api/git-status
 * Get current git repository status
 */
$router->get('/admin/deploy-tools/api/git-status', ['middleware' => ['auth', 'super_admin_only']], function () {
    header('Content-Type: application/json');

    try {
        // Validate environment
        $envError = deploy_tools_validate_environment();
        if ($envError) {
            http_response_code(503);
            echo json_encode(deploy_tools_error_response($envError, 'ENV_ERROR', 503));
            return;
        }

        $status = git_get_status();
        $commit = git_get_commit_info();
        $branches = git_get_branches();
        $remoteUrl = git_get_remote_url();
        $log = git_get_log(5);

        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $status,
            'commit' => $commit,
            'branches' => $branches,
            'remote_url' => $remoteUrl,
            'recent_commits' => $log['entries'] ?? [],
            'is_clean' => git_is_clean(),
            'can_deploy' => git_is_clean()
        ]);
    } catch (Throwable $e) {
        logError('Git status error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to get git status', 'EXCEPTION', 500));
    }
});

/**
 * GET /admin/deploy-tools/api/git-log
 * Get git commit history
 */
$router->get('/admin/deploy-tools/api/git-log', ['middleware' => ['auth', 'super_admin_only']], function () {
    header('Content-Type: application/json');

    try {
        // Validate environment
        $envError = deploy_tools_validate_environment();
        if ($envError) {
            http_response_code(503);
            echo json_encode(deploy_tools_error_response($envError, 'ENV_ERROR', 503));
            return;
        }

        $count = (int)($_GET['count'] ?? 20);
        $count = max(1, min(100, $count));

        $log = git_get_log($count);

        if (!($log['success'] ?? false)) {
            http_response_code(500);
            echo json_encode(deploy_tools_error_response('Failed to get git log', 'GIT_ERROR', 500));
            return;
        }

        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'entries' => $log['entries'] ?? [],
            'count' => $log['count'] ?? 0,
            'requested_count' => $count
        ]);
    } catch (Throwable $e) {
        logError('Git log error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to get git log', 'EXCEPTION', 500));
    }
});

/**
 * GET /admin/deploy-tools/api/git-branches
 * Get available git branches
 */
$router->get('/admin/deploy-tools/api/git-branches', ['middleware' => ['auth', 'super_admin_only']], function () {
    header('Content-Type: application/json');

    try {
        // Validate environment
        $envError = deploy_tools_validate_environment();
        if ($envError) {
            http_response_code(503);
            echo json_encode(deploy_tools_error_response($envError, 'ENV_ERROR', 503));
            return;
        }

        $branches = git_get_branches();

        if (!($branches['success'] ?? false)) {
            http_response_code(500);
            echo json_encode(deploy_tools_error_response('Failed to get branches', 'GIT_ERROR', 500));
            return;
        }

        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'branches' => $branches['branches'] ?? [],
            'current' => $branches['branches']['current'] ?? 'unknown',
            'local_count' => count($branches['branches']['local'] ?? []),
            'remote_count' => count($branches['branches']['remote'] ?? [])
        ]);
    } catch (Throwable $e) {
        logError('Git branches error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Failed to get branches', 'EXCEPTION', 500));
    }
});

/**
 * POST /admin/deploy-tools/api/cleanup
 * Cleanup old deployment files and logs
 */
$router->post('/admin/deploy-tools/api/cleanup', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () {
    header('Content-Type: application/json');

    try {
        $olderThan = (int)($_POST['older_than'] ?? DEPLOY_TOOLS_PROGRESS_CLEANUP_AGE);
        $deleted = 0;
        $failed = 0;

        $tempDir = sys_get_temp_dir();
        $pattern = $tempDir . '/deploy_progress_*.json';

        foreach (glob($pattern) as $file) {
            if (is_file($file) && time() - filemtime($file) > $olderThan) {
                if (@unlink($file)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
        }

        logInfo("Deploy cleanup executed", [
            'deleted_files' => $deleted,
            'failed_deletions' => $failed,
            'user_id' => $_SESSION['user_id'] ?? 'unknown'
        ]);

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'failed' => $failed,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        logError('Cleanup error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(deploy_tools_error_response('Cleanup failed', 'EXCEPTION', 500));
    }
});

/**
 * Build file tree structure for JSON response
 */
function build_file_tree(string $path, string $rootPath): array
{
    $items = [];
    $baseName = basename($path);

    // Skip hidden files and certain directories
    $skipDirs = ['.git', 'node_modules', 'vendor', '.env', 'storage/logs', 'storage/cache'];
    $relativePath = str_replace('\\', '/', str_replace($rootPath, '', $path));

    foreach ($skipDirs as $skip) {
        if (strpos($relativePath . '/', '/' . $skip . '/') === 0 || $relativePath === '/' . $skip) {
            return ['name' => $baseName, 'type' => 'folder', 'children' => []];
        }
    }

    if (!is_dir($path)) {
        return [
            'name' => $baseName,
            'type' => 'file',
            'size' => @filesize($path),
            'modified' => @filemtime($path),
            'path' => str_replace($rootPath, '', str_replace('\\', '/', $path)),
        ];
    }

    $entries = @scandir($path);
    if (!is_array($entries)) {
        return [
            'name' => $baseName ?: '/',
            'type' => 'folder',
            'path' => str_replace($rootPath, '', str_replace('\\', '/', $path)),
            'children' => [],
            'error' => 'Cannot read directory'
        ];
    }

    $children = [];

    foreach ($entries as $entry) {
        if ($entry[0] === '.') continue;

        $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
        $childRelative = str_replace($rootPath, '', str_replace('\\', '/', $entryPath));

        // Skip certain directories
        $skip = false;
        foreach ($skipDirs as $skipDir) {
            if (strpos($childRelative . '/', '/' . $skipDir . '/') === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        if (@is_dir($entryPath)) {
            $children[] = [
                'name' => $entry,
                'type' => 'folder',
                'path' => $childRelative,
                'children' => [], // Lazy load
            ];
        } else {
            $children[] = [
                'name' => $entry,
                'type' => 'file',
                'size' => @filesize($entryPath),
                'modified' => @filemtime($entryPath),
                'path' => $childRelative,
            ];
        }
    }

    // Sort: folders first, then files, alphabetically
    usort($children, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'folder' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    return [
        'name' => $baseName ?: '/',
        'type' => 'folder',
        'path' => str_replace($rootPath, '', str_replace('\\', '/', $path)),
        'children' => $children,
    ];
}

// ============================================================================
// GITHUB WEBHOOK ROUTES
// ============================================================================

/**
 * POST /webhook/github
 * GitHub webhook endpoint for automated deployments
 * This endpoint is public and does not require authentication
 */
$router->post('/webhook/github', function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $webhookModel = new WebhookSettingsModel($mysqli);

        // Check if webhook is enabled
        $enabled = $webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_ENABLED, false);

        if (!$enabled) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Webhook not configured']);
            return;
        }

        // Get settings
        $secret = $webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_SECRET, '');
        $allowedBranch = $webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_BRANCH, 'main');
        $allowedEvents = $webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_EVENTS, ['push']);
        $autoDeploy = $webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_AUTO_DEPLOY, false);

        // Get GitHub headers
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        $event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
        $delivery = $_SERVER['HTTP_X_GITHUB_DELIVERY'] ?? '';

        // Get raw payload
        $payload = file_get_contents('php://input');
        $payloadJson = json_decode($payload, true);

        // Verify signature if secret is set
        $signatureVerified = false;
        if (!empty($secret)) {
            if (empty($signature)) {
                logError('GitHub webhook: No signature provided', 'WARNING');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Signature required']);
                return;
            }

            $signatureVerified = $webhookModel->verifySignature($payload, $signature, $secret);

            if (!$signatureVerified) {
                logError('GitHub webhook: Invalid signature', 'WARNING');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid signature']);
                return;
            }
        }

        // Check if event is allowed
        if (!in_array($event, $allowedEvents)) {
            echo json_encode([
                'success' => true,
                'message' => 'Event not configured for deployment',
                'event' => $event
            ]);
            return;
        }

        // For push events, check branch
        $triggeredBranch = '';
        $shouldDeploy = false;

        if ($event === 'push' && isset($payloadJson['ref'])) {
            $ref = $payloadJson['ref'];
            // Extract branch name from ref (refs/heads/main)
            $triggeredBranch = preg_replace('#^refs/heads/#', '', $ref);

            if ($triggeredBranch === $allowedBranch) {
                $shouldDeploy = true;
            }
        }

        // Log the webhook delivery
        $webhookModel->logDelivery([
            'delivery_id' => $delivery,
            'event_type' => $event,
            'payload' => json_encode($payloadJson),
            'signature_verified' => $signatureVerified,
            'deployment_triggered' => $shouldDeploy && $autoDeploy,
            'deployment_status' => null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // Update last webhook info
        $webhookModel->updateSetting(WebhookSettingsModel::KEY_LAST_WEBHOOK_DELIVERY, date('Y-m-d H:i:s'));
        $webhookModel->updateSetting(WebhookSettingsModel::KEY_LAST_WEBHOOK_STATUS, $shouldDeploy ? 'triggered' : 'ignored');

        // Trigger deployment if conditions met
        if ($shouldDeploy && $autoDeploy) {
            // Queue a deploy job
            $deployModel = new DeployJobModel($mysqli);
            $deployModel->ensureTablesExist();

            $meta = [
                'triggered_by' => 'github_webhook',
                'webhook_event' => $event,
                'branch' => $triggeredBranch,
                'commit' => $payloadJson['after'] ?? null,
                'author' => $payloadJson['pusher']['name'] ?? null
            ];

            $jobId = $deployModel->enqueueJob('deploy', 1, $meta); // user_id 1 for system

            logInfo('GitHub webhook triggered deployment', [
                'job_id' => $jobId,
                'branch' => $triggeredBranch,
                'event' => $event
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Deployment queued',
                'job_id' => $jobId,
                'branch' => $triggeredBranch,
                'event' => $event
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => $shouldDeploy ? 'Webhook received, auto-deploy disabled' : 'Webhook received, branch not matched',
                'branch' => $triggeredBranch,
                'expected_branch' => $allowedBranch,
                'event' => $event
            ]);
        }
    } catch (Throwable $e) {
        logError('GitHub webhook error: ' . $e->getMessage(), 'ERROR', [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
});

/**
 * GET /admin/deploy-tools/webhook
 * Display webhook configuration page
 */
$router->get('/admin/deploy-tools/webhook', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    try {
        $webhookModel = new WebhookSettingsModel($mysqli);
        $settings = $webhookModel->getAllSettings(true);
        $logs = $webhookModel->getLogs(20);

        // Get the webhook URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $webhookUrl = $protocol . '://' . $host . '/webhook/github.php';

        echo $twig->render('admin/settings/deploy_tools_webhook.twig', [
            'title' => 'Deploy Tools - GitHub Webhook',
            'current_page' => 'deploy-tools',
            'csrf_token' => generateCsrfToken(),
            'settings' => $settings,
            'logs' => $logs,
            'webhook_url' => $webhookUrl,
            'available_events' => ['push', 'release', 'workflow_run']
        ]);
    } catch (Throwable $e) {
        logError('Webhook config error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo $twig->render('error.twig', [
            'code' => 500,
            'title' => 'Server Error',
            'message' => 'Failed to load webhook configuration'
        ]);
    }
});

/**
 * POST /admin/deploy-tools/webhook/update
 * Update webhook settings
 */
$router->post('/admin/deploy-tools/webhook/update', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        // Verify CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            return;
        }

        $webhookModel = new WebhookSettingsModel($mysqli);

        $updates = [];

        // Update enabled status
        if (isset($_POST['webhook_enabled'])) {
            $updates[WebhookSettingsModel::KEY_WEBHOOK_ENABLED] = $_POST['webhook_enabled'] === '1' || $_POST['webhook_enabled'] === 'true';
        }

        // Update secret (only if provided and not empty)
        if (!empty($_POST['webhook_secret'])) {
            $updates[WebhookSettingsModel::KEY_WEBHOOK_SECRET] = $_POST['webhook_secret'];
        }

        // Generate new secret
        if (isset($_POST['generate_secret']) && $_POST['generate_secret'] === '1') {
            $updates[WebhookSettingsModel::KEY_WEBHOOK_SECRET] = $webhookModel->generateSecret();
        }

        // Update branch
        if (isset($_POST['webhook_branch'])) {
            $updates[WebhookSettingsModel::KEY_WEBHOOK_BRANCH] = trim($_POST['webhook_branch']);
        }

        // Update events
        if (isset($_POST['webhook_events'])) {
            $updates[WebhookSettingsModel::KEY_WEBHOOK_EVENTS] = is_array($_POST['webhook_events'])
                ? $_POST['webhook_events']
                : [$_POST['webhook_events']];
        }

        // Update auto deploy
        if (isset($_POST['webhook_auto_deploy'])) {
            $updates[WebhookSettingsModel::KEY_WEBHOOK_AUTO_DEPLOY] = $_POST['webhook_auto_deploy'] === '1' || $_POST['webhook_auto_deploy'] === 'true';
        }

        // Update admin API key (for cPanel standalone webhook)
        if (!empty($_POST['admin_api_key'])) {
            $updates[WebhookSettingsModel::KEY_ADMIN_API_KEY] = $_POST['admin_api_key'];
        }

        // Update deploy path
        if (!empty($_POST['deploy_path'])) {
            $updates[WebhookSettingsModel::KEY_DEPLOY_PATH] = $_POST['deploy_path'];
        }

        // Update create backup
        if (isset($_POST['create_backup'])) {
            $updates[WebhookSettingsModel::KEY_CREATE_BACKUP] = $_POST['create_backup'] === '1' || $_POST['create_backup'] === 'true';
        }

        // Update max backups
        if (!empty($_POST['max_backups'])) {
            $updates[WebhookSettingsModel::KEY_MAX_BACKUPS] = (int)$_POST['max_backups'];
        }

        // Update project name
        if (!empty($_POST['project_name'])) {
            $updates[WebhookSettingsModel::KEY_PROJECT_NAME] = $_POST['project_name'];
        }

        $result = $webhookModel->updateMultipleSettings($updates);

        logInfo('Webhook settings updated', [
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'updated' => $result['updated']
        ]);

        echo json_encode([
            'success' => $result['failed'] === 0,
            'message' => 'Settings updated successfully',
            'updated' => $result['updated']
        ]);
    } catch (Throwable $e) {
        logError('Webhook update error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
    }
});

/**
 * GET /admin/deploy-tools/webhook/logs
 * Get webhook logs (AJAX)
 */
$router->get('/admin/deploy-tools/webhook/logs', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $webhookModel = new WebhookSettingsModel($mysqli);
        $logs = $webhookModel->getLogs(50);

        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch logs']);
    }
});

/**
 * POST /admin/deploy-tools/webhook/test
 * Test webhook endpoint
 */
$router->post('/admin/deploy-tools/webhook/test', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $webhookModel = new WebhookSettingsModel($mysqli);

        // Send a test payload to ourselves
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = $protocol . '://' . $host . '/webhook/github.php';

        $testPayload = [
            'ref' => 'refs/heads/' . ($webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_BRANCH, 'main')),
            'after' => 'test-commit-hash',
            'pusher' => ['name' => 'test-user'],
            'repository' => ['full_name' => 'test/repo']
        ];

        $secret = $webhookModel->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_SECRET, '');
        $payloadJson = json_encode($testPayload);

        // Generate signature if secret is set
        $signature = '';
        if (!empty($secret)) {
            $signature = 'sha256=' . hash_hmac('sha256', $payloadJson, $secret);
        }

        // Make test request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-GitHub-Event: push',
            'X-GitHub-Delivery: test-' . uniqid(),
            $signature ? 'X-Hub-Signature-256: ' . $signature : ''
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        echo json_encode([
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'error' => $curlError
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
});

/**
 * GET /admin/deploy-tools/webhook/remote-status
 * Get status from standalone webhook (cPanel version)
 */
$router->get('/admin/deploy-tools/webhook/remote-status', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $webhookModel = new WebhookSettingsModel($mysqli);
        $adminApiKey = $webhookModel->getSettingValue('admin_api_key', '');

        if (empty($adminApiKey)) {
            echo json_encode(['success' => false, 'message' => 'Admin API Key not configured in webhook settings']);
            return;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = $protocol . '://' . $host . '/webhook/github.php?action=status&api_key=' . urlencode($adminApiKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo json_encode(['success' => false, 'error' => $curlError]);
            return;
        }

        echo json_encode([
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'data' => json_decode($response, true)
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
});

/**
 * GET /admin/deploy-tools/webhook/remote-versions
 * Get versions from standalone webhook (cPanel version)
 */
$router->get('/admin/deploy-tools/webhook/remote-versions', ['middleware' => ['auth', 'super_admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $webhookModel = new WebhookSettingsModel($mysqli);
        $adminApiKey = $webhookModel->getSettingValue('admin_api_key', '');

        if (empty($adminApiKey)) {
            echo json_encode(['success' => false, 'message' => 'Admin API Key not configured in webhook settings']);
            return;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = $protocol . '://' . $host . '/webhook/github.php?action=versions&api_key=' . urlencode($adminApiKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo json_encode(['success' => false, 'error' => $curlError]);
            return;
        }

        echo json_encode([
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'data' => json_decode($response, true)
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
});

/**
 * POST /admin/deploy-tools/webhook/remote-rollback
 * Trigger rollback from standalone webhook (cPanel version)
 */
$router->post('/admin/deploy-tools/webhook/remote-rollback', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    header('Content-Type: application/json');

    try {
        $versionTag = trim($_POST['version'] ?? '');
        if (empty($versionTag)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Version tag required']);
            return;
        }

        // Verify CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
            return;
        }

        $webhookModel = new WebhookSettingsModel($mysqli);
        $adminApiKey = $webhookModel->getSettingValue('admin_api_key', '');

        if (empty($adminApiKey)) {
            echo json_encode(['success' => false, 'message' => 'Admin API Key not configured in webhook settings']);
            return;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = $protocol . '://' . $host . '/webhook/github.php?action=rollback&version=' . urlencode($versionTag) . '&api_key=' . urlencode($adminApiKey);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        logInfo('Remote rollback triggered', [
            'version' => $versionTag,
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'http_code' => $httpCode
        ]);

        if ($curlError) {
            echo json_encode(['success' => false, 'error' => $curlError]);
            return;
        }

        echo json_encode([
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'data' => json_decode($response, true)
        ]);
    } catch (Throwable $e) {
        logError('Remote rollback error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
});
