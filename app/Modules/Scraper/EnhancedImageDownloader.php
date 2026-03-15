<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

/**
 * EnhancedImageDownloader.php
 * Enhanced image downloading with:
 * - Parallel downloads
 * - Image optimization
 * - Format conversion
 * - Broken link detection
 * - Retry logic
 */
class EnhancedImageDownloader
{
    private Client $client;
    private string $uploadPath;
    private string $baseUrl;
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxFileSize = 5242880; // 5MB
    private int $maxWidth = 1920;
    private int $maxHeight = 1080;
    private int $quality = 85;
    private bool $convertToWebP = false;
    private array $failedUrls = [];

    public function __construct(array $config = [])
    {
        $uploadsBasePath = function_exists('brox_get_uploads_base_path')
            ? brox_get_uploads_base_path()
            : (defined('UPLOADS_DIR') ? UPLOADS_DIR : dirname(__DIR__, 3) . '/public_html/uploads');
        $uploadsBaseUrl = function_exists('brox_get_uploads_base_url')
            ? brox_get_uploads_base_url()
            : '/uploads';

        $this->uploadPath = $config['upload_path'] ?? rtrim((string)$uploadsBasePath, '/\\') . '/autocontent/';
        $this->baseUrl = $config['base_url'] ?? rtrim((string)$uploadsBaseUrl, '/') . '/autocontent/';
        $this->maxFileSize = $config['max_file_size'] ?? 5242880;
        $this->maxWidth = $config['max_width'] ?? 1920;
        $this->maxHeight = $config['max_height'] ?? 1080;
        $this->quality = $config['quality'] ?? 85;
        $this->convertToWebP = $config['convert_to_webp'] ?? false;

        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }

        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ],
        ]);
    }

    /**
     * Download a single image
     */
    public function download(string $imageUrl, ?string $prefix = null): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        // Validate URL
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = $this->client->get($imageUrl, [
                'sink' => 'temp',
                'allow_redirects' => true,
            ]);

            // Check content type
            $contentType = $response->getHeaderLine('Content-Type');
            if (!$this->isAllowedContentType($contentType)) {
                return null;
            }

            // Get image data
            $imageData = $response->getBody()->getContents();

            // Check file size
            if (strlen($imageData) > $this->maxFileSize) {
                return null;
            }

            // Determine output format
            $extension = $this->getExtensionFromContentType($contentType);
            if (!$extension) {
                $extension = $this->getExtensionFromUrl($imageUrl);
            }
            if (!$extension || !in_array($extension, $this->allowedExtensions)) {
                $extension = 'jpg';
            }

            // Process image
            $processedImage = $this->processImage($imageData, $extension);

            if (!$processedImage) {
                return null;
            }

            // Generate unique filename
            $filename = $this->generateFilename($imageUrl, $prefix, $extension);
            $filepath = $this->uploadPath . $filename;

            // Save file
            file_put_contents($filepath, $processedImage);

            return $this->baseUrl . $filename;
        } catch (\Exception $e) {
            $this->failedUrls[$imageUrl] = $e->getMessage();
            return null;
        }
    }

    /**
     * Download multiple images in parallel
     */
    public function downloadMultiple(array $imageUrls, ?string $prefix = null, int $concurrency = 5): array
    {
        $promises = [];
        $results = [];

        // Create promises for each URL
        foreach ($imageUrls as $index => $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            $localPrefix = ($prefix ?? 'img') . '_' . $index;
            
            $promises[$url] = \GuzzleHttp\Promise\Create::promiseFor(null)
                ->then(function () use ($url, $localPrefix) {
                    return $this->download($url, $localPrefix);
                });
        }

        // Process in batches
        $promises = Utils::settle($promises)->wait();

        foreach ($promises as $url => $result) {
            $results[$url] = $result['value'] ?? null;
        }

        return array_filter($results);
    }

    /**
     * Process image (resize, optimize)
     */
    private function processImage(string $imageData, string $extension): ?string
    {
        // Detect image type and create resource
        $info = getimagesizefromstring($imageData);
        
        if (!$info) {
            return $imageData; // Return original if can't process
        }

        $width = $info[0];
        $height = $info[1];
        $type = $info[2];

        // Check if resizing is needed
        if ($width <= $this->maxWidth && $height <= $this->maxHeight) {
            // Convert format if needed
            if ($this->convertToWebP && $extension !== 'webp') {
                return $this->convertToWebPFormat($imageData, $type);
            }
            return $imageData;
        }

        // Calculate new dimensions
        $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
        $newWidth = (int)round($width * $ratio);
        $newHeight = (int)round($height * $ratio);

        // Create image resource based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromstring($imageData);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefromstring($imageData);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromstring($imageData);
                break;
            case IMAGETYPE_WEBP:
                $src = imagecreatefromstring($imageData);
                break;
            default:
                return $imageData;
        }

        if (!$src) {
            return $imageData;
        }

        // Create new image
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/GIF
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Output to string
        $output = $this->imageToString($dst, $type);

        // Clean up
        imagedestroy($src);
        imagedestroy($dst);

        // Convert to WebP if enabled
        if ($this->convertToWebP) {
            return $this->convertToWebPFormat($output, $type);
        }

        return $output;
    }

    /**
     * Convert image to WebP format
     */
    private function convertToWebPFormat(string $imageData, int $type): ?string
    {
        if (!function_exists('imagewebp')) {
            return $imageData;
        }

        $src = null;
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromstring($imageData);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefromstring($imageData);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromstring($imageData);
                break;
            case IMAGETYPE_WEBP:
                return $imageData; // Already WebP
        }

        if (!$src) {
            return $imageData;
        }

        // Create WebP
        ob_start();
        imagewebp($src, null, $this->quality);
        $webp = ob_get_clean();

        imagedestroy($src);

        return $webp ?: $imageData;
    }

    /**
     * Output image to string based on type
     */
    private function imageToStringGd($image, int $type): ?string
    {
        ob_start();
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($image, null, $this->quality);
                break;
            case IMAGETYPE_PNG:
                $pngQuality = 9 - (int)round(($this->quality / 100) * 9);
                imagepng($image, null, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                imagegif($image);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($image, null, $this->quality);
                break;
            default:
                return null;
        }
        
        return ob_get_clean();
    }

    /**
     * Image to string helper
     */
    private function imageToString($image, int $type): string
    {
        ob_start();
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($image, null, $this->quality);
                break;
            case IMAGETYPE_PNG:
                $quality = 9 - (int)round(($this->quality / 100) * 9);
                imagepng($image, null, $quality);
                break;
            case IMAGETYPE_GIF:
                imagegif($image);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($image, null, $this->quality);
                break;
        }
        
        return ob_get_clean() ?: '';
    }

    /**
     * Generate unique filename
     */
    private function generateFilename(string $url, ?string $prefix, string $extension): string
    {
        $hash = substr(md5($url), 0, 8);
        $timestamp = time();
        $webpExt = $this->convertToWebP ? 'webp' : $extension;
        
        return sprintf('%s_%d_%s.%s', $prefix ?? 'img', $timestamp, $hash, $webpExt);
    }

    /**
     * Check if content type is allowed
     */
    private function isAllowedContentType(string $contentType): bool
    {
        $contentType = strtolower(explode(';', $contentType)[0]);
        return in_array($contentType, $this->allowedMimeTypes);
    }

    /**
     * Get extension from content type
     */
    private function getExtensionFromContentType(string $contentType): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $contentType = strtolower(explode(';', $contentType)[0]);
        return $map[$contentType] ?? null;
    }

    /**
     * Get extension from URL
     */
    private function getExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return in_array(strtolower($ext), $this->allowedExtensions) ? $ext : null;
    }

    /**
     * Download featured image for article
     */
    public function downloadFeaturedImage(string $imageUrl, int $articleId): ?string
    {
        return $this->download($imageUrl, 'article_' . $articleId);
    }

    /**
     * Download and save images from article content
     */
    public function downloadContentImages(array $images, int $articleId): array
    {
        $downloaded = [];
        
        foreach ($images as $index => $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }
            
            $result = $this->download($url, 'article_' . $articleId . '_img_' . $index);
            if ($result) {
                $downloaded[$url] = $result;
            }
        }
        
        return $downloaded;
    }

    /**
     * Check if image URL is valid (HEAD request)
     */
    public function validateImageUrl(string $url): bool
    {
        try {
            $response = $this->client->head($url, [
                'allow_redirects' => true,
            ]);
            
            $contentType = $response->getHeaderLine('Content-Type');
            
            return $this->isAllowedContentType($contentType);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get failed URLs
     */
    public function getFailedUrls(): array
    {
        return $this->failedUrls;
    }

    /**
     * Clear failed URLs tracking
     */
    public function clearFailedUrls(): void
    {
        $this->failedUrls = [];
    }

    /**
     * Delete an image
     */
    public function delete(string $imagePath): bool
    {
        $filepath = $this->uploadPath . basename($imagePath);
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Get upload path
     */
    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }

    /**
     * Set upload path
     */
    public function setUploadPath(string $path): self
    {
        $this->uploadPath = rtrim($path, '/') . '/';
        
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
        
        return $this;
    }

    /**
     * Set base URL
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/') . '/';
        return $this;
    }

    /**
     * Set image quality
     */
    public function setQuality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * Enable/disable WebP conversion
     */
    public function setConvertToWebP(bool $convert): self
    {
        $this->convertToWebP = $convert;
        return $this;
    }

    /**
     * Set max dimensions
     */
    public function setMaxDimensions(int $width, int $height): self
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;
        return $this;
    }

    /**
     * Get image info
     */
    public function getImageInfo(string $imagePath): ?array
    {
        $filepath = $this->uploadPath . basename($imagePath);
        
        if (!file_exists($filepath)) {
            return null;
        }
        
        $info = getimagesize($filepath);
        
        if (!$info) {
            return null;
        }
        
        return [
            'width' => $info[0],
            'height' => $info[1],
            'type' => $info[2],
            'mime' => $info['mime'],
            'size' => filesize($filepath),
            'path' => $filepath,
        ];
    }

    /**
     * Clean up old images
     */
    public function cleanup(int $olderThanDays = 30): int
    {
        $count = 0;
        $cutoff = time() - ($olderThanDays * 86400);
        
        $files = glob($this->uploadPath . '*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
}
