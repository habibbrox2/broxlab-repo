<?php
/**
 * MediaManager Service Class
 * 
 * Centralized media handling for image upload, processing, validation, and storage
 * Supports multiple media types (image, video, audio, document)
 * Architecture-ready for cloud storage (S3) integration in future
 */

class MediaManager
{
    private $mysqli;
    private $uploadDir;
    private $tempDir;
    private $maxFileSize;
    private $allowedMimeTypes;
    private $allowedExtensions;
    private $blockedExtensions;
    private $imageProcessor;
    private $errorLogFile;

    public function __construct(
        mysqli $mysqli,
        string $uploadDir = null,
        string $tempDir = null,
        int $maxFileSize = 52428800
    ) {
        $this->mysqli = $mysqli;
        // Use UPLOADS_MEDIA_DIR constant if defined, otherwise fall back to hardcoded path
        $this->uploadDir = $uploadDir ?? (defined('UPLOADS_MEDIA_DIR') ? UPLOADS_MEDIA_DIR : dirname(__DIR__) . '/public_html/uploads/media');
        $this->tempDir = $tempDir ?? (defined('UPLOADS_TEMP_DIR') ? UPLOADS_TEMP_DIR : dirname(__DIR__, 2) . '/storage/tmp');
        $this->maxFileSize = $maxFileSize;
        $this->errorLogFile = dirname(__DIR__, 2) . '/storage/logs/media-upload.log';
        
        // Ensure error log directory exists
        $logDir = dirname($this->errorLogFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Initialize MIME type support
        $this->allowedMimeTypes = [
            // Images
            'image/jpeg' => 'image',
            'image/png' => 'image',
            'image/webp' => 'image',
            'image/gif' => 'image',
            // Video
            'video/mp4' => 'video',
            'video/webm' => 'video',
            'video/quicktime' => 'video',
            // Audio
            'audio/mpeg' => 'audio',
            'audio/wav' => 'audio',
            'audio/webm' => 'audio',
            'audio/ogg' => 'audio',
            // Documents
            'application/pdf' => 'document',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-excel' => 'document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
        ];

        $this->allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $this->blockedExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'pht', 'exe', 'bat', 'sh', 'jsp', 'py', 'rb', 'dll', 'com', 'jar', 'vbs', 'js', 'asp', 'aspx', 'cgi'];

        // Image processor initialization
        if (extension_loaded('gd')) {
            $this->imageProcessor = new class {
                public function resizeImage(string $source, string $dest, int $maxWidth = 1920, int $maxHeight = 1080): bool {
                    $image = getimagesize($source);
                    if (!$image) return false;

                    list($origWidth, $origHeight) = $image;
                    
                    // Calculate new dimensions maintaining aspect ratio
                    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
                    if ($ratio >= 1) return copy($source, $dest); // No resize needed

                    $newWidth = (int)($origWidth * $ratio);
                    $newHeight = (int)($origHeight * $ratio);

                    $src = $this->loadImage($source);
                    if (!$src) return false;

                    $dst = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                    $result = $this->saveImage($dst, $dest, $source);
                    imagedestroy($src);
                    imagedestroy($dst);

                    return $result;
                }

                public function generateThumbnail(string $source, string $dest, int $size = 200): bool {
                    $image = getimagesize($source);
                    if (!$image) return false;

                    list($origWidth, $origHeight) = $image;
                    $src = $this->loadImage($source);
                    if (!$src) return false;

                    // Create square crop
                    $minDim = min($origWidth, $origHeight);
                    $x = (int)(($origWidth - $minDim) / 2);
                    $y = (int)(($origHeight - $minDim) / 2);

                    $dst = imagecreatetruecolor($size, $size);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagecopyresampled($dst, $src, 0, 0, $x, $y, $size, $size, $minDim, $minDim);

                    $result = $this->saveImage($dst, $dest, $source);
                    imagedestroy($src);
                    imagedestroy($dst);

                    return $result;
                }

                private function loadImage(string $path) {
                    $info = getimagesize($path);
                    if (!$info) return false;

                    $type = $info[2];
                    switch ($type) {
                        case IMAGETYPE_JPEG:
                            return imagecreatefromjpeg($path);
                        case IMAGETYPE_PNG:
                            return imagecreatefrompng($path);
                        case IMAGETYPE_GIF:
                            return imagecreatefromgif($path);
                        case IMAGETYPE_WEBP:
                            return imagecreatefromwebp($path);
                        default:
                            return false;
                    }
                }

                private function saveImage($image, string $dest, string $original): bool {
                    $info = getimagesize($original);
                    if (!$info) return false;

                    $type = $info[2];
                    switch ($type) {
                        case IMAGETYPE_JPEG:
                            return imagejpeg($image, $dest, 85);
                        case IMAGETYPE_PNG:
                            return imagepng($image, $dest, 8);
                        case IMAGETYPE_GIF:
                            return imagegif($image, $dest);
                        case IMAGETYPE_WEBP:
                            return imagewebp($image, $dest, 85);
                        default:
                            return false;
                    }
                }
            };
        }
    }

