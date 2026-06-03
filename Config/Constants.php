<?php
/**
 * Constants Configuration File
 * ============================================================================
 * 
 * Centralized location for all application constants defined via define()
 * This file ensures clean separation of concerns and easier maintenance
 * 
 * Include this file AFTER environment variables are loaded (.env)
 * 
 * Include order:
 * 1. vendor/autoload.php
 * 2. Load .env variables
 * 3. This file (constants.php)
 * 4. Other configs
 */

// ============================================================================
// BASE DIRECTORY PATHS
// ============================================================================
define('BASE_PATH', realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR);

// ============================================================================
// STORAGE DIRECTORY PATHS
// ============================================================================
define('STORAGE_DIR', BASE_PATH . 'storage' . DIRECTORY_SEPARATOR);
define('CACHE_DIR', STORAGE_DIR . 'cache' . DIRECTORY_SEPARATOR);
define('TEMP_DIR', STORAGE_DIR . 'tmp' . DIRECTORY_SEPARATOR);
define('LOG_DIR', STORAGE_DIR . 'logs' . DIRECTORY_SEPARATOR);

// ============================================================================
// HTMLPURIFIER CACHE DIRECTORY
// ============================================================================
define('HTMLPURIFIER_CACHE_DIR', CACHE_DIR . 'htmlpurifier' . DIRECTORY_SEPARATOR);

// ============================================================================
// URL PATHS (Application Routes)
// ============================================================================
define('ADMIN_DIR', '/admin');
define('USER_DIR', '/users');

// ============================================================================
// MAINTENANCE & DEBUG FLAGS
// ============================================================================
define(
    'IS_MAINTENANCE',
    (($_ENV['MAINTENANCE_MODE'] ?? getenv('MAINTENANCE_MODE') ?? '0') === '1')
);
define('DEBUG_MODE', !IS_MAINTENANCE);

// ============================================================================
// UPLOAD DIRECTORY DEFINITIONS
// ============================================================================

if (!defined('UPLOADS_PUBLIC_URL')) {
    define('UPLOADS_PUBLIC_URL', '/uploads');
}

// Base uploads directory
if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/public_html/uploads');
}

// Profile pictures
if (!defined('UPLOADS_PROFILES_DIR')) {
    define('UPLOADS_PROFILES_DIR', UPLOADS_DIR . '/profiles');
}

// Mobile device images
if (!defined('UPLOADS_MOBILES_DIR')) {
    define('UPLOADS_MOBILES_DIR', UPLOADS_DIR . '/mobiles');
}

// Content images
if (!defined('UPLOADS_CONTENT_DIR')) {
    define('UPLOADS_CONTENT_DIR', UPLOADS_DIR . '/content');
}

// Media Manager (organized by date: YYYY/MM)
if (!defined('UPLOADS_MEDIA_DIR')) {
    define('UPLOADS_MEDIA_DIR', UPLOADS_DIR . '/media');
}

// Temporary uploads
if (!defined('UPLOADS_TEMP_DIR')) {
    define('UPLOADS_TEMP_DIR', UPLOADS_DIR . '/tmp');
}

// ============================================================================
// FILE SIZE LIMITS (in bytes)
// ============================================================================

if (!defined('UPLOAD_MAX_PROFILE_SIZE')) {
    define('UPLOAD_MAX_PROFILE_SIZE', 2 * 1024 * 1024);  // 2 MB
}

if (!defined('UPLOAD_MAX_MOBILE_SIZE')) {
    define('UPLOAD_MAX_MOBILE_SIZE', 5 * 1024 * 1024);   // 5 MB
}

if (!defined('UPLOAD_MAX_CONTENT_SIZE')) {
    define('UPLOAD_MAX_CONTENT_SIZE', 5 * 1024 * 1024);  // 5 MB
}

if (!defined('UPLOAD_MAX_MEDIA_SIZE')) {
    define('UPLOAD_MAX_MEDIA_SIZE', 52 * 1024 * 1024);   // 52 MB
}

// Services images directory & size limits
if (!defined('UPLOADS_SERVICES_DIR')) {
    define('UPLOADS_SERVICES_DIR', UPLOADS_DIR . '/services');
}

if (!defined('UPLOAD_MAX_SERVICE_IMAGE_SIZE')) {
    define('UPLOAD_MAX_SERVICE_IMAGE_SIZE', 10 * 1024 * 1024); // 10 MB
}

// ERROR LOGGING SYSTEM CONSTANTS
// ============================================================================

define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB max file size
define('LOG_MAX_AGE_DAYS', 30); // Keep logs for 30 days
define('LOG_CLEANUP_PROBABILITY', 100); // 1% chance to run cleanup on each init
define('ENABLE_ENHANCED_ERROR_LOG', true);


if (!function_exists('brox_get_uploads_base_path')) {
    function brox_get_uploads_base_path(): string
    {
        if (defined('UPLOADS_DIR')) {
            return rtrim((string)UPLOADS_DIR, '/\\');
        }
        return '';
    }
}

if (!function_exists('brox_get_uploads_base_url')) {
    function brox_get_uploads_base_url(): string
    {
        if (defined('UPLOADS_PUBLIC_URL')) {
            return '/' . trim((string)UPLOADS_PUBLIC_URL, '/');
        }
        return '/uploads';
    }
}

