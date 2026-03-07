<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/ErrorLogging.php';
require_once __DIR__ . '/../app/Helpers/BreadcrumbHelper.php';
require_once __DIR__ . '/../app/Models/UserModel.php';
require_once __DIR__ . '/../app/Models/AppSettings.php';
require_once __DIR__ . '/Functions.php';
require_once __DIR__ . '/RteCacheConfig.php';

// ============================================================
// ENVIRONMENT HELPERS
// ============================================================

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        // 1️⃣ $_ENV check
        if (isset($_ENV[$key])) {
            return normalizeEnvValue($_ENV[$key]);
        }

        // 2️⃣ $_SERVER check (Apache / Nginx compatibility)
        if (isset($_SERVER[$key])) {
            return normalizeEnvValue($_SERVER[$key]);
        }

        // 3️⃣ Native getenv()
        $value = getenv($key);
        if ($value !== false) {
            return normalizeEnvValue($value);
        }

        return $default;
    }
}

if (!function_exists('normalizeEnvValue')) {
    function normalizeEnvValue($value) {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        $value = trim($value);

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('brox_is_development_env')) {
    function brox_is_development_env(): bool
    {
        return env('APP_ENV', 'production') === 'development';
    }
}

if (!function_exists('brox_project_root')) {
    function brox_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('brox_latest_mtime')) {
    function brox_latest_mtime(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        if (is_file($path)) {
            return (int)(@filemtime($path) ?: 0);
        }

        $latest = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $mtime = (int)$item->getMTime();
                    if ($mtime > $latest) {
                        $latest = $mtime;
                    }
                }
            }
        } catch (Throwable $e) {
            return 0;
        }

        return $latest;
    }
}

if (!function_exists('brox_try_run_dev_build')) {
    function brox_try_run_dev_build(string $commandKey, string $command, string $cwd): void
    {
        static $attempted = [];

        if (isset($attempted[$commandKey])) {
            return;
        }
        $attempted[$commandKey] = true;

        if (!brox_is_development_env()) {
            return;
        }

        $lockDir = brox_project_root() . '/storage/cache/dev-build-locks';
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            return;
        }

        $lockPath = $lockDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $commandKey) . '.lock';
        $handle = @fopen($lockPath, 'c+');
        if (!$handle) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }

            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($command, $descriptorSpec, $pipes, $cwd);
            if (!is_resource($process)) {
                return;
            }

            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    stream_set_blocking($pipe, true);
                }
            }

            $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
            $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';

            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                error_log("[dev-build] command failed: {$commandKey}; exit={$exitCode}; stderr={$stderr}; stdout={$stdout}");
            }
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}

if (!function_exists('brox_resolve_asset_for_development')) {
    function brox_resolve_asset_for_development(string $url): string
    {
        if (!brox_is_development_env()) {
            return $url;
        }

        $projectRoot = brox_project_root();

        if (strpos($url, '/assets/js/dist/') === 0) {
            $relative = substr($url, strlen('/assets/js/dist/'));
            $sourceUrl = '/assets/js/' . ltrim($relative, '/');
            $sourceAbs = $projectRoot . '/public_html' . $sourceUrl;

            if (file_exists($sourceAbs)) {
                return $sourceUrl;
            }

            if ($url === '/assets/js/dist/datepicker.js') {
                $datepickerUrl = '/assets/datepicker/datepicker.js';
                $datepickerAbs = $projectRoot . '/public_html' . $datepickerUrl;
                if (file_exists($datepickerAbs)) {
                    return $datepickerUrl;
                }
            }

            return $url;
        }

        $buildMap = [
            '/assets/ai-assistant/dist/' => [
                'sourceRoot' => $projectRoot . '/public_html/assets/ai-assistant',
                'distRoot' => $projectRoot . '/public_html/assets/ai-assistant/dist',
                'commandKey' => 'assistants',
                'command' => 'npm run build:assistants',
            ],
            '/assets/firebase/v2/dist/' => [
                'sourceRoot' => $projectRoot . '/public_html/assets/firebase/v2',
                'distRoot' => $projectRoot . '/public_html/assets/firebase/v2/dist',
                'commandKey' => 'firebase-v2',
                'command' => 'npm run build:firebase:v2',
            ],
        ];

        foreach ($buildMap as $prefix => $config) {
            if (strpos($url, $prefix) !== 0) {
                continue;
            }

            $relative = substr($url, strlen($prefix));
            $distAbs = $config['distRoot'] . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $sourceAbs = $config['sourceRoot'] . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            $distMtime = brox_latest_mtime($distAbs);
            $sourceMtime = brox_latest_mtime($sourceAbs);

            if ($sourceMtime > $distMtime || !file_exists($distAbs)) {
                brox_try_run_dev_build($config['commandKey'], $config['command'], $projectRoot);
            }

            return $url;
        }

        return $url;
    }
}

