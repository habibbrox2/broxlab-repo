<?php

/**
 * StorageCleanupHelper.php
 * 
 * Handles automatic cleanup of old files in storage/ directory
 * Removes temporary files, old logs, and cache based on age
 * 
 * Usage:
 * - Manual: StorageCleanupHelper::cleanupAllDirectories()
 * - Scheduled: Via cron job: php -f /path/to/storage-cleanup-task.php
 */

class StorageCleanupHelper
{
    private static $basePath;
    private static $logFile;
    private static $lastRunFile;
    private static $lockFile;
    private static $echoLogs = false;

    // Retention policies (in days)
    private static $retentionPolicies = [
        'logs' => 30,          // Keep logs for 30 days
        'cache' => 7,          // Keep cache for 7 days
        'temp' => 1,           // Keep temp files for 1 day
        'tmp' => 1,            // Keep tmp files for 1 day
        'uploads' => 365,      // Keep uploads for 1 year
    ];

    /**
     * Initialize cleanup helper
     */
    public static function init($basePath = null, ?bool $echoLogs = null)
    {
        self::$basePath = $basePath ?? dirname(__DIR__, 2);
        self::$logFile = self::$basePath . '/storage/logs/cleanup.log';
        self::$lastRunFile = self::$basePath . '/storage/logs/cleanup.last_run';
        self::$lockFile = self::$basePath . '/storage/tmp/storage_cleanup.lock';
        self::$echoLogs = $echoLogs ?? (PHP_SAPI === 'cli');

        foreach ([dirname(self::$logFile), dirname(self::$lockFile)] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Log cleanup activity
     */
    private static function log($message, $level = 'INFO')
    {
        if (!self::$basePath) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";

        error_log($logMessage, 3, self::$logFile);
        if (self::$echoLogs) {
            echo $logMessage;
        }
    }

    /**
     * Control whether cleanup logs should also be echoed to output.
     */
    public static function setEchoLogs(bool $echoLogs): void
    {
        self::$echoLogs = $echoLogs;
    }

    public static function getLastRunFilePath(): string
    {
        if (!self::$basePath) {
            self::init();
        }

        return self::$lastRunFile;
    }

    public static function getLockFilePath(): string
    {
        if (!self::$basePath) {
            self::init();
        }

        return self::$lockFile;
    }

    /**
     * Set custom retention policy for a directory
     */
    public static function setRetentionPolicy($directory, $days)
    {
        self::$retentionPolicies[$directory] = (int)$days;
    }

    /**
     * Apply retention policies from environment variables when available.
     */
    public static function applyEnvironmentPolicies(): void
    {
        $policyMap = [
            'logs' => 'STORAGE_LOG_RETENTION_DAYS',
            'cache' => 'STORAGE_CACHE_RETENTION_DAYS',
            'temp' => 'STORAGE_TEMP_RETENTION_DAYS',
            'tmp' => 'STORAGE_TMP_RETENTION_DAYS',
            'uploads' => 'STORAGE_UPLOADS_RETENTION_DAYS',
        ];

        foreach ($policyMap as $directory => $envKey) {
            $value = $_ENV[$envKey] ?? getenv($envKey);
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $days = (int)$value;
            if ($days > 0) {
                self::setRetentionPolicy($directory, $days);
            }
        }
    }

    /**
     * Run cleanup automatically if the configured interval has elapsed.
     *
     * @return array<string, mixed>
     */
    public static function runAutomaticCleanupIfDue(?int $intervalSeconds = null): array
    {
        if (!self::$basePath) {
            self::init();
        }

        self::applyEnvironmentPolicies();

        $intervalSeconds = $intervalSeconds
            ?? (int)($_ENV['STORAGE_CLEANUP_INTERVAL_SECONDS'] ?? getenv('STORAGE_CLEANUP_INTERVAL_SECONDS') ?: 21600);

        if ($intervalSeconds <= 0) {
            return [
                'ran' => false,
                'reason' => 'disabled',
            ];
        }

        $handle = @fopen(self::$lockFile, 'c+');
        if ($handle === false) {
            self::log('Failed to open storage cleanup lock file.', 'ERROR');
            return [
                'ran' => false,
                'reason' => 'lock_open_failed',
            ];
        }

        try {
            if (!@flock($handle, LOCK_EX | LOCK_NB)) {
                return [
                    'ran' => false,
                    'reason' => 'locked',
                ];
            }

            clearstatcache(true, self::$lastRunFile);
            $lastRun = is_file(self::$lastRunFile) ? (int)@filemtime(self::$lastRunFile) : 0;
            $now = time();

            if ($lastRun > 0 && ($now - $lastRun) < $intervalSeconds) {
                return [
                    'ran' => false,
                    'reason' => 'not_due',
                    'next_run_in' => $intervalSeconds - ($now - $lastRun),
                ];
            }

            $summary = self::cleanupAllDirectories();
            @touch(self::$lastRunFile, $now);

            return [
                'ran' => true,
                'reason' => 'completed',
                'summary' => $summary,
            ];
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * Run cleanup immediately and update the last-run marker.
     *
     * @return array<string, mixed>
     */
    public static function runCleanupNow(): array
    {
        if (!self::$basePath) {
            self::init();
        }

        self::applyEnvironmentPolicies();
        $summary = self::cleanupAllDirectories();
        @touch(self::$lastRunFile, time());

        return [
            'ran' => true,
            'reason' => 'completed',
            'summary' => $summary,
        ];
    }

    /**
     * Cleanup all storage directories
     * 
     * @return array Summary of cleanup operations
     */
    public static function cleanupAllDirectories()
    {
        if (!self::$basePath) {
            self::init();
        }

        self::log("=== BroxLab Storage Cleanup Started ===");

        $summary = [
            'total_files_deleted' => 0,
            'total_size_freed' => 0,
            'directories_processed' => [],
            'errors' => [],
        ];

        $storageDir = self::$basePath . '/storage';

        if (!is_dir($storageDir)) {
            self::log("Storage directory not found: $storageDir", 'ERROR');
            return $summary;
        }

        foreach (self::$retentionPolicies as $dir => $retentionDays) {
            $fullPath = $storageDir . '/' . $dir;

            if (!is_dir($fullPath)) {
                self::log("Directory not found: $dir (skipped)");
                continue;
            }

            $result = self::cleanupDirectory($fullPath, $retentionDays);

            $summary['total_files_deleted'] += $result['files_deleted'];
            $summary['total_size_freed'] += $result['size_freed'];
            $summary['directories_processed'][$dir] = $result;
        }

        self::log("=== Cleanup Completed ===");
        self::log("Files deleted: {$summary['total_files_deleted']}");
        self::log("Size freed: " . self::formatBytes($summary['total_size_freed']));

        return $summary;
    }

    /**
     * Cleanup specific directory
     * 
     * @param string $dirPath Directory path
     * @param int $retentionDays Files older than this many days will be deleted
     * @return array Cleanup summary
     */
    public static function cleanupDirectory($dirPath, $retentionDays)
    {
        $result = [
            'files_deleted' => 0,
            'size_freed' => 0,
            'errors' => [],
        ];

        if (!is_dir($dirPath)) {
            return $result;
        }

        $cutoffTime = time() - ($retentionDays * 86400); // 86400 seconds = 1 day
        $dirName = basename($dirPath);

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $filePath = $item->getPathname();

                if (self::isProtectedPath($filePath)) {
                    continue;
                }

                $fileTime = @filemtime($filePath);
                if ($fileTime === false) {
                    continue;
                }

                $relativePath = ltrim(str_replace('\\', '/', substr($filePath, strlen($dirPath))), '/');
                $label = $dirName . ($relativePath !== '' ? '/' . $relativePath : '');

                if ($item->isDir()) {
                    if ($fileTime < $cutoffTime && self::isDirectoryEmpty($filePath)) {
                        if (@rmdir($filePath)) {
                            $result['files_deleted']++;
                            self::log("Deleted old directory: $label (0 B)");
                        } else {
                            $result['errors'][] = "Failed to delete: $filePath";
                            self::log("Failed to delete: $label", 'ERROR');
                        }
                    }

                    continue;
                }

                if ($fileTime < $cutoffTime) {
                    $size = @filesize($filePath);

                    if (@unlink($filePath)) {
                        $result['files_deleted']++;
                        $result['size_freed'] += $size ?: 0;
                        self::log("Deleted old file: $label (" . self::formatBytes($size ?: 0) . ")");
                    } else {
                        $result['errors'][] = "Failed to delete: $filePath";
                        self::log("Failed to delete: $label", 'ERROR');
                    }
                }
            }
        } catch (Exception $e) {
            self::log("Exception in cleanupDirectory: " . $e->getMessage(), 'ERROR');
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check whether a path should never be removed by storage cleanup.
     */
    private static function isProtectedPath(string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $protectedPaths = [
            str_replace('\\', '/', self::$logFile),
            str_replace('\\', '/', self::$lastRunFile),
            str_replace('\\', '/', self::$lockFile),
        ];

        return in_array($normalizedPath, $protectedPaths, true);
    }

    /**
     * Check if a directory is empty.
     */
    private static function isDirectoryEmpty(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = @scandir($dir);
        if ($files === false) {
            return false;
        }

        return count($files) === 2;
    }

    /**
     * Get total size of directory
     */
    private static function getDirectorySize($dir)
    {
        $size = 0;

        if (!is_dir($dir)) {
            return $size;
        }

        $files = @scandir($dir);

        if ($files === false) {
            return $size;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $size += self::getDirectorySize($path);
            } else {
                $fileSize = @filesize($path);
                $size += $fileSize ?: 0;
            }
        }

        return $size;
    }

    /**
     * Format bytes to human-readable format
     */
    private static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get cleanup statistics
     */
    public static function getStats()
    {
        if (!self::$basePath) {
            self::init();
        }

        $stats = [];
        $storageDir = self::$basePath . '/storage';

        foreach (self::$retentionPolicies as $dir => $retentionDays) {
            $fullPath = $storageDir . '/' . $dir;

            if (is_dir($fullPath)) {
                $stats[$dir] = [
                    'size' => self::getDirectorySize($fullPath),
                    'retention_days' => $retentionDays,
                    'path' => $fullPath,
                ];
            }
        }

        return $stats;
    }
}

// Initialize on class load
StorageCleanupHelper::init();