if (!function_exists('brox_normalize_branding_asset_path')) {
    function brox_normalize_branding_asset_path(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }

        $uploadsBaseUrl = brox_get_uploads_base_url();
        $uploadsLogoPrefix = $uploadsBaseUrl . '/logo/';

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);

        if (strpos($path, '/public_html/') === 0) {
            return substr($path, strlen('/public_html'));
        }

        if (strpos($path, 'public_html/') === 0) {
            return '/' . substr($path, strlen('public_html/'));
        }

        if (strpos($path, '/public/') === 0) {
            return substr($path, strlen('/public'));
        }

        if (strpos($path, 'public/') === 0) {
            return '/' . substr($path, strlen('public/'));
        }

        if (strpos($path, 'assets/') === 0) {
            $path = '/' . $path;
        }

        if (strpos($path, ltrim($uploadsBaseUrl, '/') . '/') === 0) {
            $path = '/' . $path;
        }



        if (strpos($path, '/assets/logo/') === 0) {
            return $uploadsLogoPrefix . ltrim(substr($path, strlen('/assets/logo/')), '/');
        }

        if (strpos($path, $uploadsLogoPrefix) === 0) {
            return $path;
        }

        if ($path[0] === '/') {
            return $path;
        }

        return $path;
    }
}

if (!function_exists('brox_get_branding_asset_abs_path')) {
    function brox_get_branding_asset_abs_path(string $path): ?string
    {
        $normalized = brox_normalize_branding_asset_path($path);
        if ($normalized === '' || preg_match('#^https?://#i', $normalized)) {
            return null;
        }

        $uploadsBaseUrl = brox_get_uploads_base_url();
        $uploadsBaseUrlNoSlash = ltrim($uploadsBaseUrl, '/');

        if (strpos($normalized, $uploadsBaseUrl . '/') === 0) {
            $relative = ltrim(substr($normalized, strlen($uploadsBaseUrl . '/')), '/');
            $basePath = brox_get_uploads_base_path();
            if ($basePath === '') {
                return null;
            }
            return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        if (strpos($normalized, $uploadsBaseUrlNoSlash . '/') === 0) {
            $relative = ltrim(substr($normalized, strlen($uploadsBaseUrlNoSlash . '/')), '/');
            $basePath = brox_get_uploads_base_path();
            if ($basePath === '') {
                return null;
            }
            return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        return null;
    }
}

if (!function_exists('brox_is_default_branding_file')) {
    function brox_is_default_branding_file(string $path): bool
    {
        $basename = strtolower(basename($path));
        return in_array($basename, ['logo.png', 'favicon.ico'], true);
    }
}

if (!function_exists('brox_is_deletable_branding_path')) {
    function brox_is_deletable_branding_path(string $path): bool
    {
        $normalized = brox_normalize_branding_asset_path($path);
        $uploadsLogoPrefix = brox_get_uploads_base_url() . '/logo/';
        if (strpos($normalized, $uploadsLogoPrefix) !== 0) {
            return false;
        }

        if (brox_is_default_branding_file($normalized)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('brox_delete_branding_file')) {
    function brox_delete_branding_file(string $path): void
    {
        if (!brox_is_deletable_branding_path($path)) {
            return;
        }

        $absPath = brox_get_branding_asset_abs_path($path);
        if ($absPath && is_file($absPath)) {
            @unlink($absPath);
        }
    }
}

if (!function_exists('brox_ensure_branding_assets_dir')) {
    function brox_ensure_branding_assets_dir(): void
    {
        $projectRoot = dirname(__DIR__);
        $logoDir = rtrim(brox_get_uploads_base_path(), '/\\') . '/logo';

        if (!is_dir($logoDir)) {
            @mkdir($logoDir, 0755, true);
        }

        $logoFile = $logoDir . '/logo.png';
        if (!is_file($logoFile)) {
            $legacyLogoCandidates = [
                $projectRoot . '/public_html/assets/logo/logo.png',
                $projectRoot . '/public_html/assets/logo.png',
                $projectRoot . '/public/assets/logo/logo.png',
                $projectRoot . '/public/assets/logo.png',
            ];
            foreach ($legacyLogoCandidates as $legacyLogo) {
                if (is_file($legacyLogo)) {
                    @copy($legacyLogo, $logoFile);
                    break;
                }
            }
        }

        $faviconFile = $logoDir . '/favicon.ico';
        if (!is_file($faviconFile)) {
            $legacyFaviconCandidates = [
                $projectRoot . '/public_html/assets/logo/favicon.ico',
                $projectRoot . '/public_html/assets/favicon.ico',
                $projectRoot . '/public/assets/logo/favicon.ico',
                $projectRoot . '/public/assets/favicon.ico',
            ];
            foreach ($legacyFaviconCandidates as $legacyFavicon) {
                if (is_file($legacyFavicon)) {
                    @copy($legacyFavicon, $faviconFile);
                    break;
                }
            }
        }
    }
}

if (!function_exists('brox_store_branding_upload')) {
    function brox_store_branding_upload(array $file, string $kind = 'logo'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed'];
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Invalid upload source'];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed = $kind === 'favicon'
            ? ['ico', 'png', 'svg', 'webp']
            : ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return ['success' => false, 'error' => 'File type is not allowed'];
        }

        $maxBytes = 5 * 1024 * 1024;
        if ((int)($file['size'] ?? 0) > $maxBytes) {
            return ['success' => false, 'error' => 'File size exceeds 5 MB limit'];
        }

        brox_ensure_branding_assets_dir();
        $logoDir = rtrim(brox_get_uploads_base_path(), '/\\') . '/logo';

        $prefix = $kind === 'favicon' ? 'favicon' : 'site-logo';
        $filename = sprintf('%s-%d-%04d.%s', $prefix, time(), random_int(1000, 9999), $ext);
        $target = $logoDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }

        return [
            'success' => true,
            'path' => $target,
            'url' => brox_get_uploads_base_url() . '/logo/' . $filename,
        ];
    }
}