// ============================================================
// FLASH MESSAGE HANDLER
// ============================================================

if (!function_exists('getFlash')) {
    function getFlash(?array &$session): ?array {
        if (!is_array($session)) {
            $session = [];
        }

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('secureSession')) {
                secureSession();
            } elseif (!headers_sent()) {
                session_start();
            }
        }
        
        // First check $_SESSION, fallback to passed session array
        if (!empty($_SESSION['flash_message'])) {
            $msg = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $msg;
        }
        
        if (!empty($session['flash_message'])) {
            $msg = $session['flash_message'];
            unset($session['flash_message']);
            return $msg;
        }
        
        return null;
    }
}

// ============================================================
// USER LOADER
// ============================================================

function loadUser(mysqli $mysqli, ?int $userId): array
{
    $defaults = [
        'id' => 0,
        'is_authenticated' => false,
        'username' => 'Guest',
        'profile_pic' => '/assets/images/default-avatar.png',
        'role' => 'guest',
        'roles' => [],
        'permissions' => [],
    ];

    if (!$userId) {
        return $defaults;
    }

    try {
        $userModel = new UserModel($mysqli);
        $profile = $userModel->loadUserById($userId);

        if (!$profile || !is_array($profile)) {
            logDebug('User profile not found', ['user_id' => $userId]);
            return $defaults;
        }

        $user = [
            'id' => (int) $userId,
            'is_authenticated' => true,
            'role' => $profile['role'] ?? 'guest',
        ];

        foreach ($profile as $key => $value) {
            // Skip protected keys
            if (in_array($key, ['id', 'role'], true)) {
                continue;
            }

            // Profile picture handling
            if ($key === 'profile_pic') {
                if (!empty($value)) {
                    $user[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                } else {
                    $user[$key] = $defaults['profile_pic'];
                }
                continue;
            }

            // Normal field handling
            if (is_string($value)) {
                $user[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $user[$key] = $value;
            }
        }

        // Merge with defaults (user data overrides defaults)
        return array_merge($defaults, $user);

    } catch (Throwable $e) {
        logError(
            'Error loading user profile: ' . $e->getMessage(),
            'WARNING',
            [
                'user_id' => $userId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        );

        return $defaults;
    }
}

// ============================================================
// TWIG INITIALIZATION
// ============================================================

function initializeTwig(mysqli $mysqli, ?array &$session, string $configUrl): \Twig\Environment
{
    global $settingsModel;
    
    try {
        if (!is_array($session)) {
            $session = [];
        }

        secureSession();

        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../app/Views');

        // Fetch app settings (cached inside AppSettings model)
        $appSettings = $settingsModel->getSettings();

        // Twig cache control from settings
        $twigCache = false;
        if (!empty($appSettings['enable_cache']) && $appSettings['enable_cache'] != '0') {
            $cacheDir = CACHE_DIR . 'twig' . DIRECTORY_SEPARATOR;
            
            // Ensure cache directory exists with proper permissions
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }
            
            // Verify directory is writable
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                $twigCache = $cacheDir;
                logDebug("Twig cache enabled - cache_dir: {$cacheDir}");
            } else {
                $twigCache = false;
                logDebug("WARNING: Twig cache directory is not writable: {$cacheDir}");
            }
        }

        // Twig debug control from MAINTENANCE_MODE
        // MAINTENANCE_MODE=0 → debug ON
        // MAINTENANCE_MODE=1 → debug OFF
        $maintenanceMode = (int) env('MAINTENANCE_MODE', 0);
        $twigDebug = ($maintenanceMode === 0);

        $twig = new \Twig\Environment($loader, [
            'cache' => $twigCache,
            'debug' => $twigDebug,
            'auto_reload' => true,
        ]);

        if ($twigDebug) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
            logDebug("Twig debug mode enabled");
        }

        $twig->addExtension(new \Twig\Extension\StringLoaderExtension());
        
        // ============================================================
        // FILTERS
        // ============================================================
        
        // Max filter (numbers or arrays)
        $twig->addFilter(new \Twig\TwigFilter('max', function ($value, $compare = null) {
            if (is_array($value)) {
                return !empty($value) ? max($value) : null;
            }

            if (is_numeric($value) && is_numeric($compare)) {
                return max($value, $compare);
            }

            return $value;
        }));

        // Min filter (numbers or arrays)
        $twig->addFilter(new \Twig\TwigFilter('min', function ($value, $compare = null) {
            if (is_array($value)) {
                return !empty($value) ? min($value) : null;
            }

            if (is_numeric($value) && is_numeric($compare)) {
                return min($value, $compare);
            }

            return $value;
        }));

        // Currency filter (BDT)
        $twig->addFilter(new \Twig\TwigFilter('currency', function ($number, $symbol = '৳') {
            $number = (float) $number;
            return $symbol . number_format($number, 2, '.', ',');
        }));
        
        // Unique filter (array or string)
        $twig->addFilter(new \Twig\TwigFilter('unique', function ($value) {
            if (is_array($value)) {
                return array_values(array_unique($value, SORT_REGULAR));
            }

            if (is_string($value)) {
                $words = preg_split('/\s+/', trim($value));
                $uniqueWords = array_unique($words);
                return implode(' ', $uniqueWords);
            }

            return $value;
        }));
        
        // String replace filter
        $twig->addFilter(new \Twig\TwigFilter('str_replace', function ($search, $replace, $subject) {
            return str_replace($search, $replace, $subject);
        }));

        // Truncate text
        $twig->addFilter(new \Twig\TwigFilter('truncate', function ($text, $length = 100, $suffix = '...') {
            if ($text === null || $text === '') {
                return $text;
            }
            if (strlen($text) <= $length) {
                return $text;
            }
            return substr($text, 0, $length) . $suffix;
        }));
        
        // Integer cast filter
        $twig->addFilter(new \Twig\TwigFilter('int', function ($value, $default = 0) {
            if (is_numeric($value)) {
                return (int) $value;
            }
            return (int) $default;
        }));

        // File size format
        $twig->addFilter(new \Twig\TwigFilter('filesizeformat', function ($bytes, $decimals = 2) {
            $bytes = (float) $bytes;
            
            if ($bytes <= 0) {
                return '0 B';
            }
            
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
            $factor = floor((strlen((string) $bytes) - 1) / 3);
            
            return sprintf(
                "%.{$decimals}f %s",
                $bytes / pow(1024, $factor),
                $units[$factor] ?? 'B'
            );
        }));
        
        // Date only filter
        $twig->addFilter(new \Twig\TwigFilter('date', function ($date, $format = 'm-d-Y') {
            if (empty($date)) {
                return '';
            }

            try {
                if ($date instanceof DateTime) {
                    return $date->format($format);
                }
                return (new DateTime($date))->format($format);
            } catch (Exception $e) {
                return $date;
            }
        }));

        // Date & time filter
        $twig->addFilter(new \Twig\TwigFilter('datetime', function ($date, $format = 'm-d-Y h:i A') {
            if (empty($date)) {
                return '';
            }

            try {
                if ($date instanceof DateTime) {
                    return $date->format($format);
                }
                return (new DateTime($date))->format($format);
            } catch (Exception $e) {
                return $date;
            }
        }));
        
        // Bengali date filter
        $twig->addFilter(new \Twig\TwigFilter('date_bn', function ($date, $format = 'd F Y') {
            if (empty($date)) {
                return '';
            }

            try {
                $dt = $date instanceof DateTime ? $date : new DateTime($date);
                $formatted = $dt->format($format);

                // Convert month names to Bengali
                $months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                
                foreach ($months as $month) {
                    $formatted = str_replace($month, enToBnMonth($month), $formatted);
                }

                // Convert digits to Bengali
                return enToBnDigits($formatted);

            } catch (Exception $e) {
                return $date;
            }
        }));
        
        // Bengali datetime filter
        $twig->addFilter(new \Twig\TwigFilter('datetime_bn', function ($date, $format = 'd F Y, h:i A') {
            if (empty($date)) {
                return '';
            }

            try {
                $dt = $date instanceof DateTime ? $date : new DateTime($date);
                $formatted = $dt->format($format);

                $months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                
                foreach ($months as $month) {
                    $formatted = str_replace($month, enToBnMonth($month), $formatted);
                }

                return enToBnDigits($formatted);

            } catch (Exception $e) {
                return $date;
            }
        }));
        
        // Relative time (e.g., "2 hours ago")
        $twig->addFilter(new \Twig\TwigFilter('time_ago', function ($datetime) {
            if (empty($datetime)) {
                return '';
            }
            
            try {
                $time = is_numeric($datetime) ? $datetime : strtotime($datetime);
                $diff = time() - $time;
                
                if ($diff < 60) return 'just now';
                if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
                if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
                if ($diff < 604800) return floor($diff / 86400) . ' days ago';
                if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
                if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
                return floor($diff / 31536000) . ' years ago';
            } catch (Exception $e) {
                return $datetime;
            }
        }));
        
        // Slug filter
        $twig->addFilter(new \Twig\TwigFilter('slug', function ($text) {
            $text = preg_replace('~[^\pL\d]+~u', '-', $text);
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
            $text = preg_replace('~[^-\w]+~', '', $text);
            $text = trim($text, '-');
            $text = preg_replace('~-+~', '-', $text);
            return strtolower($text);
        }));
        
        // URL encode filter
        $twig->addFilter(new \Twig\TwigFilter('url_encode', function ($text) {
            return urlencode($text);
        }));
        
        // JSON decode filter
        $twig->addFilter(new \Twig\TwigFilter('json_decode', function ($json, $assoc = true) {
            return json_decode($json, $assoc);
        }));
        
        // Strip tags filter
        $twig->addFilter(new \Twig\TwigFilter('strip_tags', function ($text, $allowedTags = '') {
            return strip_tags($text, $allowedTags);
        }));
        
        // Highlight search terms
        $twig->addFilter(new \Twig\TwigFilter('highlight', function ($text, $search) {
            if (empty($search)) {
                return $text;
            }
            return preg_replace(
                '/(' . preg_quote($search, '/') . ')/i',
                '<mark>$1</mark>',
                $text
            );
        }, ['is_safe' => ['html']]));
        
        // Number format filter
        $twig->addFilter(new \Twig\TwigFilter('number_format', function ($number, $decimals = 0, $decPoint = '.', $thousandsSep = ',') {
            return number_format((float) $number, $decimals, $decPoint, $thousandsSep);
        }));
        
        // Percentage filter
        $twig->addFilter(new \Twig\TwigFilter('percentage', function ($number, $decimals = 2) {
            return number_format((float) $number, $decimals) . '%';
        }));
        
        // Nl2br filter (newlines to <br>)
        $twig->addFilter(new \Twig\TwigFilter('nl2br', function ($text) {
            return nl2br($text);
        }, ['is_safe' => ['html']]));
        
        // Excerpt filter (smart truncation at word boundary)
        $twig->addFilter(new \Twig\TwigFilter('excerpt', function ($text, $length = 150, $suffix = '...') {
            if (strlen($text) <= $length) {
                return $text;
            }
            $truncated = substr($text, 0, $length);
            $lastSpace = strrpos($truncated, ' ');
            return ($lastSpace !== false ? substr($truncated, 0, $lastSpace) : $truncated) . $suffix;
        }));

        // JSON encode filter for structured data
        $twig->addFilter(new \Twig\TwigFilter('json', function ($value) {
            return json_encode(
                $value,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            );
        }));
        
        // Role badge filter
        $twig->addFilter(new \Twig\TwigFilter('role_badge', function ($roleName) {
            $badges = [
                'super_admin' => '<span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i>Super Admin</span>',
                'admin' => '<span class="badge bg-warning"><i class="bi bi-shield-check me-1"></i>Admin</span>',
                'moderator' => '<span class="badge bg-info"><i class="bi bi-shield me-1"></i>Moderator</span>',
                'user' => '<span class="badge bg-secondary"><i class="bi bi-person me-1"></i>User</span>',
            ];
            return $badges[$roleName] ?? '<span class="badge bg-light text-dark">' . ucfirst($roleName) . '</span>';
        }, ['is_safe' => ['html']]));
        
        // Role color filter
        $twig->addFilter(new \Twig\TwigFilter('role_color', function ($roleName) {
            $colors = [
                'super_admin' => 'danger',
                'admin' => 'warning',
                'moderator' => 'info',
                'user' => 'secondary',
            ];
            return $colors[$roleName] ?? 'light';
        }));
        // ============================================================
        // BREADCRUMB FILTERS
        // ============================================================
        
        // Sanitize breadcrumbs filter for JSON-LD schema validation
        $twig->addFilter(new \Twig\TwigFilter('sanitize_breadcrumbs', function ($breadcrumbs) {
            // Get base URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            
            // Call helper function
            return sanitizeBreadcrumbs($breadcrumbs, $baseUrl);
        }));        
        // ============================================================
        // FUNCTIONS
        // ============================================================
        
        // Media URL helper
        $twig->addFunction(new \Twig\TwigFunction('media_url', function ($filePath) {
            if (empty($filePath)) {
                return '';
            }
            return '/uploads/media/' . ltrim($filePath, '/');
        }));
        
        // Thumbnail URL helper
        $twig->addFunction(new \Twig\TwigFunction('thumbnail_url', function ($thumbnailPath) {
            if (!$thumbnailPath) {
                return null;
            }
            return '/uploads/media/' . ltrim($thumbnailPath, '/');
        }));
        
        // Format file size
        $twig->addFunction(new \Twig\TwigFunction('format_file_size', function ($bytes) {
            $sizes = ["B", "KB", "MB", "GB", "TB"];
            if ($bytes == 0) {
                return "0 B";
            }
            $i = floor(log($bytes, 1024));
            return round($bytes / pow(1024, $i), 2) . " " . $sizes[$i];
        }));
        
        // Get file icon
        $twig->addFunction(new \Twig\TwigFunction('get_file_icon', function ($mimeType) {
            $iconMap = [
                'image' => '🖼️',
                'video' => '🎥',
                'audio' => '🎵',
                'pdf' => '📄',
                'word' => '📝',
                'excel' => '📊',
                'powerpoint' => '📈',
                'archive' => '📦',
                'code' => '💻',
                'text' => '📄'
            ];
            
            if (strpos($mimeType, 'image') !== false) return $iconMap['image'];
            if (strpos($mimeType, 'video') !== false) return $iconMap['video'];
            if (strpos($mimeType, 'audio') !== false) return $iconMap['audio'];
            if (strpos($mimeType, 'pdf') !== false) return $iconMap['pdf'];
            if (strpos($mimeType, 'word') !== false) return $iconMap['word'];
            if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) return $iconMap['excel'];
            if (strpos($mimeType, 'presentation') !== false) return $iconMap['powerpoint'];
            if (strpos($mimeType, 'zip') !== false || strpos($mimeType, 'rar') !== false) return $iconMap['archive'];
            if (strpos($mimeType, 'text') !== false) return $iconMap['text'];
            
            return '📎';
        }));
        
        // Asset URL helper (with version/cache busting)
        $twig->addFunction(new \Twig\TwigFunction('asset', function ($path, $version = true) {
            // Clean path (ensure it starts with /)
            $url = '/' . ltrim($path, '/');
            $url = brox_resolve_asset_for_development($url);

            // Add cache-busting version if file exists
            if ($version) {
                // Get document root safely
                $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
                $fullPath = $documentRoot . $url;
                
                if (file_exists($fullPath)) {
                    $filemtime = @filemtime($fullPath);
                    if ($filemtime !== false) {
                        $url .= '?v=' . $filemtime;
                    }
                }
            }
            
            return $url;
        }));
        
        // Route URL generator
        $twig->addFunction(new \Twig\TwigFunction('route', function ($name, $params = []) {
            global $router;
            if (isset($router) && method_exists($router, 'route')) {
                return $router->route($name, $params);
            }
            return '#';
        }));
        
        // Path function (alias for route)
        $twig->addFunction(new \Twig\TwigFunction('path', function ($name, $params = []) {
            global $router;
            if (isset($router) && method_exists($router, 'route')) {
                return $router->route($name, $params);
            }
            return '#';
        }));
        
        // Current route checker - checks if current path contains the route name
        $twig->addFunction(new \Twig\TwigFunction('is_route', function ($routeName) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            
            // Normalize paths for comparison
            $routeName = trim($routeName, '/');
            $currentPath = trim($currentPath, '/');
            
            // Exact match or starts with check
            return $currentPath === $routeName || strpos($currentPath, $routeName) === 0;
        }));
        
        // ============================================================
        // RBAC FUNCTIONS (Role-Based Access Control)
        // ============================================================
        
        // Check if user has permission
        $twig->addFunction(new \Twig\TwigFunction('can', function ($permission, $userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0) {
                return false;
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->hasPermission($userId, $permission);
            } catch (Throwable $e) {
                logError('Permission check failed: ' . $e->getMessage(), 'WARNING');
                return false;
            }
        }));
        
        // Check if user has any permission
        $twig->addFunction(new \Twig\TwigFunction('canAny', function ($permissions, $userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0 || !is_array($permissions)) {
                return false;
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->hasAnyPermission($userId, $permissions);
            } catch (Throwable $e) {
                logError('Permission check failed: ' . $e->getMessage(), 'WARNING');
                return false;
            }
        }));
        
        // Check if user has role
        $twig->addFunction(new \Twig\TwigFunction('hasRole', function ($roleName, $userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0) {
                return false;
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->hasRole($userId, $roleName);
            } catch (Throwable $e) {
                logError('Role check failed: ' . $e->getMessage(), 'WARNING');
                return false;
            }
        }));
        
        // Check if user has any of multiple roles
        $twig->addFunction(new \Twig\TwigFunction('hasAnyRole', function ($roleNames, $userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0 || !is_array($roleNames)) {
                return false;
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->hasAnyRole($userId, $roleNames);
            } catch (Throwable $e) {
                logError('Role check failed: ' . $e->getMessage(), 'WARNING');
                return false;
            }
        }));
        
        // Check if user is super admin
        $twig->addFunction(new \Twig\TwigFunction('isSuperAdmin', function ($userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0) {
                return false;
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->isSuperAdmin($userId);
            } catch (Throwable $e) {
                logError('Super admin check failed: ' . $e->getMessage(), 'WARNING');
                return false;
            }
        }));
        
        // Get user roles
        $twig->addFunction(new \Twig\TwigFunction('getUserRoles', function ($userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0) {
                return [];
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->getRoles($userId);
            } catch (Throwable $e) {
                logError('Get user roles failed: ' . $e->getMessage(), 'WARNING');
                return [];
            }
        }));
        
        // Get user permissions
        $twig->addFunction(new \Twig\TwigFunction('getUserPermissions', function ($userId = null) use ($mysqli) {
            if ($userId === null) {
                $user = AuthManager::getCurrentUserArray();
                $userId = $user['id'] ?? 0;
            }
            if ($userId <= 0) {
                return [];
            }
            try {
                $userModel = new UserModel($mysqli);
                return $userModel->getPermissions($userId);
            } catch (Throwable $e) {
                logError('Get user permissions failed: ' . $e->getMessage(), 'WARNING');
                return [];
            }
        }));
        
        // Get configuration value
        $twig->addFunction(new \Twig\TwigFunction('config', function ($key, $default = null) use ($appSettings) {
            return $appSettings[$key] ?? $default;
        }));
        
        // Get old input (for form repopulation)
        $twig->addFunction(new \Twig\TwigFunction('old', function ($key, $default = '') use ($session) {
            return $session['old_input'][$key] ?? $default;
        }));
        
        // Get validation error
        $twig->addFunction(new \Twig\TwigFunction('error', function ($field) use ($session) {
            return $session['errors'][$field] ?? null;
        }));
        
        // Check if field has error
        $twig->addFunction(new \Twig\TwigFunction('has_error', function ($field) use ($session) {
            return isset($session['errors'][$field]);
        }));
        
        // Generate random string
        $twig->addFunction(new \Twig\TwigFunction('random_string', function ($length = 10) {
            return bin2hex(random_bytes($length / 2));
        }));
        
        // Current URL (full URL with protocol, host, and query params)
        $twig->addFunction(new \Twig\TwigFunction('current_url', function () {
            // Build full URL with protocol and host
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $protocol = $isHttps ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            
            return $protocol . '://' . $host . $requestUri;
        }));
        
        // Base URL function (consistent with global variable)
        // Returns base URL with optional path
        $twig->addFunction(new \Twig\TwigFunction('base_url', function ($path = '') {
            // Get HTTPS detection
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $protocol = $isHttps ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            
            if (empty($path)) {
                return $baseUrl . '/';
            }
            
            return $baseUrl . '/' . ltrim($path, '/');
        }));
        
        // URL function (alias for base_url - for backward compatibility)
        $twig->addFunction(new \Twig\TwigFunction('url', function ($path = '') {
            // Get HTTPS detection
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $protocol = $isHttps ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
            
            if (empty($path)) {
                return $baseUrl . '/';
            }
            
            return $baseUrl . '/' . ltrim($path, '/');
        }));

        // Dump and die (for debugging)
        $twig->addFunction(new \Twig\TwigFunction('dd', function (...$vars) {
            echo '<pre>';
            foreach ($vars as $var) {
                var_dump($var);
            }
            echo '</pre>';
            die();
        }));
        
        // Get avatar URL
        $twig->addFunction(new \Twig\TwigFunction('avatar', function ($user, $size = 80) {
            if (!empty($user['avatar'])) {
                return '/uploads/avatars/' . $user['avatar'];
            }
            
            // Gravatar fallback
            $email = $user['email'] ?? '';
            $hash = md5(strtolower(trim($email)));
            return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
        }));
        
        // Status badge helper
        $twig->addFunction(new \Twig\TwigFunction('status_badge', function ($status) {
            $badges = [
                'active' => '<span class="badge bg-success">Active</span>',
                'inactive' => '<span class="badge bg-secondary">Inactive</span>',
                'pending' => '<span class="badge bg-warning">Pending</span>',
                'approved' => '<span class="badge bg-success">Approved</span>',
                'rejected' => '<span class="badge bg-danger">Rejected</span>',
                'draft' => '<span class="badge bg-secondary">Draft</span>',
                'published' => '<span class="badge bg-primary">Published</span>',
            ];
            
            return $badges[strtolower($status)] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
        }, ['is_safe' => ['html']]));
        
        // Include SVG icon
        $twig->addFunction(new \Twig\TwigFunction('icon', function ($name, $class = '') {
            $iconPath = $_SERVER['DOCUMENT_ROOT'] . "/assets/icons/{$name}.svg";
            if (file_exists($iconPath)) {
                $svg = file_get_contents($iconPath);
                if ($class) {
                    $svg = str_replace('<svg', '<svg class="' . $class . '"', $svg);
                }
                return $svg;
            }
            return '';
        }, ['is_safe' => ['html']]));

        // Admin breadcrumb functions
        $twig->addFunction(new \Twig\TwigFunction('getAdminBreadcrumbs', function ($page = null, $subpage = null, $item = null) {
            return getAdminBreadcrumbs($page, $subpage, $item);
        }));

        $twig->addFunction(new \Twig\TwigFunction('auto_admin_breadcrumbs', function () {
            return autoAdminBreadcrumbs();
        }));

        // RTE (Rich Text Editor) functions
        $twig->addFunction(new \Twig\TwigFunction('getRTEVersion', function () {
            return getRTEVersion();
        }));

        $twig->addFunction(new \Twig\TwigFunction('getRTEFileUrl', function ($filename, $basePath = '/rtceditor/') {
            return getRTEFileUrl($filename, $basePath);
        }));


        // ============================================================
        // GLOBAL VARIABLES - URL & BASE CONFIGURATION
        // ============================================================
        
        // 1. Determine base URL (protocol + host)
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        // 2. Get current path (without query string)
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $currentPath = !empty($currentPath) ? rtrim($currentPath, '/') : '/';
        
        // 3. Full current URL with query string
        $fullCurrentUrl = $baseUrl . ($_SERVER['REQUEST_URI'] ?? '/');
        
        // 4. Canonical URL (without query params/fragments)
        $canonicalPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?#');
        $canonicalPath = !empty($canonicalPath) ? rtrim($canonicalPath, '/') : '/';
        $canonicalUrl = $baseUrl . $canonicalPath;
        
        // Add base URL global (same as url for backward compatibility)
        $twig->addGlobal('url', $baseUrl . '/');
        $twig->addGlobal('base_url', $baseUrl . '/');
        $twig->addGlobal('site_url', $baseUrl . '/');
        
        // Add request URL globals
        $twig->addGlobal('current_url', $fullCurrentUrl);
        $twig->addGlobal('current_path', $currentPath);
        $twig->addGlobal('canonical_url', $canonicalUrl);
        
        // Add CSRF token
        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('secureSession')) {
                secureSession();
            } elseif (!headers_sent()) {
                session_start();
            }
        }
        $twig->addGlobal('csrf_token', generateCsrfToken());
        
        // Flash message - use centralized function if available
        if (function_exists('getFlashMessage')) {
            $twig->addGlobal('flash_message', getFlashMessage());
        } else {
            $twig->addGlobal('flash_message', getFlash($session));
        }

        // Load user
        $user = loadUser($mysqli, $session['user_id'] ?? null);
        $twig->addGlobal('auth_user', $user);
        $twig->addGlobal('currentUserId', $user['id'] ?? 0);
        $twig->addGlobal('is_logged_in', $user['is_authenticated']);
        
        // App settings
        $twig->addGlobal('app_settings', $appSettings);
        $publicNavItems = [];
        try {
            if ($settingsModel instanceof AppSettings) {
                $publicNavItems = $settingsModel->getPublicNavItems($appSettings, true);
            }
        } catch (Throwable $e) {
            $publicNavItems = [];
        }
        if (empty($publicNavItems)) {
            $publicNavItems = [
                ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door-fill', 'match' => '/', 'enabled' => true, 'order' => 10],
                ['label' => 'Mobiles', 'url' => '/mobiles', 'icon' => 'bi-phone-fill', 'match' => '/mobiles', 'enabled' => true, 'order' => 20],
                ['label' => 'Articles', 'url' => '/posts', 'icon' => 'bi-newspaper', 'match' => '/posts', 'enabled' => true, 'order' => 30],
                ['label' => 'Services', 'url' => '/services', 'icon' => 'bi-award-fill', 'match' => '/services', 'enabled' => true, 'order' => 40],
            ];
        }
        $twig->addGlobal('public_nav_items', $publicNavItems);
        $twig->addGlobal('admin_dir', ADMIN_DIR);
        $twig->addGlobal('user_dir', USER_DIR);
        $twig->addGlobal('app_name', $appSettings['app_name'] ?? 'Application');
        $twig->addGlobal('site_name', $appSettings['site_name'] ?? 'Application');
        $twig->addGlobal('app_version', $appSettings['app_version'] ?? '1.0.0');
        
        // Request info
        $twig->addGlobal('current_year', date('Y'));
        $twig->addGlobal('request_method', $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $twig->addGlobal('is_ajax', !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        return $twig;
        
    } catch (Throwable $e) {
        error_log("CRITICAL: Twig initialization error - message: {$e->getMessage()}, " .
                "file: {$e->getFile()}, line: {$e->getLine()}");
        throw $e;
    }
}

// ============================================================
// INITIALIZE APP SETTINGS MODEL
// ============================================================

$settingsModel = new AppSettings($mysqli);

// ============================================================
// INITIALIZE TWIG
// ============================================================

global $twig;

try {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    $twig = initializeTwig($mysqli, $_SESSION, getCurrentUrl());
    logDebug('Twig engine initialized successfully', ['timestamp' => date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    logError('Failed to initialize Twig engine: ' . $e->getMessage(), 'CRITICAL', [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    renderError(500, 'Template engine initialization failed');
    exit;
}
