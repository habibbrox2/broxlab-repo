<?php

declare(strict_types=1);

require_once __DIR__ . '/Functions.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/ErrorLogging.php';
require_once dirname(__DIR__, 1) . '/app/Models/UserModel.php';

if (!function_exists('env')) {
    function env(string $key, mixed $default = null)
    {
        if (isset($_ENV[$key])) {
            return normalizeEnvValue($_ENV[$key]);
        }

        if (isset($_SERVER[$key])) {
            return normalizeEnvValue($_SERVER[$key]);
        }

        $value = getenv($key);
        if ($value !== false) {
            return normalizeEnvValue($value);
        }

        return $default;
    }
}

if (!function_exists('normalizeEnvValue')) {
    function normalizeEnvValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        $value = trim($value);

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('brox_is_development_env')) {
    function brox_is_development_env(): bool
    {
        $env = env('APP_ENV', 'production');
        if ($env === 'development') {
            return true;
        }

        $host = strtolower((string)(brox_get_request_host() ?? ''));
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}

if (!function_exists('brox_get_request_protocol')) {
    function brox_get_request_protocol(): string
    {
        $httpsServer = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $forwardedSsl = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
        $serverPort = (int)($_SERVER['SERVER_PORT'] ?? 0);

        if ($httpsServer !== '' && $httpsServer !== 'off' && $httpsServer !== '0') {
            return 'https';
        }

        if ($forwardedProto === 'https' || $forwardedSsl === 'on') {
            return 'https';
        }

        if ($serverPort === 443) {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('brox_get_request_host')) {
    function brox_get_request_host(): ?string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
            return trim((string)$hosts[0]);
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            return trim((string)$_SERVER['HTTP_HOST']);
        }

        return $_SERVER['SERVER_NAME'] ?? null;
    }
}

if (!function_exists('brox_normalize_schema')) {
    function brox_normalize_schema(mixed $value, string $baseUrl): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = brox_normalize_schema($item, $baseUrl);
            }
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if (preg_match('~^(https?://|//)~i', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('~^/[^/].*~', $trimmed)) {
            return rtrim($baseUrl, '/') . $trimmed;
        }

        return $trimmed;
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
            $distAbs = $projectRoot . '/public_html/assets/js/dist/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $watchRoots = [
                $projectRoot . '/public_html/assets/js',
                $projectRoot . '/public_html/assets/datepicker/datepicker.js',
                $projectRoot . '/build/esbuild.config.js',
            ];

            $distMtime = brox_latest_mtime($distAbs);
            $sourceMtime = 0;

            foreach ($watchRoots as $watchRoot) {
                $mtime = brox_latest_mtime($watchRoot);
                if ($mtime > $sourceMtime) {
                    $sourceMtime = $mtime;
                }
            }

            if ($sourceMtime > $distMtime || !file_exists($distAbs)) {
                brox_try_run_dev_build('app-dist', 'npm run build:app:dist', $projectRoot);
            }

            return $url;
        }

        $buildMap = [
            '/assets/ai-assistant/dist/' => [
                'watchRoots' => [
                    $projectRoot . '/public_html/assets/ai-assistant/bootstrap',
                    $projectRoot . '/public_html/assets/ai-assistant/core',
                    $projectRoot . '/public_html/assets/ai-assistant/modules',
                    $projectRoot . '/public_html/assets/ai-assistant/styles',
                    $projectRoot . '/node_modules/@heyputer/puter.js/src',
                    $projectRoot . '/build/esbuild-assistants.mjs',
                ],
                'distRoot' => $projectRoot . '/public_html/assets/ai-assistant/dist',
                'commandKey' => 'assistants',
                'command' => 'npm run build:assistants',
            ],
            '/assets/firebase/v2/dist/' => [
                'watchRoots' => [
                    $projectRoot . '/public_html/assets/firebase/v2/src',
                    $projectRoot . '/public_html/assets/firebase/v2/modules',
                    $projectRoot . '/public_html/assets/firebase/v2/core',
                    $projectRoot . '/build/esbuild-firebase.mjs',
                ],
                'distRoot' => $projectRoot . '/public_html/assets/firebase/v2/dist',
                'commandKey' => 'firebase-v2',
                'command' => 'npm run build:firebase:v2',
            ],
            '/ai/dist/' => [
                'watchRoots' => [
                    $projectRoot . '/public_html/ai/js',
                    $projectRoot . '/public_html/ai/css',
                    $projectRoot . '/build/esbuild-ai.mjs',
                ],
                'distRoot' => $projectRoot . '/public_html/ai/dist',
                'commandKey' => 'ai',
                'command' => 'npm run build:ai',
            ],
        ];

        foreach ($buildMap as $prefix => $config) {
            if (strpos($url, $prefix) !== 0) {
                continue;
            }

            $relative = substr($url, strlen($prefix));
            $distAbs = $config['distRoot'] . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $distMtime = brox_latest_mtime($distAbs);
            $sourceMtime = 0;

            foreach (($config['watchRoots'] ?? []) as $watchRoot) {
                $mtime = brox_latest_mtime($watchRoot);
                if ($mtime > $sourceMtime) {
                    $sourceMtime = $mtime;
                }
            }

            if ($sourceMtime > $distMtime || !file_exists($distAbs)) {
                brox_try_run_dev_build($config['commandKey'], $config['command'], $projectRoot);
            }

            return $url;
        }

        return $url;
    }
}

