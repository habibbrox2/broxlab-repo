<?php

declare(strict_types=1);

// Enable output buffering to prevent "headers already sent" errors
ob_start();

// Set UTF-8 character encoding for all responses
header('Content-Type: text/html; charset=utf-8');


// ============================================================================
// Composer Autoload
// ============================================================================
require_once __DIR__ . '/../vendor/autoload.php';

// ============================================================================
// Optional extension stubs (used only for static analysis / IDE hints)
// These classes are provided by PHP extensions (redis, memcached) when installed.
// The stubs do not affect runtime behavior because the code paths are gated
// behind `extension_loaded(...)` checks.
// ============================================================================
if (!class_exists('Redis')) {
    class Redis
    {
        public function connect(string $host, int $port = 6379, float $timeout = 0.0): bool
        {
            return true;
        }

        public function keys(string $pattern): array
        {
            return [];
        }

        public function ttl(string $key): int
        {
            return -1;
        }

        public function del(string $key): int
        {
            return 0;
        }
    }
}

if (!class_exists('Memcached')) {
    class Memcached
    {
        public function addServer(string $host, int $port, int $weight = 0): bool
        {
            return true;
        }

        public function getAllKeys(): array|false
        {
            return [];
        }

        public function delete(string $key): bool
        {
            return true;
        }
    }
}

// ============================================================================
// Load Environment Variables
// ============================================================================
// .env is optional (may be absent in production / distributed packages)
// Use safeLoad() so missing env file does not cause a fatal error.
// If you need custom env vars, create a `.env` file in the project root.
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// ============================================================================
// Timezone (before anything time-related)
// ============================================================================
$timezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Dhaka';
if (!empty($timezone)) {
    date_default_timezone_set($timezone);
}

// ============================================================================
// Load All Constants
// ============================================================================
require_once dirname(__DIR__) . '/Config/Constants.php';

// ============================================================================
// Ensure Required Directories
// ============================================================================
foreach ([CACHE_DIR, TEMP_DIR, LOG_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// ============================================================================
// Error Logging System
// ============================================================================
require_once BASE_PATH . 'app/Helpers/ErrorLogging.php';
initializeErrorLogging();

// ============================================================================
// Helper Functions (ALL PRESERVED)
// ============================================================================

function secureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps =
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => $isHttps,
            'use_strict_mode' => true,
        ]);
    }
}

function renderError(int $code, string $message): void
{
    http_response_code($code);
    global $twig;

    if (isset($twig)) {
        echo $twig->render('error.twig', [
            'code' => $code,
            'message' => $message,
        ]);
    } else {
        echo $message;
    }
    exit;
}

// ============================================================================
// Initialize Core
// ============================================================================
secureSession();

// ============================================================================
// Class Autoload (fallback – preserved)
// ============================================================================
spl_autoload_register(function (string $className): void {

    // 🚫 Never interfere with Composer / vendor namespaces
    if (str_contains($className, '\\')) {
        return;
    }

    $classFile = BASE_PATH . "app/Models/{$className}.php";

    if (file_exists($classFile)) {
        require_once $classFile;
    }
});


// ============================================================================
// Recursive PHP Loader (preserved)
// ============================================================================
function requireAllPhpFiles($dir)
{
    if (!is_dir($dir)) {
        logError("Directory not found: {$dir}");
        return;
    }

    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            requireAllPhpFiles($file);
        } elseif (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            try {
                require_once $file;
            } catch (Throwable $e) {
                logError('Load Error [' . basename($file) . ']: ' . $e->getMessage());
            }
        }
    }
}

// ============================================================================
// Load Core Configs (order matters)
// ============================================================================
require_once BASE_PATH . 'Config/Db.php';
require_once BASE_PATH . 'Config/Twig.php';
require_once BASE_PATH . 'app/Routes/Router.php';

// Other configs
require_once BASE_PATH . 'Config/Functions.php';
require_once BASE_PATH . 'Config/UploadConfig.php';

// ============================================================================
// Load Helpers (Recursive)
// ============================================================================
requireAllPhpFiles(BASE_PATH . 'app/Helpers');

// Upload directories (functions preserved)
// Static-analysis helper: declare no-op stub so tools like Intelephense don't flag missing function
if (!function_exists('ensureUploadDirectories')) {
    function ensureUploadDirectories(): void {}
}
if (function_exists('initializeUploadDirectories')) {
    initializeUploadDirectories();
}
if (function_exists('ensureUploadDirectories')) {
    ensureUploadDirectories();
}

