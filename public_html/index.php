<?php

declare(strict_types=1);

// ============================================================================
// SERVE STATIC FILES DIRECTLY (Before any processing)
// ============================================================================
// Check if the request is for a static file. If so, serve it directly
// and exit without going through the PHP routing system.
$requestPath = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string from path
$requestPath = parse_url($requestPath, PHP_URL_PATH);

// Define allowed static asset directories
$staticDirs = ['/assets/', '/uploads/', '/rtceditor/', '/public/', '/favicon.ico', '/robots.txt', '/sitemap.xml'];

$isStaticFile = false;
foreach ($staticDirs as $dir) {
    if (str_starts_with($requestPath, $dir)) {
        $isStaticFile = true;
        break;
    }
}

// If it's a static file, check if it exists and serve it
if ($isStaticFile) {
    $filePath = __DIR__ . $requestPath;
    
    // Security: Prevent path traversal attacks
    $realPath = realpath($filePath);
    $baseDir = realpath(__DIR__);
    
    if ($realPath && $baseDir && str_starts_with($realPath, $baseDir) && is_file($realPath)) {
        // Determine MIME type
        $mimeTypes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'xml'   => 'application/xml; charset=utf-8',
            'txt'   => 'text/plain; charset=utf-8',
        ];
        
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        
        // For CSS and JS, add more aggressive caching
        if (in_array($ext, ['css', 'js'])) {
            header('Cache-Control: public, max-age=31536000, immutable');
        }
        
        readfile($realPath);
        exit;
    }
    
    // Static file requested but not found - return 404
    http_response_code(404);
    exit;
}

// Enable output buffering to prevent "headers already sent" errors
ob_start();

// Set UTF-8 character encoding for all responses (HTML only)
header('Content-Type: text/html; charset=utf-8');


// ============================================================================
// Composer Autoload
// ============================================================================
require_once dirname(__DIR__, 1) . '/vendor/autoload.php';

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
//
// IMPORTANT: createImmutable() only writes to $_ENV/$_SERVER, not PHP's getenv().
// Use createUnsafeImmutable() to also write via putenv() so getenv() works.
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__));
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
require_once dirname(__DIR__) . '/app/Models/Permissions.php';

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
        $sessionDir = TEMP_DIR . 'sessions' . DIRECTORY_SEPARATOR;
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0775, true);
        }
        @ini_set('session.save_path', $sessionDir);
        session_save_path($sessionDir);

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
// Language Detection
// ============================================================================
require_once BASE_PATH . "app/Helpers/LanguageHelper.php";
LanguageHelper::getCurrentLang(); // This sets the session if needed

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
function requireAllPhpFiles(string $dir): void
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
require_once BASE_PATH . 'app/Router/Router.php';

/** @var mysqli $mysqli */

// Other configs
require_once BASE_PATH . 'Config/Functions.php';
require_once BASE_PATH . 'Config/UploadConfig.php';

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    logError('Database connection handle was not initialized by Config/Db.php');
    renderError(500, 'Database initialization error');
}

// ============================================================================
// Load Helpers (Recursive)
// ============================================================================
requireAllPhpFiles(BASE_PATH . 'app/Helpers');
requireAllPhpFiles(BASE_PATH . 'app/Services');

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

if (isset($appSettings) && $appSettings instanceof AppSettings) {
    cleanCache($appSettings);
}

if (class_exists('StorageCleanupHelper')) {
    StorageCleanupHelper::setEchoLogs(false);
    StorageCleanupHelper::runAutomaticCleanupIfDue();
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
    
    // Handle permission: prefixed middleware names (e.g., 'permission:users.delete')
    if (strpos($name, 'permission:') === 0) {
        return run_permission_middleware(substr($name, strlen('permission:')), $ctx);
    }
    
    return isset($middlewares[$name])
        ? $middlewares[$name]($ctx) !== false
        : true;
}

function run_permission_middleware(string $permission, array $ctx = []): bool
{
    global $userModel;
    
    $userId = AuthManager::getCurrentUserId();
    
    if (!$userId) {
        logMiddlewareReject('permission', 'NOT_AUTHENTICATED', ['permission' => $permission]);
        json_response(['success' => false, 'error' => 'Authentication required'], 401);
        return false;
    }
    
    // Super admins bypass permission checks
    if ($userModel && $userModel->isSuperAdmin($userId)) {
        return true;
    }
    
    if (!$userModel || !$userModel->hasPermission($userId, $permission)) {
        logMiddlewareReject('permission', 'PERMISSION_DENIED', [
            'permission' => $permission,
            'user_id' => $userId
        ]);
        $currentPath = strtolower((string)($ctx['uri'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isApiRequest = (strpos($currentPath, '/api/') === 0)
            || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
            || (strpos($accept, 'application/json') !== false);
        
        if ($isApiRequest) {
            json_response(['success' => false, 'error' => "Permission denied: {$permission}"], 403);
        } else {
            showMessage("Access denied. Required permission: {$permission}", 'danger');
            redirect('/');
        }
        return false;
    }
    
    return true;
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

// Routes are now registered in their respective controller files.
// The app/Router directory only contains the Router class.
// No separate route files to load.

// ============================================================================
// Register Routes from Controllers
// ============================================================================
// Routes auto-register when controller files are loaded via require_once above
// This keeps route definitions close to their implementations
global $router;

try {
    //error_log("Dispatching: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
    $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($requestMethod === 'POST') {
        $overrideMethod = strtoupper(trim((string)($_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '')));
        if (in_array($overrideMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
            $requestMethod = $overrideMethod;
        }
    }

    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '/', PHP_URL_PATH) ?: '/';
    $router->dispatch($requestMethod, $requestUri);
} catch (Throwable $e) {
    // Handle ForbiddenException with proper 403 response
    if ($e instanceof ForbiddenException) {
        logError('Forbidden: ' . $e->getMessage() . ' | User: ' . (($ctx = $e->getContext()) ? ($ctx['user_id'] ?? 'unknown') : 'unknown'));
        $e->respond();
        exit; // Safety: ensure no fallthrough
    }
    logError('Routing Error: ' . $e->getMessage());
    renderError(500, 'Routing Error');
}

// Flush output buffer (only if a buffer exists)
if (ob_get_level() > 0) {
    ob_end_flush();
}