if (!function_exists('getFlash')) {
    function getFlash(?array &$session): ?array
    {
        if (!is_array($session)) {
            $session = [];
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('secureSession')) {
                secureSession();
            } elseif (!headers_sent()) {
                session_start();
            }
        }

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

if (!function_exists('loadUser')) {
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
                'id' => (int)$userId,
                'is_authenticated' => true,
                'role' => $profile['role'] ?? 'guest',
            ];

            foreach ($profile as $key => $value) {
                if (in_array($key, ['id', 'role'], true)) {
                    continue;
                }

                if ($key === 'profile_pic') {
                    if (!empty($value)) {
                        $user[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    } else {
                        $user[$key] = $defaults['profile_pic'];
                    }
                    continue;
                }

                if (is_string($value)) {
                    $user[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                } else {
                    $user[$key] = $value;
                }
            }

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
}


if (!function_exists("registerTwigHelpers")) {
    function registerTwigHelpers(\Twig\Environment $twig, mysqli $mysqli, array &$session, array $appSettings, string $configUrl): void
    {
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
                $twig->addFilter(new \Twig\TwigFilter('currency', function ($number, $symbol = 'αº│') {
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
        
                // Float cast filter
                $twig->addFilter(new \Twig\TwigFilter('float', function ($value, $default = 0.0) {
                    if (is_numeric($value)) {
                        return (float) $value;
                    }
                    return (float) $default;
                }));
        
                // Word count filter
                $twig->addFilter(new \Twig\TwigFilter('wordcount', function ($value): int {
                    if (empty($value)) {
                        return 0;
                    }
                    $text = strip_tags((string)$value);
                    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
                    return (int) count($words);
                }));
        
                // Regex find filter (returns array of matches)
                $twig->addFilter(new \Twig\TwigFilter('regex_find', function ($pattern, $subject) {
                    if (!is_string($subject) || !is_string($pattern)) {
                        return [];
                    }
                    preg_match_all($pattern, $subject, $matches);
                    return $matches[0] ?? [];
                }));
        
                // File size format
                $twig->addFilter(new \Twig\TwigFilter('filesizeformat', function ($bytes, $decimals = 2) {
                    $bytes = (float) $bytes;
        
                    if ($bytes <= 0) {
                        return '0 B';
                    }
        
                    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
                    $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
        
                    return sprintf(
                        "%." . (int)$decimals . "f %s",
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
                            'January',
                            'February',
                            'March',
                            'April',
                            'May',
                            'June',
                            'July',
                            'August',
                            'September',
                            'October',
                            'November',
                            'December'
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
                            'January',
                            'February',
                            'March',
                            'April',
                            'May',
                            'June',
                            'July',
                            'August',
                            'September',
                            'October',
                            'November',
                            'December'
                        ];
        
                        foreach ($months as $month) {
                            $formatted = str_replace($month, enToBnMonth($month), $formatted);
                        }
        
                        return enToBnDigits($formatted);
                    } catch (Exception $e) {
                        return $date;
                    }
                }));
        
                // Relative time (e.g., "2 hours ago" or Bengali: "αº¿ αªÿαª¿αºìαªƒαª╛ αªåαªùαºç")
                $twig->addFilter(new \Twig\TwigFilter('time_ago', function ($datetime, $bengali = false) {
                    if (empty($datetime)) {
                        return '';
                    }
        
                    try {
                        $time = is_numeric($datetime) ? $datetime : strtotime($datetime);
                        $diff = time() - $time;
        
                        $output = '';
        
                        if ($diff < 60) {
                            $output = $bengali ? 'αªÅαªûαª¿αªç' : 'just now';
                        } elseif ($diff < 3600) {
                            $minutes = (int)floor($diff / 60);
                            $output = $bengali
                                ? enToBnDigits((string)$minutes) . ' αª«αª┐αª¿αª┐αªƒ αªåαªùαºç'
                                : $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
                        } elseif ($diff < 86400) {
                            $hours = (int)floor($diff / 3600);
                            $output = $bengali
                                ? enToBnDigits((string)$hours) . ' αªÿαª¿αºìαªƒαª╛ αªåαªùαºç'
                                : $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                        } elseif ($diff < 604800) {
                            $days = (int)floor($diff / 86400);
                            $output = $bengali
                                ? enToBnDigits((string)$days) . ' αªªαª┐αª¿ αªåαªùαºç'
                                : $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                        } elseif ($diff < 2592000) {
                            $weeks = (int)floor($diff / 604800);
                            $output = $bengali
                                ? enToBnDigits((string)$weeks) . ' αª╕αª¬αºìαªñαª╛αª╣ αªåαªùαºç'
                                : $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
                        } elseif ($diff < 31536000) {
                            $months = (int)floor($diff / 2592000);
                            $output = $bengali
                                ? enToBnDigits((string)$months) . ' αª«αª╛αª╕ αªåαªùαºç'
                                : $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
                        } else {
                            $years = (int)floor($diff / 31536000);
                            $output = $bengali
                                ? enToBnDigits((string)$years) . ' αª¼αª¢αª░ αªåαªùαºç'
                                : $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
                        }
        
                        return $output;
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
        
                // URL encode filter (always cast to string to avoid PHP 8 strict urlencode type errors)
                $twig->addFilter(new \Twig\TwigFilter('url_encode', function ($text) {
                    return urlencode((string)$text);
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
        
                // Absolute URL filter - converts relative paths to absolute URLs for SEO
                $twig->addFilter(new \Twig\TwigFilter('absolute_url', function ($path) {
                    if (empty($path)) {
                        return '';
                    }
                    // If already absolute URL, return as is
                    if (preg_match('~^https?://~i', $path)) {
                        return $path;
                    }
                    // Get base URL
                    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                    $protocol = $isHttps ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $baseUrl = $protocol . '://' . $host;
                    return $baseUrl . '/' . ltrim($path, '/');
                }));
        
                // Format file size
                $twig->addFunction(new \Twig\TwigFunction('format_file_size', function ($bytes) {
                    $sizes = ["B", "KB", "MB", "GB", "TB"];
                    if ($bytes == 0) {
                        return "0 B";
                    }
                    $i = (int) floor(log($bytes, 1024));
                    return round($bytes / pow(1024, $i), 2) . " " . $sizes[$i];
                }));
        
                // Get file icon
                $twig->addFunction(new \Twig\TwigFunction('get_file_icon', function ($mimeType) {
                    $iconMap = [
                        'image' => '≡ƒû╝∩╕Å',
                        'video' => '≡ƒÄÑ',
                        'audio' => '≡ƒÄ╡',
                        'pdf' => '≡ƒôä',
                        'word' => '≡ƒô¥',
                        'excel' => '≡ƒôè',
                        'powerpoint' => '≡ƒôê',
                        'archive' => '≡ƒôª',
                        'code' => '≡ƒÆ╗',
                        'text' => '≡ƒôä'
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
        
                    return '≡ƒôÄ';
                }));                // Asset URL helper (with version/cache busting)
                $twig->addFunction(new \Twig\TwigFunction('asset', function ($path, $version = true) {
                    $url = '/' . ltrim($path, '/');

                    // In dev mode, map /cdn/ paths to actual CDN URLs so they don't 404
                    if (brox_is_development_env() && strpos($url, '/cdn/') === 0) {
                        static $cdnMap = [
                            '/cdn/css/bootstrap.min.css'        => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                            '/cdn/css/bootstrap-icons.min.css'  => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
                            '/cdn/css/sweetalert2.min.css'      => 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
                            '/cdn/js/sweetalert2.all.min.js'   => 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
                        ];
                        if (isset($cdnMap[$url])) {
                            return $cdnMap[$url];
                        }
                    }

                    $url = brox_resolve_asset_for_development($url);

                    if ($version) {
                        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? realpath(dirname(__DIR__) . '/public_html');
                        $fullPath = $documentRoot . $url;

                        if (file_exists($fullPath)) {
                            $filemtime = @filemtime($fullPath);
                            if ($filemtime !== false) {
                                $separator = strpos($url, '?') === false ? '?' : '&';
                                $url .= $separator . 'v=' . $filemtime;
                            }
                        }
                    }

                    return $url;
                }));
        
                // Route URL generator
                $twig->addFunction(new \Twig\TwigFunction('route', function ($name, $params = []) {
                    global $router;
                    if (isset($router) && is_object($router) && is_callable([$router, 'route'])) {
                        return $router->route($name, $params);
                    }
                    return '#';
                }));
        
                // Path function (alias for route)
                $twig->addFunction(new \Twig\TwigFunction('path', function ($name, $params = []) {
                    global $router;
                    if (isset($router) && is_object($router) && is_callable([$router, 'route'])) {
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
        
                // Get environment variable (reads from .env, $_ENV, $_SERVER, getenv)
                $twig->addFunction(new \Twig\TwigFunction('env', function ($key, $default = null) {
                    return env((string)$key, $default);
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
        
                // CSRF token helper
                $twig->addFunction(new \Twig\TwigFunction('generateCsrfToken', function () {
                    return generateCsrfToken();
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
                    $generator = new BreadcrumbGenerator();
                    return $generator->generateAutoAdmin();
                }));
        
                // RTE (Rich Text Editor) functions
                $twig->addFunction(new \Twig\TwigFunction('getRTEVersion', function () {
                    return getRTEVersion();
                }));
        
                $twig->addFunction(new \Twig\TwigFunction('getRTEFileUrl', function ($filename, $basePath = '/rtceditor/') {
                    return getRTEFileUrl($filename, $basePath);
                }));
        
                // 1. Determine base URL (protocol + host)
                $protocol = brox_get_request_protocol();
                $host = brox_get_request_host() ?? 'localhost';
        
                // Prefer a configured canonical URL first, then APP_URL, then current request host.
                $preferredSiteUrl = trim((string)($appSettings['site_url'] ?? ''));
                if (empty($preferredSiteUrl)) {
                    $preferredSiteUrl = trim((string)env('APP_URL', ''));
                }
                if (empty($preferredSiteUrl) && !empty($_SERVER['HTTP_HOST'])) {
                    $host = preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST']);
                    $preferredSiteUrl = brox_get_request_protocol() . '://' . $host;
                }
                $baseUrl = rtrim($preferredSiteUrl, '/');

                // Schema JSON filter for JSON-LD output
                $twig->addFilter(new \Twig\TwigFilter('schema_json', function ($value) use ($baseUrl) {
                    return json_encode(
                        brox_normalize_schema($value, $baseUrl),
                        JSON_UNESCAPED_SLASHES |
                        JSON_UNESCAPED_UNICODE |
                        JSON_HEX_TAG |
                        JSON_HEX_AMP |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT
                    );
                }, ['is_safe' => ['html']]));

                // Redirect to the preferred domain/protocol if the request is not already using it.
                if (PHP_SAPI !== 'cli' && !brox_is_development_env()) {
                    $currentBaseUrl = strtolower($protocol . '://' . preg_replace('/:\d+$/', '', $host));
                    $normalizedBaseUrl = strtolower($baseUrl);
                    if ($currentBaseUrl !== $normalizedBaseUrl) {
                        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
                        $redirectUrl = $normalizedBaseUrl . $requestUri;
                        if (!headers_sent()) {
                            header('HTTP/1.1 301 Moved Permanently');
                            header('Location: ' . $redirectUrl);
                            exit;
                        }
                    }
                }
        
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
                // Language support
                $twig->addGlobal('current_lang', LanguageHelper::getCurrentLang());
                $twig->addGlobal('available_languages', LanguageHelper::getAvailableLanguages());
        
                $twig->addGlobal('app_settings', $appSettings);
                $twig->addGlobal('is_dev_env', brox_is_development_env());
                $publicNavItems = [];
                try {
                    if (class_exists('AppSettings')) {
                        $settingsModelInstance = new AppSettings($mysqli);
                        $publicNavItems = $settingsModelInstance->getPublicNavItems($appSettings, true);
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
        
                $twig->addFunction(new \Twig\TwigFunction('translate', function ($text, $from = 'en', $to = null) {
                    return LanguageHelper::translate((string) $text, $from, $to, false);
                }));
        
                $twig->addFilter(new \Twig\TwigFilter('trans', function ($text, $from = 'en', $to = null) {
                    return LanguageHelper::translate((string) $text, $from, $to, false);
                }));
        
                // ΓöÇΓöÇ Calculator description helper ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ
                $twig->addFunction(new \Twig\TwigFunction('getCalculatorDescription', function (string $type): string {
                    $descriptions = [
                        'simple-interest'    => '<p>Calculate the interest earned on a simple-interest investment or loan using the formula <em>SI = P ├ù r ├ù t</em>.</p>
                            <ul><li><strong>Principal</strong> ΓÇô starting amount</li><li><strong>Rate</strong> ΓÇô annual interest rate as a percentage</li><li><strong>Time</strong> ΓÇô number of years</li></ul>',
                        'compound-interest'  => '<p>Estimate the future value of an investment with compound interest. The interest is reinvested each compounding period.</p>
                            <ul><li><strong>Compounded Yearly</strong> ΓÇô interest adds once per year</li><li><strong>Compounded Monthly</strong> ΓÇô interest adds every month</li><li><strong>Compounded Daily</strong> ΓÇô interest adds every day</li></ul>',
                        'loan-amortization'  => '<p>Break down any loan into its monthly payments, total interest payable, and total cost. Works for personal loans, car loans, and student loans.</p>',
                        'mortgage'           => '<p>Estimate your monthly mortgage payment including principal &amp; interest (P&amp;I), property tax, home insurance, and HOA fees.</p>
                            <ul><li><strong>LTV Ratio</strong> shows how much of the home price is financed</li><li><strong>PMI</strong> is typically required when the down payment is below 20%</li></ul>',
                        'percentage'         => '<p>Compute what a given percentage of any number is. Also useful for tips, discounts, and markup calculations.</p>',
                        'percentage-change'  => '<p>Find the percentage difference between two values. A positive result is an increase; a negative result is a decrease.</p>',
                        'gpa'                => '<p>Calculate your GPA from a list of courses. Enter each course\'s credit hours and grade point (on a 0ΓÇô4 scale) as JSON.</p>
                            <p><small>Example: <code>[{"credit_hours":3,"grade_point":4},{"credit_hours":3,"grade_point":3.3}]</code></small></p>',
                        'bmi'                => '<p>Body Mass Index (BMI) is a simple screening tool that compares your weight to your height. BMI does not measure body fat directly.</p>
                            <ul><li><strong>Underweight</strong> ΓÇô BMI below 18.5</li><li><strong>Normal</strong> ΓÇô 18.5 ΓÇô 24.9</li><li><strong>Overweight</strong> ΓÇô 25 ΓÇô 29.9</li><li><strong>Obese</strong> ΓÇô 30 or above</li></ul>',
                    ];
                    return $descriptions[$type] ?? '<p>Complete the form below and click Calculate to see your result.</p>';
                }, ['is_safe' => ['html']]));
        
    }
}