// ============================================================================
// Cache & Storage Cleanup (functions preserved)
// ============================================================================
function cleanCache(AppSettings $settingsModel): void
{
    $cacheEnabled = $settingsModel->get('enable_cache', 0);
    $driver = $settingsModel->get('cache_driver', 'file');
    $lifetime = (int)$settingsModel->get('cache_lifetime', 3600);

    if (!$cacheEnabled) return;

    switch ($driver) {
        case 'file':
            if (is_dir(CACHE_DIR)) {
                $now = time();
                foreach (glob(CACHE_DIR . '/*') as $file) {
                    if (is_file($file) && ($now - filemtime($file)) > $lifetime) {
                        @unlink($file);
                    }
                }
            }
            break;

        case 'redis':
            if (extension_loaded('redis') && class_exists('Redis')) {
                try {
                    /** @var \Redis $redis */
                    /** @psalm-suppress UndefinedClass */
                    /** @phpstan-ignore-next-line */
                    $redis = new \Redis();
                    $redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int)($_ENV['REDIS_PORT'] ?? 6379));
                    foreach ($redis->keys('*') as $key) {
                        if ($redis->ttl($key) < $lifetime) {
                            $redis->del($key);
                        }
                    }
                } catch (Throwable $e) {
                    logError('Redis Cleanup Error: ' . $e->getMessage());
                }
            }
            break;

        case 'memcached':
            if (extension_loaded('memcached') && class_exists('Memcached')) {
                try {
                    $mem = new \Memcached();
                    $mem->addServer($_ENV['MEMCACHED_HOST'] ?? '127.0.0.1', (int)($_ENV['MEMCACHED_PORT'] ?? 11211));
                    $keys = $mem->getAllKeys();
                    if ($keys) {
                        foreach ($keys as $key) {
                            $mem->delete($key);
                        }
                    }
                } catch (Throwable $e) {
                    logError('Memcached Cleanup Error: ' . $e->getMessage());
                }
            }
            break;
    }
}

// remove old temp and log files based on retention setting
function cleanStorage(AppSettings $settingsModel): void
{
    // retention in seconds (default 7 days)
    $retention = (int)$settingsModel->get('storage_retention_seconds', 604800);
    if ($retention <= 0) {
        return;
    }
    $now = time();
    foreach ([TEMP_DIR, LOG_DIR] as $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob($dir . '*') as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $retention) {
                @unlink($file);
            }
        }
    }
}

if (isset($appSettings) && $appSettings instanceof AppSettings) {
    cleanCache($appSettings);
    // periodically remove old files from temporary and log directories
    if (function_exists('cleanStorage')) {
        cleanStorage($appSettings);
    }
}

// ============================================================================
// Middleware System (preserved)
// ============================================================================
$middlewares = [];

function register_middleware(string $name, callable $callback): void
{
    global $middlewares;
    $middlewares[$name] = $callback;
}

function run_middleware(string $name, array $ctx = []): bool
{
    global $middlewares;
    return isset($middlewares[$name])
        ? $middlewares[$name]($ctx) !== false
        : true;
}


require_once BASE_PATH . 'app/Middleware/Middleware.php';



// Global rate limit
run_middleware('rate_limit', [
    'scope' => 'global',
    'limit' => 120,
    'window' => 60,
]);

// ============================================================================
// Maintenance Mode
// ============================================================================
$settings = new AppSettings($mysqli);
if ($settings->get('maintenance_mode', 0) === 1 && !IS_MAINTENANCE) {
    http_response_code(503);
    echo isset($twig)
        ? $twig->render('maintenance.twig')
        : 'Server under maintenance';
    exit;
}

$controllerFiles = glob(BASE_PATH . 'app/Controllers/*.php') ?: [];
usort($controllerFiles, static function (string $a, string $b): int {
    $priority = [
        'PaymentController.php' => -1000,
    ];

    $aBase = basename($a);
    $bBase = basename($b);
    $aPriority = $priority[$aBase] ?? 0;
    $bPriority = $priority[$bBase] ?? 0;

    if ($aPriority !== $bPriority) {
        return $aPriority <=> $bPriority;
    }

    return strcmp($aBase, $bBase);
});

foreach ($controllerFiles as $controller) {
    require_once $controller;
}
// ============================================================================
// Dispatch Router
// ============================================================================
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    logError('Routing Error: ' . $e->getMessage());
    renderError(500, 'Routing Error');
}

<<<<<<< HEAD
// Flush output buffer
ob_end_flush();
=======
// Flush output buffer (only if a buffer exists)
if (ob_get_level() > 0) {
    ob_end_flush();
}
>>>>>>> temp_branch
