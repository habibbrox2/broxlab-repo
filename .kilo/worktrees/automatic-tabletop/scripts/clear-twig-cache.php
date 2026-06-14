<?php

/**
 * Manual Twig Cache Clearing Script
 * Usage: php scripts/clear-twig-cache.php
 */

$cacheDir = dirname(__DIR__, 1) . '/storage/cache/twig/';

function deleteDir($dir)
{
    if (!is_dir($dir)) {
        return true;
    }
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

try {
    if (is_dir($cacheDir) && deleteDir($cacheDir)) {
        echo "✓ Successfully cleared Twig cache\n";
        exit(0);
    } else {
        echo "✓ Cache already clean or doesn't exist\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
