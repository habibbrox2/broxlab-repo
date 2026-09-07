<?php

/**
 * Upload Configuration
 * 
 * Centralized configuration for all file/media uploads
 * Ensures proper organization and consistent directory structure
 * 
 * Upload Directory Structure:
 * /public_html/uploads/
 * ├── profiles/          (User profile pictures)
 * ├── mobiles/           (Mobile device photos)
 * ├── content/           (Content images)
 * ├── media/             (Media manager - images, videos, audio, documents)
 * │   ├── {YYYY/MM}/     (Date-based organization)
 * └── tmp/               (Temporary files)
 * 
 * NOTE: All directory path constants are now defined in config/constants.php
 * NOTE: All file size limit constants are now defined in config/constants.php
 */

// =============================================================================
// ALLOWED FILE TYPES
// =============================================================================

$GLOBALS['ALLOWED_IMAGE_EXTENSIONS'] = ['jpg', 'jpeg', 'jfif', 'png', 'gif', 'webp', 'avif'];
$GLOBALS['ALLOWED_IMAGE_MIME'] = [
    'image/jpeg',
    'image/jpg',
    'image/pjpeg',
    'image/jfif',
    'image/png',
    'image/webp',
    'image/gif',
    'image/avif'
];

$GLOBALS['ALLOWED_VIDEO_EXTENSIONS'] = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
$GLOBALS['ALLOWED_VIDEO_MIME'] = [
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'video/x-msvideo',
    'video/x-matroska'
];

$GLOBALS['ALLOWED_AUDIO_EXTENSIONS'] = ['mp3', 'wav', 'ogg', 'aac', 'flac'];
$GLOBALS['ALLOWED_AUDIO_MIME'] = [
    'audio/mpeg',
    'audio/wav',
    'audio/ogg',
    'audio/aac',
    'audio/flac'
];

$GLOBALS['ALLOWED_DOCUMENT_EXTENSIONS'] = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
$GLOBALS['ALLOWED_DOCUMENT_MIME'] = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain'
];

// Combined allowed media types
$GLOBALS['ALLOWED_MEDIA_EXTENSIONS'] = array_merge(
    $GLOBALS['ALLOWED_IMAGE_EXTENSIONS'],
    $GLOBALS['ALLOWED_VIDEO_EXTENSIONS'],
    $GLOBALS['ALLOWED_AUDIO_EXTENSIONS'],
    $GLOBALS['ALLOWED_DOCUMENT_EXTENSIONS']
);

$GLOBALS['ALLOWED_MEDIA_MIME'] = array_merge(
    $GLOBALS['ALLOWED_IMAGE_MIME'],
    $GLOBALS['ALLOWED_VIDEO_MIME'],
    $GLOBALS['ALLOWED_AUDIO_MIME'],
    $GLOBALS['ALLOWED_DOCUMENT_MIME']
);

// =============================================================================
// BLOCKED/DANGEROUS FILE EXTENSIONS
// =============================================================================

$GLOBALS['BLOCKED_EXTENSIONS'] = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'pht',
    'exe', 'bat', 'sh', 'jsp', 'py', 'rb', 'dll', 'com', 'jar',
    'vbs', 'js', 'asp', 'aspx', 'cgi', 'pl', 'pm', 'conf',
    'htaccess', 'ini', 'cfg', 'config'
];

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Ensure all required upload directories exist with proper permissions
 * Called automatically during application bootstrap
 */
if (!function_exists('initializeUploadDirectories')) {
    function initializeUploadDirectories(): void
    {
        $directories = [
            UPLOADS_DIR,
            UPLOADS_PROFILES_DIR,
            UPLOADS_MOBILES_DIR,
            UPLOADS_CONTENT_DIR,
            UPLOADS_MEDIA_DIR,
            UPLOADS_TEMP_DIR,
            // Also create typical date-based subdirectories for media
            UPLOADS_MEDIA_DIR . '/' . date('Y'),
            UPLOADS_MEDIA_DIR . '/' . date('Y/m'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    error_log("Failed to create upload directory: $dir");
                }
            }

            // Ensure directory is writable
            if (is_dir($dir) && !is_writable($dir)) {
                @chmod($dir, 0755);
            }
        }

        // Create .htaccess file to prevent script execution in uploads (delegate to modern implementation)
        if (function_exists('createUploadSecurityFiles')) {
            createUploadSecurityFiles();
        }
    }
}

// Backwards-compatible alias used at bootstrap. Defined here so callers (like index.php)
// won't trigger 'undefined function' diagnostics when helpers are loaded later.
if (!function_exists('ensureUploadDirectories')) {
    function ensureUploadDirectories(): void {
        if (function_exists('initializeUploadDirectories')) {
            initializeUploadDirectories();
        }
    }
}

/* Legacy createUploadSecurityFiles implementation removed from this file.
   Use `config/upload.php` which contains a modern Apache 2.4+ implementation and
   will run during bootstrap. */

/**
 * Get upload directory for a specific upload type
 * 
 * @param string $type Type of upload (profile, mobile, content, media)
 * @return string Full filesystem path to directory
 */
