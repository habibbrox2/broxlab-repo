<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use GuzzleHttp\Client;

/**
 * ImageDownloaderService.php
 * Downloads and saves images from scraped content
 */
class ImageDownloaderService
{
    private Client $client;
    private string $uploadPath;
    private string $baseUrl;
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private int $maxFileSize = 5242880; // 5MB

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

        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }

        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
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

        try {
            $response = $this->client->get($imageUrl, [
                'sink' => 'temp'
            ]);

            // Check content type
            $contentType = $response->getHeaderLine('Content-Type');
            if (!$this->isAllowedContentType($contentType)) {
                return null;
            }

            // Get extension from content type or URL
            $extension = $this->getExtensionFromContentType($contentType);
            if (!$extension) {
                $extension = $this->getExtensionFromUrl($imageUrl);
            }

            if (!$extension || !in_array($extension, $this->allowedExtensions)) {
                $extension = 'jpg';
            }

            // Generate unique filename
            $filename = ($prefix ?? 'img') . '_' . time() . '_' . substr(md5($imageUrl), 0, 8) . '.' . $extension;
            $filepath = $this->uploadPath . $filename;

            // Move temp file to upload path
            $content = $response->getBody()->getContents();

            // Check file size
            if (strlen($content) > $this->maxFileSize) {
                return null;
            }

            file_put_contents($filepath, $content);

            return $this->baseUrl . $filename;
        } catch (\Exception $e) {
            error_log("Image Download Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Download multiple images
     */
    public function downloadMultiple(array $imageUrls, ?string $prefix = null): array
    {
        $downloaded = [];

        foreach ($imageUrls as $index => $url) {
            $url = trim($url);
            if (empty($url)) continue;

            $result = $this->download($url, ($prefix ?? 'img') . '_' . $index);
            if ($result) {
                $downloaded[] = $result;
            }
        }

        return $downloaded;
    }

    /**
     * Download featured image for article
     */
    public function downloadFeaturedImage(string $imageUrl, int $articleId): ?string
    {
        return $this->download($imageUrl, 'article_' . $articleId);
    }

    /**
     * Check if content type is allowed
     */
    private function isAllowedContentType(string $contentType): bool
    {
        $allowed = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        return in_array(strtolower($contentType), $allowed);
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
            'image/webp' => 'webp'
        ];

        return $map[strtolower($contentType)] ?? null;
    }

    /**
     * Get extension from URL
     */
    private function getExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) return null;

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return in_array(strtolower($ext), $this->allowedExtensions) ? $ext : null;
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
}
