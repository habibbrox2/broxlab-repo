<?php
/**
 * Backwards-compatible upload config wrapper
 * Loads the legacy `UploadConfig.php` (which sets globals) and
 * returns an array-based config expected by `UploadService`.
 */

require_once __DIR__ . '/UploadConfig.php';

// Ensure modern .htaccess creation routine (overrides legacy implementations)
if (!function_exists('createUploadSecurityFiles')) {
    function createUploadSecurityFiles(): void
    {
        $htaccessContent = <<<'HTACCESS'
# Prevent script execution in upload directories (Apache 2.4+)
<FilesMatch "\.(?:php|phtml|php3|php4|php5|phps|pht|phar|inc|hphp|ctp|shtml)$">
    Require all denied
</FilesMatch>

# Allow serving static files
<FilesMatch "\.(?:jpe?g|jfif|gif|png|webp|avif|svg|mp4|webm|mov|mp3|wav|ogg|pdf|doc|docx|xls|xlsx)$">
    Require all granted
</FilesMatch>

# Protect .htaccess files
<FilesMatch "^\.htaccess$">
    Require all denied
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Cache images and media files
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType video/mp4 "access plus 1 month"
    ExpiresByType audio/mpeg "access plus 1 month"
</IfModule>
HTACCESS;

        $uploadDirs = [
            defined('UPLOADS_PROFILES_DIR') ? UPLOADS_PROFILES_DIR : __DIR__ . '/../public_html/uploads/profiles',
            defined('UPLOADS_MOBILES_DIR') ? UPLOADS_MOBILES_DIR : __DIR__ . '/../public_html/uploads/mobiles',
            defined('UPLOADS_CONTENT_DIR') ? UPLOADS_CONTENT_DIR : __DIR__ . '/../public_html/uploads/content',
            defined('UPLOADS_MEDIA_DIR') ? UPLOADS_MEDIA_DIR : __DIR__ . '/../public_html/uploads/media',
            defined('UPLOADS_SERVICES_DIR') ? UPLOADS_SERVICES_DIR : __DIR__ . '/../public_html/uploads/services',
        ];

        foreach ($uploadDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $htaccessPath = $dir . '/.htaccess';
            $needsWrite = true;
            if (file_exists($htaccessPath)) {
                $existing = file_get_contents($htaccessPath);
                if (strpos($existing, 'Require all denied') !== false) {
                    $needsWrite = false;
                }
            }
            if ($needsWrite) {
                file_put_contents($htaccessPath, $htaccessContent);
                @chmod($htaccessPath, 0644);
            }
        }
    }
}

// Run once to ensure existing .htaccess files are modernized
createUploadSecurityFiles();

// Build file types mapping from legacy globals
$fileTypes = [
    'image' => [
        'extensions' => $GLOBALS['ALLOWED_IMAGE_EXTENSIONS'] ?? [],
        'mimes' => $GLOBALS['ALLOWED_IMAGE_MIME'] ?? []
    ],
    'video' => [
        'extensions' => $GLOBALS['ALLOWED_VIDEO_EXTENSIONS'] ?? [],
        'mimes' => $GLOBALS['ALLOWED_VIDEO_MIME'] ?? []
    ],
    'audio' => [
        'extensions' => $GLOBALS['ALLOWED_AUDIO_EXTENSIONS'] ?? [],
        'mimes' => $GLOBALS['ALLOWED_AUDIO_MIME'] ?? []
    ],
    'document' => [
        'extensions' => $GLOBALS['ALLOWED_DOCUMENT_EXTENSIONS'] ?? [],
        'mimes' => $GLOBALS['ALLOWED_DOCUMENT_MIME'] ?? []
    ],
    'media' => [
        'extensions' => $GLOBALS['ALLOWED_MEDIA_EXTENSIONS'] ?? [],
        'mimes' => $GLOBALS['ALLOWED_MEDIA_MIME'] ?? []
    ]
];

return [
    'base' => [
        'upload_dir' => defined('UPLOADS_DIR') ? UPLOADS_DIR : (__DIR__ . '/../public_html/uploads'),
        'temp_dir' => defined('UPLOADS_TEMP_DIR') ? UPLOADS_TEMP_DIR : (__DIR__ . '/../public_html/uploads/tmp'),
        'logs_dir' => defined('LOG_DIR') ? LOG_DIR . 'uploads' : __DIR__ . '/../storage/logs',
        'max_file_size' => defined('UPLOAD_MAX_MEDIA_SIZE') ? UPLOAD_MAX_MEDIA_SIZE : (52 * 1024 * 1024),
        // Optional watermark path (create or place watermark image here if needed)
        'watermark_path' => __DIR__ . '/../public_html/assets/watermark.png'
    ],

    'blocked_extensions' => $GLOBALS['BLOCKED_EXTENSIONS'] ?? [],

    'file_types' => $fileTypes,

    'image_processing' => [
        'enabled' => true,
        // Remove EXIF metadata from images after processing
        'remove_exif' => true,
        // JPEG quality for output (0-100)
        'quality' => 85,
        // Backwards compatible alias
        'jpeg_quality' => 85,
        // Whether to generate thumbnails
        'create_thumbnails' => true,
        'max_width' => 2048,
        'max_height' => 2048,
        // Default thumbnail sizes
        'thumbnail_sizes' => [
            'small' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300]
        ]
    ],

    'security' => [
        'rate_limit' => [
            'enabled' => false,
            'max_per_minute' => 20
        ]
    ],

    'categories' => [
        'media_library' => [
            'subdirectory' => 'media',
            'max_size' => defined('UPLOAD_MAX_MEDIA_SIZE') ? UPLOAD_MAX_MEDIA_SIZE : (52 * 1024 * 1024),
            'type' => 'media',
            'preserve_name' => false,
            'watermark' => false
        ],
        'content_image' => [
            'subdirectory' => 'content',
            'max_size' => defined('UPLOAD_MAX_CONTENT_SIZE') ? UPLOAD_MAX_CONTENT_SIZE : (5 * 1024 * 1024),
            'type' => 'image',
            'preserve_name' => true,
            'watermark' => false
        ],
        'service_image' => [
            'subdirectory' => 'services',
            'max_size' => defined('UPLOAD_MAX_SERVICE_IMAGE_SIZE') ? UPLOAD_MAX_SERVICE_IMAGE_SIZE : (10 * 1024 * 1024),
            'type' => 'image',
            'preserve_name' => false,
            'watermark' => false
        ],
        'service_document' => [
            'subdirectory' => 'services/documents',
            'max_size' => defined('UPLOAD_MAX_MEDIA_SIZE') ? UPLOAD_MAX_MEDIA_SIZE : (52 * 1024 * 1024),
            'type' => ['document', 'image'],
            'preserve_name' => true,
            'watermark' => false
        ],
        'mobile_image' => [
            'subdirectory' => 'mobiles',
            'max_size' => defined('UPLOAD_MAX_MOBILE_SIZE') ? UPLOAD_MAX_MOBILE_SIZE : (5 * 1024 * 1024),
            'type' => 'image',
            'preserve_name' => false,
            'watermark' => false
        ],
        'profiles' => [
            'subdirectory' => 'profiles',
            'max_size' => defined('UPLOAD_MAX_PROFILE_SIZE') ? UPLOAD_MAX_PROFILE_SIZE : (2 * 1024 * 1024),
            'type' => 'image',
            'preserve_name' => true,
            'watermark' => false
        ]
    ]
];