if (!function_exists('getUploadDirectory')) {
    function getUploadDirectory(string $type): string
    {
        switch (strtolower($type)) {
            case 'profile':
            case 'profiles':
                return UPLOADS_PROFILES_DIR;
            case 'mobile':
            case 'mobiles':
                return UPLOADS_MOBILES_DIR;
            case 'content':
                return UPLOADS_CONTENT_DIR;
            case 'media':
            case 'media_manager':
                // Return date-organized path for media
                $mediaPath = UPLOADS_MEDIA_DIR . '/' . date('Y/m');
                if (!is_dir($mediaPath)) {
                    mkdir($mediaPath, 0755, true);
                }
                return $mediaPath;
            case 'temp':
            case 'tmp':
                return UPLOADS_TEMP_DIR;
            default:
                return UPLOADS_DIR;
        }
    }
}

/**
 * Get web-accessible URL from filesystem path
 * 
 * @param string $filePath Filesystem path
 * @return string Web-accessible URL
 */
if (!function_exists('getUploadWebUrl')) {
    function getUploadWebUrl(string $filePath): string
    {
        return str_replace(UPLOADS_DIR, '/uploads', $filePath);
    }
}

/**
 * Check if file is blocked (dangerous)
 * 
 * @param string $filename Filename to check
 * @return bool True if file is blocked
 */
if (!function_exists('isBlockedFile')) {
    function isBlockedFile(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $GLOBALS['BLOCKED_EXTENSIONS']);
    }
}

/**
 * Validate upload size based on type
 * 
 * @param int $fileSize File size in bytes
 * @param string $type Upload type (profile, mobile, content, media)
 * @return bool True if file size is acceptable
 */
if (!function_exists('validateUploadSize')) {
    function validateUploadSize(int $fileSize, string $type = 'media'): bool
    {
        $maxSize = match (strtolower($type)) {
            'profile', 'profiles' => UPLOAD_MAX_PROFILE_SIZE,
            'mobile', 'mobiles' => UPLOAD_MAX_MOBILE_SIZE,
            'content' => UPLOAD_MAX_CONTENT_SIZE,
            'media', 'media_manager' => UPLOAD_MAX_MEDIA_SIZE,
            default => UPLOAD_MAX_MEDIA_SIZE,
        };

        return $fileSize <= $maxSize;
    }
}

/**
 * Format bytes to human-readable size
 * 
 * @param int $bytes Number of bytes
 * @param int $precision Decimal precision
 * @return string Formatted size string
 */
if (!function_exists('formatFileSize')) {
    function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

/**
 * Validate file extension against allowed list
 * 
 * @param string $filename Filename to check
 * @param string $type Type of file (image, video, audio, document, media)
 * @return bool True if extension is allowed
 */
if (!function_exists('validateFileExtension')) {
    function validateFileExtension(string $filename, string $type = 'media'): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $allowedExtensions = match (strtolower($type)) {
            'image' => $GLOBALS['ALLOWED_IMAGE_EXTENSIONS'],
            'video' => $GLOBALS['ALLOWED_VIDEO_EXTENSIONS'],
            'audio' => $GLOBALS['ALLOWED_AUDIO_EXTENSIONS'],
            'document' => $GLOBALS['ALLOWED_DOCUMENT_EXTENSIONS'],
            'media' => $GLOBALS['ALLOWED_MEDIA_EXTENSIONS'],
            default => $GLOBALS['ALLOWED_MEDIA_EXTENSIONS'],
        };
        
        return in_array($ext, $allowedExtensions);
    }
}

/**
 * Validate MIME type against allowed list
 * 
 * @param string $mimeType MIME type to check
 * @param string $type Type of file (image, video, audio, document, media)
 * @return bool True if MIME type is allowed
 */
if (!function_exists('validateMimeType')) {
    function validateMimeType(string $mimeType, string $type = 'media'): bool
    {
        $allowedMimes = match (strtolower($type)) {
            'image' => $GLOBALS['ALLOWED_IMAGE_MIME'],
            'video' => $GLOBALS['ALLOWED_VIDEO_MIME'],
            'audio' => $GLOBALS['ALLOWED_AUDIO_MIME'],
            'document' => $GLOBALS['ALLOWED_DOCUMENT_MIME'],
            'media' => $GLOBALS['ALLOWED_MEDIA_MIME'],
            default => $GLOBALS['ALLOWED_MEDIA_MIME'],
        };
        
        return in_array($mimeType, $allowedMimes);
    }
}

/**
 * Get maximum upload size for a type
 * 
 * @param string $type Upload type
 * @return int Maximum size in bytes
 */
if (!function_exists('getMaxUploadSize')) {
    function getMaxUploadSize(string $type = 'media'): int
    {
        return match (strtolower($type)) {
            'profile', 'profiles' => UPLOAD_MAX_PROFILE_SIZE,
            'mobile', 'mobiles' => UPLOAD_MAX_MOBILE_SIZE,
            'content' => UPLOAD_MAX_CONTENT_SIZE,
            'media', 'media_manager' => UPLOAD_MAX_MEDIA_SIZE,
            default => UPLOAD_MAX_MEDIA_SIZE,
        };
    }
}

// Auto-initialize on include
initializeUploadDirectories();

// Duplicate content removed: consolidated earlier in this file.
// The modern .htaccess creation routine now lives in `config/upload.php` and is run during bootstrap.