    /**
     * Log messages to file
     */
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = '[' . $timestamp . '] [MediaManager] ' . $message . "\n";
        
        if (!is_writable(dirname($this->errorLogFile))) {
            @mkdir(dirname($this->errorLogFile), 0755, true);
        }
        
        @file_put_contents($this->errorLogFile, $logMessage, FILE_APPEND | LOCK_EX);
        // Also send to PHP error_log
        logError('MediaManager::upload - ' . $message);
    }

    /**
     * Upload media file with validation and processing
     */
    public function upload(
        array $file,
        int $userId,
        string $mediaType = 'image',
        string $title = '',
        string $description = ''
    ): array {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                logError('MediaManager::upload - Validation failed: ' . $validation['error']);
                return ['success' => false, 'error' => $validation['error']];
            }

            // Sanitize and secure filename
            $originalName = $this->sanitizeFilename($file['name']);
            $secureName = $this->generateSecureFilename($originalName);
            $detectedMimeType = $this->detectMimeType($file['tmp_name']);

            logError('MediaManager::upload - Processing file: ' . $originalName . ' -> ' . $secureName . ' | MIME: ' . $detectedMimeType);

            // Ensure upload directory exists
            $destDir = $this->uploadDir . '/' . date('Y/m');
            if (!is_dir($destDir)) {
                $mkdirResult = mkdir($destDir, 0755, true);
                if (!$mkdirResult) {
                    $error = 'Failed to create upload directory: ' . $destDir;
                    logError('MediaManager::upload - ' . $error);
                    return ['success' => false, 'error' => $error];
                }
                logError('MediaManager::upload - Created directory: ' . $destDir);
            }

            // Verify directory is writable
            if (!is_writable($destDir)) {
                logError('MediaManager::upload - Directory not writable: ' . $destDir . ' | File: ' . $originalName);
                return ['success' => false, 'error' => 'Upload directory is not writable'];
            }

            // Move uploaded file
            $destPath = $destDir . '/' . $secureName;
            $this->log('Attempting move_uploaded_file | FROM: ' . $file['tmp_name'] . ' | TO: ' . $destPath . ' | Exists: ' . (is_uploaded_file($file['tmp_name']) ? 'yes' : 'no'));
            
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $destDirExists = is_dir($destDir) ? 'yes' : 'no';
                $destDirWritable = is_writable($destDir) ? 'yes' : 'no';
                $tmpFileExists = file_exists($file['tmp_name']) ? 'yes' : 'no';
                $error = 'Failed to save media file to: ' . $destPath;
                $this->log('MOVE_UPLOADED_FILE FAILED | Error checking: DestDir exists=' . $destDirExists . ' | Writable=' . $destDirWritable . ' | TmpFile exists=' . $tmpFileExists . ' | TmpPath=' . $file['tmp_name']);
                logError('MediaManager::upload - ' . $error);
                return ['success' => false, 'error' => $error];
            }

            // Verify file was actually moved
            if (!file_exists($destPath)) {
                $this->log('File move claimed success but file does not exist at: ' . $destPath);
                logError('MediaManager::upload - File verification failed after move');
                return ['success' => false, 'error' => 'File verification failed after move'];
            }

            $this->log('File successfully moved to: ' . $destPath);

            $filePath = date('Y/m') . '/' . $secureName;

            // Process images (resize, generate thumbnail)
            $dimensions = null;
            $thumbnailPath = null;
            
            if (strpos($detectedMimeType, 'image/') === 0 && $this->imageProcessor) {
                $imageInfo = @getimagesize($destPath);
                if ($imageInfo) {
                    $dimensions = ['width' => $imageInfo[0], 'height' => $imageInfo[1]];

                    // Resize if needed
                    if ($imageInfo[0] > 1920 || $imageInfo[1] > 1080) {
                        if ($this->imageProcessor->resizeImage($destPath, $destPath, 1920, 1080)) {
                            logError('MediaManager::upload - Image resized for: ' . $originalName);
                        }
                    }

                    // Generate thumbnail
                    $thumbnailName = pathinfo($secureName, PATHINFO_FILENAME) . '_thumb.' . pathinfo($secureName, PATHINFO_EXTENSION);
                    $thumbnailFullPath = $destDir . '/' . $thumbnailName;
                    if ($this->imageProcessor->generateThumbnail($destPath, $thumbnailFullPath)) {
                        $thumbnailPath = date('Y/m') . '/' . $thumbnailName;
                        logError('MediaManager::upload - Thumbnail created for: ' . $originalName);
                    }
                }
            }

            // Get file size after all processing
            $fileSizeBytes = filesize($destPath);
            if ($fileSizeBytes === false) {
                logError('MediaManager::upload - Cannot read file size for: ' . $destPath);
                @unlink($destPath);
                if ($thumbnailPath) @unlink($destDir . '/' . basename($thumbnailPath));
                return ['success' => false, 'error' => 'Cannot verify file size'];
            }

            // Save to database
            $mediaId = $this->saveToDatabase(
                $userId,
                $originalName,
                $filePath,
                $detectedMimeType,
                $fileSizeBytes,
                $mediaType,
                $title,
                $description,
                $thumbnailPath,
                $dimensions
            );

            if (!$mediaId) {
                $error = 'Failed to save media record to database';
                @unlink($destPath);
                if ($thumbnailPath) @unlink($this->uploadDir . '/' . $thumbnailPath);
                logError('MediaManager::upload - ' . $error . ' | File will be cleaned up');
                return ['success' => false, 'error' => $error];
            }

            logError('MediaManager::upload - Success! Media ID: ' . $mediaId . ', User: ' . $userId . ', Type: ' . $mediaType);

            return [
                'success' => true,
                'id' => $mediaId,
                'path' => $filePath,
                'thumbnail' => $thumbnailPath,
                'mime_type' => $detectedMimeType,
                'dimensions' => $dimensions
            ];
        } catch (Throwable $e) {
            logError('MediaManager::upload - Exception: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | File: ' . ($file['name'] ?? 'unknown'));
            return ['success' => false, 'error' => 'Upload error: ' . $e->getMessage()];
        }
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server limits',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form limits',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory missing',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'File extension not allowed'
            ];
            return ['valid' => false, 'error' => $errors[$file['error']] ?? 'Upload error'];
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            logError('MediaManager::validateFile - Invalid upload: tmp_name missing or not uploaded file');
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }

        $fileSize = filesize($file['tmp_name']);
        if ($fileSize > $this->maxFileSize) {
            $maxSizeFormatted = formatFileSize($this->maxFileSize);
            $fileSizeFormatted = formatFileSize($fileSize);
            logError('MediaManager::validateFile - File too large: ' . $fileSizeFormatted . ' exceeds ' . $maxSizeFormatted);
            return ['valid' => false, 'error' => 'File size exceeds maximum (' . $maxSizeFormatted . ')'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $this->blockedExtensions)) {
            logError('MediaManager::validateFile - Blocked extension: ' . $ext);
            return ['valid' => false, 'error' => 'File type (.' . $ext . ') is not allowed'];
        }

        $mimeType = $this->detectMimeType($file['tmp_name']);
        if (!isset($this->allowedMimeTypes[$mimeType])) {
            logError('MediaManager::validateFile - Disallowed MIME type: ' . $mimeType . ' for file: ' . $file['name']);
            return ['valid' => false, 'error' => 'File type (' . $mimeType . ') not allowed'];
        }

        return ['valid' => true];
    }

    /**
     * Detect actual MIME type using multiple methods
     */
    private function detectMimeType(string $filePath): string {
        // Try finfo first (most reliable)
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mimeType) return $mimeType;
        }

        // Fallback to mime_content_type
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType) return $mimeType;
        }

        return 'application/octet-stream';
    }

    /**
     * Sanitize filename to prevent traversal attacks
     */
    private function sanitizeFilename(string $filename): string {
        // Remove any path components
        $filename = basename($filename);
        // Remove special characters
        $filename = preg_replace('/[^\w\s\-\.]/', '', $filename);
        // Replace multiple spaces with single space
        $filename = preg_replace('/\s+/', '-', trim($filename));
        return $filename ?: 'file-' . time();
    }

    /**
     * Generate secure filename with timestamp to prevent collisions
     */
    private function generateSecureFilename(string $originalName): string {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = preg_replace('/[^\w\-]/', '', $name);
        return $name . '-' . uniqid() . '.' . $ext;
    }

    /**
     * Save media record to database with comprehensive error logging
     */
    private function saveToDatabase(
        int $userId,
        string $originalName,
        string $filePath,
        string $mimeType,
        int $fileSize,
        string $mediaType,
        string $title,
        string $description,
        ?string $thumbnailPath,
        ?array $dimensions
    ): ?int {
        try {
            if (!$this->mysqli) {
                logError('MediaManager::saveToDatabase - Database connection missing');
                return null;
            }

            $stmt = $this->mysqli->prepare(
                'INSERT INTO media (user_id, title, description, file_path, thumbnail_path, original_name, mime_type, media_type, file_size, width, height, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );

            if (!$stmt) {
                logError('MediaManager::saveToDatabase - Prepare failed: ' . $this->mysqli->error);
                return null;
            }

            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;
            $titleToSave = $title ?: $originalName;

            // Ensure types are correct - convert nulls properly
            $userId = (int)$userId;
            $fileSize = (int)$fileSize;
            $width = $width !== null ? (int)$width : null;
            $height = $height !== null ? (int)$height : null;

            logError('MediaManager::saveToDatabase - Preparing to bind: userId=' . $userId . ', fileSize=' . $fileSize . ', width=' . ($width ?? 'null') . ', height=' . ($height ?? 'null') . ', thumbnailPath=' . ($thumbnailPath ?? 'null'));

            // Build type string and parameters array
            // Types: i(userId) + s(title, desc, file_path, thumbnail_path, original_name, mime_type, media_type) + i(file_size, width, height)
            // Count: 1 + 8 + 3 = 12... wait, that's wrong. Let me count params:
            // 1:userId(i), 2:title(s), 3:desc(s), 4:file_path(s), 5:thumbnail_path(s), 6:original_name(s), 7:mime_type(s), 8:media_type(s), 9:file_size(i), 10:width(i), 11:height(i)
            // That's i + 6s + i + i = i + 6s + 2i... no wait: 1i + 6s + 1i + 1i = 1i + 6s + 2i
            // Actually: user_id=i, title=s, description=s, file_path=s, thumbnail_path=s, original_name=s, mime_type=s, media_type=s, file_size=i, width=i, height=i
            // That's: i + s + s + s + s + s + s + s + i + i + i = i + 7s + 3i = isssssssiii (11 chars)
            $types = 'isssssssiii';
            
            // Build params array - order MUST match SQL INSERT statement
            $params = array(
                $userId,
                $titleToSave,
                $description,
                $filePath,
                $thumbnailPath,
                $originalName,
                $mimeType,
                $mediaType,
                $fileSize,
                $width,
                $height
            );

            // Verify parameter count
            if (count($params) !== strlen($types)) {
                logError('MediaManager::saveToDatabase - Parameter mismatch: Expected ' . strlen($types) . ' params, got ' . count($params));
                $stmt->close();
                return null;
            }

            // Use safe bind_param method that works in PHP 8+
            try {
                // Create reference array properly
                $refs = array();
                foreach($params as $key => $value) {
                    $refs[$key] = &$params[$key];
                }
                
                // Combine type string with references
                array_unshift($refs, $types);
                
                // Bind using call_user_func_array with proper reference handling
                $bindSuccess = call_user_func_array(array($stmt, 'bind_param'), $refs);
                
                if (!$bindSuccess) {
                    logError('MediaManager::saveToDatabase - Bind param failed: ' . $stmt->error);
                    $stmt->close();
                    return null;
                }
                
                logError('MediaManager::saveToDatabase - Parameters bound successfully');
            } catch (Throwable $bindEx) {
                logError('MediaManager::saveToDatabase - Bind exception: ' . $bindEx->getMessage() . ' at line ' . $bindEx->getLine());
                $stmt->close();
                return null;
            }

            if ($stmt->execute()) {
                $insertId = (int)$this->mysqli->insert_id;
                $stmt->close();
                logError('MediaManager::saveToDatabase - Successfully saved media ID: ' . $insertId . ' for user: ' . $userId . ', file: ' . $originalName);
                return $insertId;
            }

            logError('MediaManager::saveToDatabase - Execute failed: ' . $stmt->error . ' | User: ' . $userId . ', File: ' . $originalName . ', FilePath: ' . $filePath . ', Dimensions: width=' . ($width ?? 'null') . ', height=' . ($height ?? 'null'));
            $stmt->close();
            return null;
        } catch (Throwable $e) {
            logError('MediaManager::saveToDatabase - Exception: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | User: ' . $userId . ', File: ' . $originalName);
            return null;
        }
    }

    /**
     * Delete media (soft delete)
     */
    public function softDelete(int $mediaId, int $userId): array {
        try {
            $stmt = $this->mysqli->prepare(
                'UPDATE media SET deleted_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
            );
            $stmt->bind_param('ii', $mediaId, $userId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                return ['success' => true, 'message' => 'Media deleted'];
            }

            return ['success' => false, 'error' => 'Media not found'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Delete error: ' . $e->getMessage()];
        }
    }

    /**
     * Permanently delete media (hard delete)
     */
    public function hardDelete(int $mediaId, int $userId): array {
        try {
            // Get media info first
            $stmt = $this->mysqli->prepare('SELECT file_path, thumbnail_path FROM media WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $mediaId, $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if (!$result) {
                return ['success' => false, 'error' => 'Media not found'];
            }

            // Delete files from disk
            @unlink($this->uploadDir . '/' . $result['file_path']);
            if ($result['thumbnail_path']) {
                @unlink($this->uploadDir . '/' . $result['thumbnail_path']);
            }

            // Delete from database
            $stmt = $this->mysqli->prepare('DELETE FROM media WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $mediaId, $userId);

            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Media permanently deleted'];
            }

            return ['success' => false, 'error' => 'Database deletion failed'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Hard delete error: ' . $e->getMessage()];
        }
    }

    /**
     * Get media file URL
     */
    public function getMediaUrl(string $filePath): string {
        return '/uploads/media/' . $filePath;
    }

    /**
     * Check if media exists and is accessible
     */
    public function mediaExists(int $mediaId, int $userId): bool {
        $stmt = $this->mysqli->prepare('SELECT id FROM media WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('ii', $mediaId, $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}
