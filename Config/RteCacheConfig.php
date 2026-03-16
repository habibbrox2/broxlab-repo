<?php
/**
 * Rich Text Editor & Firebase Cache Configuration
 * Provides cache-busting strategies for RTE and Firebase JS files
 */

/**
 * Generate cache buster parameter for RTE files
 * Uses file modification time for proper cache invalidation
 * 
 * @param string $filename - Filename relative to /public_html/rtceditor/ (e.g., 'editor.js', 'editor.css')
 * @return string - URL parameter (e.g., '?v=1706921416')
 */
function getRTECacheBuster($filename) {
    $filepath = __DIR__ . '/../public_html/rtceditor/' . $filename;
    
    if (!file_exists($filepath)) {
        // Fallback to current timestamp if file not found
        return '?v=' . time();
    }
    
    // Use file modification time as cache buster
    $mtime = filemtime($filepath);
    return '?v=' . $mtime;
}

/**
 * Generate cache buster parameter for Firebase JS files
 * Uses file modification time for proper cache invalidation
 * 
 * @param string $filename - Filename relative to /public_html/ (e.g., 'firebase-init.js', 'assets/js/notifications.js')
 * @return string - URL parameter (e.g., '?v=1706921416')
 */
function getFirebaseCacheBuster($filename) {
    $filepath = __DIR__ . '/../public_html/' . $filename;
    
    if (!file_exists($filepath)) {
        // Fallback to current timestamp if file not found
        return '?v=' . time();
    }
    
    // Use file modification time as cache buster
    $mtime = filemtime($filepath);
    return '?v=' . $mtime;
}

/**
 * Generate full URL with cache buster for RTE files
 * 
 * @param string $filename - Filename (e.g., 'editor.js')
 * @param string $basePath - Base path (default: '/rtceditor/')
 * @return string - Full URL with cache parameter
 */
function getRTEFileUrl($filename, $basePath = '/rtceditor/') {
    return $basePath . $filename . getRTECacheBuster($filename);
}

/**
 * Generate full URL with cache buster for Firebase JS files
 * 
 * @param string $filename - Filename (e.g., 'firebase-init.js', 'assets/js/notifications.js')
 * @param string $basePath - Base path (default: '/')
 * @return string - Full URL with cache parameter
 */
function getFirebaseFileUrl($filename, $basePath = '/') {
    return $basePath . $filename . getFirebaseCacheBuster($filename);
}

/**
 * Get all RTE core files to preload
 * 
 * @return array - Array of ['filename' => url]
 */
function getRTECoreFiles() {
    return [
        'editor.css' => getRTEFileUrl('editor.css'),
        'editor.js' => getRTEFileUrl('editor.js'),
        'editor.debug.js' => getRTEFileUrl('editor.debug.js'),
    ];
}

/**
 * Get all Firebase core JS files to preload
 * 
 * @return array - Array of ['filename' => url]
 */
function getFirebaseCoreFiles() {
    return [
        'firebase-init.js' => getFirebaseFileUrl('firebase-init.js'),
        'notification-system.js' => getFirebaseFileUrl('firebase/notification-system.js'),
        'analytics-wrapper.js' => getFirebaseFileUrl('firebase/analytics-wrapper.js'),
    ];
}

/**
 * Get version string for RTE cache busting
 * Based on latest modification time of any RTE file
 * 
 * @return string - Version string (timestamp)
 */
function getRTEVersion() {
    $rtePath = __DIR__ . '/../public_html/rtceditor/';
    $latestMtime = 0;
    
    if (is_dir($rtePath)) {
        foreach (glob($rtePath . '*.js') as $file) {
            $mtime = filemtime($file);
            if ($mtime > $latestMtime) {
                $latestMtime = $mtime;
            }
        }
    }
    
    return $latestMtime ?: time();
}

/**
 * Get version string for Firebase JS cache busting
 * Based on latest modification time of Firebase JS files
 * 
 * @return string - Version string (timestamp)
 */
function getFirebaseVersion() {
    $publicPath = __DIR__ . '/../public_html/';
    $firebaseFiles = [
        'firebase-init.js',
        'firebase/notification-system.js',
        'firebase/analytics-wrapper.js',
    ];
    
    $latestMtime = 0;
    foreach ($firebaseFiles as $file) {
        $filepath = $publicPath . $file;
        if (file_exists($filepath)) {
            $mtime = filemtime($filepath);
            if ($mtime > $latestMtime) {
                $latestMtime = $mtime;
            }
        }
    }
    
    return $latestMtime ?: time();
}
