<?php
/**
 * Unified Upload Service Class
 * ============================
 * সমস্ত ফাইল আপলোডের জন্য কেন্দ্রীয় সেবা
 * সকল কনফিগারেশন এক জায়গায়
 * সমস্ত লগিং ErrorLogging.php এর মাধ্যমে
 */

class UploadService
{
    private $config;
    private $mysqli;
    private $userId;
    private $uploadDir;
    private $tempDir;

    public function __construct(mysqli $mysqli, int $userId = 0)
    {
        $this->mysqli = $mysqli;
        $this->userId = $userId;
<<<<<<< HEAD
        $this->config = require dirname(__DIR__) . '/Config/Upload.php';
=======
        $this->config = require dirname(__DIR__, 2) . '/Config/Upload.php';
>>>>>>> temp_branch
        $this->uploadDir = $this->config['base']['upload_dir'];
        $this->tempDir = $this->config['base']['temp_dir'];
        
        // Ensure directories exist
        $this->ensureDirectories();
    }

    /**
     * নিশ্চিত করুন সমস্ত ডিরেক্টরি বিদ্যমান
     */
    private function ensureDirectories(): void
    {
        $dirs = [
            $this->uploadDir,
            $this->tempDir,
            $this->config['base']['logs_dir']
        ];

        // Add all category subdirectories
        foreach ($this->config['categories'] as $category => $settings) {
            $dirs[] = $this->uploadDir . '/' . $settings['subdirectory'];
        }

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    logError("Failed to create directory: $dir");
                }
            }
        }
    }

    /**
     * ফাইল আপলোড করুন
     * 
     * @param array $file $_FILES element
     * @param string $category Category name (from config)
     * @param array $options Additional options
     * @return array ['success' => bool, 'path' => string, 'url' => string, 'error' => string]
     */
    public function upload(array $file, string $category = 'media_library', array $options = []): array
    {
        try {
            // Validate category
            if (!isset($this->config['categories'][$category])) {
                throw new Exception("Invalid upload category: $category");
            }

            $categoryConfig = $this->config['categories'][$category];
            $resolvedIdentity = $this->resolveFileIdentity($file);

            // Step 1: Validate file
            $validation = $this->validateFile($file, $categoryConfig, $resolvedIdentity);
            if (!$validation['valid']) {
                logError("File validation failed", "FILE_UPLOAD", [
                    'file' => $file['name'],
                    'error' => $validation['error'],
                    'category' => $category
                ]);
                return ['success' => false, 'error' => $validation['error']];
            }

            // Step 2: Check rate limiting
            if ($this->config['security']['rate_limit']['enabled']) {
                if (!$this->checkRateLimit()) {
                    throw new Exception("Upload rate limit exceeded");
                }
            }

            // Step 3: Generate safe filename (supports $options['base_name'] for editor permalink)
            $filename = $this->generateFilename(
                (string)($file['name'] ?? ''),
                $categoryConfig,
                $options,
                (string)($resolvedIdentity['extension'] ?? '')
            );

            // Step 4: Determine upload path
            $uploadPath = $this->uploadDir . '/' . $categoryConfig['subdirectory'] . '/' . $filename;

            // Step 5: Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception("Failed to move uploaded file");
            }

            // Step 6: Process image (resize, watermark)
            if ($this->isImage($file, $resolvedIdentity) && $this->config['image_processing']['enabled']) {
                $this->processImage($uploadPath, $categoryConfig);
            }

            // Step 7: Save to database (if needed)
            $mediaId = null;
            if (in_array($category, ['media_library', 'content_image', 'mobile_image'])) {
                $mediaId = $this->saveToDatabase($filename, $uploadPath, $file, $category, $options, $resolvedIdentity);
            }

            // Step 8: Log success
<<<<<<< HEAD
            $webPath = str_replace(dirname(__DIR__) . '/public_html', '', $uploadPath);
=======
            $webPath = $this->toWebPath($uploadPath);
>>>>>>> temp_branch
            logDebug("File uploaded successfully", "FILE_UPLOAD", [
                'filename' => $filename,
                'category' => $category,
                'size' => $file['size'],
                'path' => $webPath,
                'user_id' => $this->userId
            ]);

            return [
                'success' => true,
                'path' => $uploadPath,
                'url' => $webPath,
                'filename' => $filename,
                'media_id' => $mediaId,
                'size' => $file['size']
            ];

        } catch (Exception $e) {
            logError("Upload failed: " . $e->getMessage(), "FILE_UPLOAD", [
                'file' => $file['name'] ?? 'unknown',
                'category' => $category,
                'user_id' => $this->userId
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ফাইল ভ্যালিডেট করুন
     */
    private function validateFile(array $file, array $categoryConfig, ?array $resolvedIdentity = null): array
    {
        // Check file error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File size exceeds PHP limit',
                UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
                UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            return [
                'valid' => false,
                'error' => $errors[$file['error']] ?? 'Unknown upload error'
            ];
        }

        // Check file size
        $maxSize = $categoryConfig['max_size'] ?? $this->config['base']['max_file_size'];
        if ($file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => "File size exceeds limit (" . $this->formatBytes($maxSize) . ")"
            ];
        }

        $identity = $resolvedIdentity ?: $this->resolveFileIdentity($file);
        $ext = strtolower((string)($identity['extension'] ?? ''));
        $mimeType = strtolower((string)($identity['mime'] ?? ''));

        // Check blocked extensions
        if ($ext !== '' && in_array($ext, $this->config['blocked_extensions'], true)) {
            return [
                'valid' => false,
                'error' => "File type not allowed: .$ext"
            ];
        }

        // Check type-specific validation
        if ($categoryConfig['type'] !== null) {
            $allowedTypes = is_array($categoryConfig['type']) 
                ? $categoryConfig['type'] 
                : [$categoryConfig['type']];

            $isAllowedType = false;
            foreach ($allowedTypes as $type) {
                if (isset($this->config['file_types'][$type])) {
                    $typeConfig = $this->config['file_types'][$type];
                    $extensions = $typeConfig['extensions'] ?? [];
                    $mimes = $typeConfig['mimes'] ?? [];

                    $extAllowed = $ext !== '' && in_array($ext, $extensions, true);
                    $mimeAllowed = $mimeType !== '' && in_array($mimeType, $mimes, true);

                    if ($extAllowed || $mimeAllowed) {
                        $isAllowedType = true;
                        break;
                    }
                }
            }

            if (!$isAllowedType) {
                return [
                    'valid' => false,
                    'error' => "File type not allowed for this category"
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * নিরাপদ ফাইলনাম তৈরি করুন
     */
    private function generateFilename(string $originalName, array $categoryConfig, array $options = [], string $resolvedExt = ''): string
    {
        // Extract extension
        $ext = strtolower(trim($resolvedExt));
        if ($ext === '') {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }
        $ext = preg_replace('/[^a-z0-9]+/i', '', $ext);

        // Final fallback when source filename has no extension.
        if ($ext === '') {
            $type = $categoryConfig['type'] ?? null;
            if (is_array($type)) {
                $type = $type[0] ?? null;
            }
            if ($type && isset($this->config['file_types'][$type]['extensions'][0])) {
                $ext = strtolower((string)$this->config['file_types'][$type]['extensions'][0]);
            } else {
                $ext = 'bin';
            }
        }

        // If editor provided a base_name (SEO permalink), prefer that
        $base = trim((string)($options['base_name'] ?? ''));
        if ($base !== '') {
            // Sanitize to SEO friendly slug-like name
            $name = strtolower($base);
            $name = preg_replace('/[^a-z0-9\-]+/i', '-', $name);
            $name = preg_replace('/-+/', '-', $name);
            $name = trim($name, '-');

            // Append incremental-style token using timestamp to avoid collisions: i+<timestamp>
            $name = $name . '_i' . time() . '_' . rand(100, 999);
        } elseif ($categoryConfig['preserve_name'] ?? false) {
            $name = pathinfo($originalName, PATHINFO_FILENAME);
            $name = preg_replace('/[^a-z0-9]+/i', '_', $name);
            $name = trim($name, '_');
            $name = $name . '_' . time();
        } else {
            // Generate random name
            $name = bin2hex(random_bytes(8)) . '_' . time();
        }

        $filename = $name . '.' . $ext;

        return strtolower($filename);
    }

    /**
     * ছবি প্রসেস করুন (রিসাইজ, ওয়াটারমার্ক ইত্যাদি)
     */
    private function processImage(string $imagePath, array $categoryConfig): void
    {
        try {
            if (!extension_loaded('gd')) {
                logDebug("GD extension not available - skipping image processing");
                return;
            }

            $info = getimagesize($imagePath);
            if (!$info) return;

            list($width, $height, $type) = $info;

            // Load image
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $image = imagecreatefromwebp($imagePath);
                    break;
                default:
                    return;
            }

            // Remove EXIF data (unless needed)
            if (!empty($this->config['image_processing']['remove_exif'])) {
                // EXIF data is removed by saving without it
            }

            // Apply watermark if enabled for this category
            $categoryWatermark = $categoryConfig['watermark'] ?? false;
            $imageProcessingEnabled = !empty($this->config['image_processing']['enabled']);
            if ($categoryWatermark && $imageProcessingEnabled) {
                $this->applyWatermark($image, $width, $height);
            }

            // Save optimized image (fallback to jpeg_quality or default 85)
            $quality = $this->config['image_processing']['quality'] ?? $this->config['image_processing']['jpeg_quality'] ?? 85;
            if (is_null($quality)) $quality = 85;
            @imagejpeg($image, $imagePath, (int)$quality);
            imagedestroy($image);

            // Create thumbnails if enabled
            if (!empty($this->config['image_processing']['create_thumbnails'])) {
                $this->createThumbnails($imagePath);
            }

            logDebug("Image processed successfully", "IMAGE_PROCESS", [
                'file' => basename($imagePath),
                'width' => $width,
                'height' => $height
            ]);

        } catch (Exception $e) {
            logError("Image processing failed: " . $e->getMessage(), "IMAGE_PROCESS", [
                'file' => basename($imagePath)
            ]);
        }
    }

    /**
     * ওয়াটারমার্ক প্রয়োগ করুন
     */
    private function applyWatermark(&$image, int $width, int $height): void
    {
        $watermarkPath = $this->config['base']['watermark_path'];

        if (!file_exists($watermarkPath)) {
            return;
        }

        $watermark = imagecreatefrompng($watermarkPath);
        $wmWidth = imagesx($watermark);
        $wmHeight = imagesy($watermark);

        // Position watermark at bottom right
        $destX = $width - $wmWidth - 10;
        $destY = $height - $wmHeight - 10;

        imagecopy($image, $watermark, $destX, $destY, 0, 0, $wmWidth, $wmHeight);
        imagedestroy($watermark);
    }

    /**
     * থাম্বনেইল তৈরি করুন
     */
    private function createThumbnails(string $imagePath): void
    {
        $dir = dirname($imagePath);
        $filename = pathinfo($imagePath, PATHINFO_FILENAME);
        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);

        foreach ($this->config['image_processing']['thumbnail_sizes'] as $sizeName => $size) {
            $thumbPath = $dir . '/' . $filename . '_' . $sizeName . '.' . $ext;
            $this->createThumbnail($imagePath, $thumbPath, $size['width'], $size['height']);
        }
    }

    /**
     * একটি থাম্বনেইল তৈরি করুন
     */
    private function createThumbnail(string $source, string $destination, int $maxWidth, int $maxHeight): void
    {
        $info = getimagesize($source);
        if (!$info) return;

        list($width, $height, $type) = $info;

        // Calculate new dimensions
        $ratio = $width / $height;
        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = $maxHeight * $ratio;
            $newHeight = $maxHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $ratio;
        }

        // Create thumbnail
        $thumb = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($source);
                imagecopyresampled($thumb, $src, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
                imagejpeg($thumb, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($source);
                imagecopyresampled($thumb, $src, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
                imagepng($thumb, $destination);
                break;
        }

        imagedestroy($thumb);
    }

    /**
     * ডাটাবেসে সেভ করুন
     */
    private function saveToDatabase(
        string $filename,
        string $filePath,
        array $file,
        string $category,
        array $options = [],
        ?array $resolvedIdentity = null
    ): ?int
    {
        try {
<<<<<<< HEAD
            $webPath = str_replace(dirname(__DIR__) . '/public_html', '', $filePath);
=======
            $webPath = $this->toWebPath($filePath);
>>>>>>> temp_branch
            $size = $file['size'];
            $identity = $resolvedIdentity ?: $this->resolveFileIdentity($file);
            $mimeType = (string)($identity['mime'] ?? ($file['type'] ?? 'application/octet-stream'));
            $mediaType = $this->getFileType($file, $identity);
            $originalName = $file['name'] ?? $filename;

            // Use provided title/description when available
            $title = trim((string)($options['title'] ?? $filename));
            $description = isset($options['description']) ? (string)$options['description'] : null;

            $stmt = $this->mysqli->prepare("
                INSERT INTO media (user_id, title, description, file_path, original_name, mime_type, media_type, file_size, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->bind_param('isssssis', $this->userId, $title, $description, $webPath, $originalName, $mimeType, $mediaType, $size);
            $stmt->execute();
            $mediaId = $this->mysqli->insert_id;
            $stmt->close();

            return $mediaId;
        } catch (Exception $e) {
            logError("Database save failed: " . $e->getMessage(), "MEDIA_DB");
            return null;
        }
    }

    /**
     * ফাইল টাইপ নির্ণয় করুন
     */
    private function getFileType(array $file, ?array $resolvedIdentity = null): string
    {
        $identity = $resolvedIdentity ?: $this->resolveFileIdentity($file);
        $ext = strtolower((string)($identity['extension'] ?? ''));
        $mimeType = strtolower((string)($identity['mime'] ?? ''));

        foreach ($this->config['file_types'] as $type => $config) {
            if (
                ($ext !== '' && in_array($ext, $config['extensions'] ?? [], true)) ||
                ($mimeType !== '' && in_array($mimeType, $config['mimes'] ?? [], true))
            ) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * এটি ছবি কিনা পরীক্ষা করুন
     */
    private function isImage(array $file, ?array $resolvedIdentity = null): bool
    {
        $identity = $resolvedIdentity ?: $this->resolveFileIdentity($file);
        $ext = strtolower((string)($identity['extension'] ?? ''));
        $mimeType = strtolower((string)($identity['mime'] ?? ''));

        if ($ext !== '' && in_array($ext, $this->config['file_types']['image']['extensions'] ?? [], true)) {
            return true;
        }
        if ($mimeType !== '' && in_array($mimeType, $this->config['file_types']['image']['mimes'] ?? [], true)) {
            return true;
        }
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Resolve effective extension + MIME for validation and storage naming.
     * Supports files without any extension in original filename.
     */
    private function resolveFileIdentity(array $file): array
    {
        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = $this->detectMimeType($file);

        if ($extension === '' && $mimeType !== '') {
            $extension = $this->mapMimeToExtension($mimeType);
        }

        // Normalize common aliases
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if ($extension === 'pjpeg') {
            $extension = 'jpg';
        }

        return [
            'extension' => strtolower((string)$extension),
            'mime' => strtolower((string)$mimeType),
        ];
    }

    /**
     * Detect MIME type from uploaded temporary file with safe fallbacks.
     */
    private function detectMimeType(array $file): string
    {
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName !== '' && is_file($tmpName)) {
            if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = @finfo_file($finfo, $tmpName);
                    @finfo_close($finfo);
                    if (is_string($detected) && $detected !== '') {
                        return strtolower(trim($detected));
                    }
                }
            }

            if (function_exists('mime_content_type')) {
                $detected = @mime_content_type($tmpName);
                if (is_string($detected) && $detected !== '') {
                    return strtolower(trim($detected));
                }
            }

            $imageInfo = @getimagesize($tmpName);
            if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
                return strtolower(trim((string)$imageInfo['mime']));
            }
        }

        return strtolower(trim((string)($file['type'] ?? '')));
    }

    /**
     * Infer extension from MIME type when original filename has no extension.
     */
    private function mapMimeToExtension(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/jfif' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
        ];

        $mimeType = strtolower(trim($mimeType));
        return $map[$mimeType] ?? '';
    }

    /**
     * রেট লিমিট চেক করুন
     */
    private function checkRateLimit(): bool
    {
        // TODO: Implement rate limiting logic
        return true;
    }

    /**
     * বাইট ফরম্যাট করুন
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * ফাইল ডিলিট করুন
     */
    public function delete(string $filePath): bool
    {
        try {
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    throw new Exception("Failed to delete file");
                }
            }

            logDebug("File deleted", "FILE_DELETE", [
                'file' => basename($filePath),
                'user_id' => $this->userId
            ]);

            return true;
        } catch (Exception $e) {
            logError("File deletion failed: " . $e->getMessage(), "FILE_DELETE", [
                'file' => basename($filePath)
            ]);
            return false;
        }
    }

    /**
     * কনফিগারেশন পান
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * কনফিগারেশন আপডেট করুন
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
<<<<<<< HEAD
=======

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function getUploadsBasePath(): string
    {
        if (function_exists('brox_get_uploads_base_path')) {
            return rtrim((string)brox_get_uploads_base_path(), '/\\');
        }
        if (defined('UPLOADS_DIR')) {
            return rtrim((string)UPLOADS_DIR, '/\\');
        }
        $fallback = dirname(__DIR__, 2) . '/public_html/uploads';
        return rtrim($fallback, '/\\');
    }

    private function getUploadsBaseUrl(): string
    {
        if (function_exists('brox_get_uploads_base_url')) {
            return rtrim((string)brox_get_uploads_base_url(), '/');
        }
        if (defined('UPLOADS_PUBLIC_URL')) {
            return '/' . trim((string)UPLOADS_PUBLIC_URL, '/');
        }
        return '/uploads';
    }

    private function toWebPath(string $filePath): string
    {
        $basePath = $this->getUploadsBasePath();
        $normalizedFile = $this->normalizePath($filePath);
        $normalizedBase = $this->normalizePath($basePath);
        $baseUrl = $this->getUploadsBaseUrl();

        if ($normalizedBase !== '' && strpos($normalizedFile, $normalizedBase) === 0) {
            $relative = ltrim(substr($normalizedFile, strlen($normalizedBase)), '/');
            return rtrim($baseUrl, '/') . '/' . $relative;
        }

        $publicMarker = '/public_html/';
        $pos = strpos($normalizedFile, $publicMarker);
        if ($pos !== false) {
            $relative = substr($normalizedFile, $pos + strlen($publicMarker) - 1);
            return $relative;
        }

        return $filePath;
    }
>>>>>>> temp_branch
}
