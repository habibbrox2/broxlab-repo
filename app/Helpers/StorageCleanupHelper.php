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
    public static function init($basePath = null)
    {
        self::$basePath = $basePath ?? dirname(__DIR__, 2);
        self::$logFile = self::$basePath . '/storage/logs/cleanup.log';

        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
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
        echo $logMessage;
    }

    /**
     * Set custom retention policy for a directory
     */
    public static function setRetentionPolicy($directory, $days)
    {
        self::$retentionPolicies[$directory] = (int)$days;
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
            $files = @scandir($dirPath);

            if ($files === false) {
                self::log("Failed to read directory: $dirPath", 'ERROR');
                $result['errors'][] = "Failed to read directory";
                return $result;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $dirPath . '/' . $file;

                if (!file_exists($filePath)) {
                    continue;
                }

                $fileTime = @filemtime($filePath);

                if ($fileTime === false) {
                    continue;
                }

                // Delete if file is older than retention period
                if ($fileTime < $cutoffTime) {
                    if (is_dir($filePath)) {
                        // Recursively delete directory
                        $size = self::getDirectorySize($filePath);

                        if (self::deleteDirectory($filePath)) {
                            $result['files_deleted']++;
                            $result['size_freed'] += $size;
                            self::log("Deleted old directory: $dirName/$file (" . self::formatBytes($size) . ")");
                        } else {
                            $result['errors'][] = "Failed to delete: $filePath";
                            self::log("Failed to delete: $dirName/$file", 'ERROR');
                        }
                    } else {
                        // Delete file
                        $size = @filesize($filePath);

                        if (@unlink($filePath)) {
                            $result['files_deleted']++;
                            $result['size_freed'] += $size ?: 0;
                            self::log("Deleted old file: $dirName/$file (" . self::formatBytes($size ?: 0) . ")");
                        } else {
                            $result['errors'][] = "Failed to delete: $filePath";
                            self::log("Failed to delete: $dirName/$file", 'ERROR');
                        }
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
     * Delete directory recursively
     */
    private static function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = @scandir($dir);

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                if (!self::deleteDirectory($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }

        return @rmdir($dir);
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
