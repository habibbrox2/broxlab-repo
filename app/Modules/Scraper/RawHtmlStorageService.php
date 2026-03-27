<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * RawHtmlStorageService.php
 * Service for saving and loading raw HTML files
 * Enables two-phase scraping: fetch → save → parse from file
 */
class RawHtmlStorageService
{
    private string $storageBasePath;
    private array $config;

    public function __construct(?string $storageBasePath = null, array $config = [])
    {
        $this->storageBasePath = $storageBasePath ?? $this->getDefaultStoragePath();
        $this->config = $config + [
            'create_subdirs' => true,
            'file_extension' => '.html',
            'max_filename_length' => 200,
        ];
    }

    /**
     * Get default storage path
     */
    private function getDefaultStoragePath(): string
    {
        return dirname(__DIR__, 3) . '/storage/scraper/raw_html';
    }

    /**
     * Save raw HTML to file
     *
     * @param string $url Source URL (used for generating filename)
     * @param string $html Raw HTML content
     * @param string $sourceName Source identifier (e.g., 'teletalk', 'bdnews24')
     * @param string $pageType Page type: 'listing' or 'detail'
     * @return array{success: bool, file_path: string|null, error: string|null}
     */
    public function save(string $url, string $html, string $sourceName, string $pageType = 'listing'): array
    {
        try {
            $filePath = $this->generateFilePath($url, $sourceName, $pageType);
            $dirPath = dirname($filePath);

            // Create directory if it doesn't exist
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }

            // Save HTML content
            $bytesWritten = file_put_contents($filePath, $html);

            if ($bytesWritten === false) {
                return [
                    'success' => false,
                    'file_path' => null,
                    'error' => 'Failed to write HTML file',
                ];
            }

            return [
                'success' => true,
                'file_path' => $filePath,
                'error' => null,
            ];
        } catch (\Exception $e) {
            logError("RawHtmlStorageService: Save error: " . $e->getMessage());
            return [
                'success' => false,
                'file_path' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Load raw HTML from file
     *
     * @param string $url Source URL (used for generating filename)
     * @param string $sourceName Source identifier
     * @param string $pageType Page type: 'listing' or 'detail'
     * @return array{success: bool, html: string|null, file_path: string|null, error: string|null}
     */
    public function load(string $url, string $sourceName, string $pageType = 'listing'): array
    {
        try {
            $filePath = $this->generateFilePath($url, $sourceName, $pageType);

            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'html' => null,
                    'file_path' => $filePath,
                    'error' => 'HTML file not found',
                ];
            }

            $html = file_get_contents($filePath);

            if ($html === false) {
                return [
                    'success' => false,
                    'html' => null,
                    'file_path' => $filePath,
                    'error' => 'Failed to read HTML file',
                ];
            }

            return [
                'success' => true,
                'html' => $html,
                'file_path' => $filePath,
                'error' => null,
            ];
        } catch (\Exception $e) {
            logError("RawHtmlStorageService: Load error: " . $e->getMessage());
            return [
                'success' => false,
                'html' => null,
                'file_path' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if raw HTML file exists
     */
    public function exists(string $url, string $sourceName, string $pageType = 'listing'): bool
    {
        $filePath = $this->generateFilePath($url, $sourceName, $pageType);
        return file_exists($filePath);
    }

    /**
     * Delete raw HTML file
     */
    public function delete(string $url, string $sourceName, string $pageType = 'listing'): bool
    {
        $filePath = $this->generateFilePath($url, $sourceName, $pageType);

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * Generate file path from URL and source
     */
    public function generateFilePath(string $url, string $sourceName, string $pageType = 'listing'): string
    {
        // Create date-based subdirectory
        $dateDir = date('Y-m-d');

        // Generate filename from URL
        $filename = $this->urlToFilename($url);

        // Add page type suffix
        $filename .= '_' . $pageType;

        // Add file extension
        $filename .= $this->config['file_extension'];

        // Build full path
        return $this->storageBasePath . '/' . $sourceName . '/' . $dateDir . '/' . $filename;
    }

    /**
     * Convert URL to safe filename
     */
    private function urlToFilename(string $url): string
    {
        // Parse URL
        $parsed = parse_url($url);

        // Build filename from host + path + query
        $parts = [];

        if (isset($parsed['host'])) {
            $parts[] = $parsed['host'];
        }

        if (isset($parsed['path'])) {
            $path = trim($parsed['path'], '/');
            $path = str_replace('/', '_', $path);
            $parts[] = $path;
        }

        if (isset($parsed['query'])) {
            $query = str_replace(['=', '&'], ['_', '-'], $parsed['query']);
            $parts[] = $query;
        }

        $filename = implode('_', $parts);

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');

        // Truncate if too long
        if (strlen($filename) > $this->config['max_filename_length']) {
            $filename = substr($filename, 0, $this->config['max_filename_length']);
        }

        return $filename;
    }

    /**
     * Get storage statistics
     */
    public function getStats(string $sourceName = null): array
    {
        $stats = [
            'total_files' => 0,
            'total_size_bytes' => 0,
            'sources' => [],
        ];

        $scanPath = $this->storageBasePath;
        if ($sourceName) {
            $scanPath .= '/' . $sourceName;
        }

        if (!is_dir($scanPath)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $stats['total_files']++;
                $stats['total_size_bytes'] += $file->getSize();

                // Track by source
                $relativePath = str_replace($this->storageBasePath . '/', '', $file->getPath());
                $source = explode('/', $relativePath)[0] ?? 'unknown';

                if (!isset($stats['sources'][$source])) {
                    $stats['sources'][$source] = [
                        'files' => 0,
                        'size_bytes' => 0,
                    ];
                }

                $stats['sources'][$source]['files']++;
                $stats['sources'][$source]['size_bytes'] += $file->getSize();
            }
        }

        return $stats;
    }

    /**
     * Clean up old HTML files
     */
    public function cleanup(int $daysOld = 30, string $sourceName = null): array
    {
        $deleted = 0;
        $errors = 0;
        $cutoffTime = time() - ($daysOld * 86400);

        $scanPath = $this->storageBasePath;
        if ($sourceName) {
            $scanPath .= '/' . $sourceName;
        }

        if (!is_dir($scanPath)) {
            return ['deleted' => 0, 'errors' => 0];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                if ($file->getMTime() < $cutoffTime) {
                    if (unlink($file->getPathname())) {
                        $deleted++;
                    } else {
                        $errors++;
                    }
                }
            }
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }
}
