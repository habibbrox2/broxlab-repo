<?php
declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/vendor/autoload.php';
require_once dirname(__DIR__, 1) . '/Config/Constants.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/ErrorLogging.php';
require_once dirname(__DIR__, 1) . '/app/Helpers/LanguageHelper.php';
require_once dirname(__DIR__, 1) . '/app/Models/UserModel.php';
require_once dirname(__DIR__, 1) . '/app/Models/AppSettings.php';
require_once __DIR__ . '/Functions.php';
require_once __DIR__ . '/TwigHelper.php';
require_once __DIR__ . '/RteCacheConfig.php';

// ============================================================
// ENVIRONMENT HELPERS
// ============================================================
// Moved to Config/TwigHelper.php

// ============================================================
// FLASH MESSAGE HANDLER
// ============================================================

// getFlash is defined in Config/TwigHelper.php

// ============================================================
// USER LOADER
// ============================================================

// loadUser is defined in Config/TwigHelper.php

// ============================================================
// TWIG INITIALIZATION
// ============================================================

function initializeTwig(mysqli $mysqli, ?array &$session, string $configUrl): \Twig\Environment
{
    global $settingsModel;

    try {
        if (!is_array($session)) {
            $session = [];
        }

        if (function_exists('secureSession')) {
            secureSession();
        } elseif (!headers_sent()) {
            session_start();
        }

        $loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__, 1) . '/app/Views');

        // Fetch app settings (cached inside AppSettings model)
        $appSettings = $settingsModel->getSettings();

        // Twig cache control from settings
        $twigCache = false;
        if (!empty($appSettings['enable_cache']) && $appSettings['enable_cache'] != '0') {
            $cacheDir = CACHE_DIR . 'twig' . DIRECTORY_SEPARATOR;

            // Ensure cache directory exists with proper permissions
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }

            // Verify directory is writable
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                $twigCache = $cacheDir;
                logDebug("Twig cache enabled - cache_dir: {$cacheDir}");
            } else {
                $twigCache = false;
                logDebug("WARNING: Twig cache directory is not writable: {$cacheDir}");
            }
        }

        // Twig debug control from MAINTENANCE_MODE
        // MAINTENANCE_MODE=0 ΓåÆ debug ON
        // MAINTENANCE_MODE=1 ΓåÆ debug OFF
        $maintenanceMode = (int) env('MAINTENANCE_MODE', 0);
        $twigDebug = ($maintenanceMode === 0);

        $twig = new \Twig\Environment($loader, [
            'cache' => $twigCache,
            'debug' => $twigDebug,
            'auto_reload' => true,
            'autoescape' => 'html',
            'strict_variables' => false,
            'cache_busting_key' => 'v20260428-filters', // Increment to force cache invalidation
        ]);

        if ($twigDebug) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
            logDebug("Twig debug mode enabled");
        }

        $twig->addExtension(new \Twig\Extension\StringLoaderExtension());

        registerTwigHelpers($twig, $mysqli, $session, $appSettings, $configUrl);

        return $twig;
    } catch (Throwable $e) {
        error_log("CRITICAL: Twig initialization error - message: {$e->getMessage()}, " .
            "file: {$e->getFile()}, line: {$e->getLine()}");
        throw $e;
    }
}

// ============================================================
// INITIALIZE APP SETTINGS MODEL
// ============================================================

$settingsModel = new AppSettings($mysqli);

// ============================================================
// INITIALIZE TWIG
// ============================================================

global $twig;

try {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }

    $twig = initializeTwig($mysqli, $_SESSION, getCurrentUrl());
    logDebug('Twig engine initialized successfully', ['timestamp' => date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    logError('Failed to initialize Twig engine: ' . $e->getMessage(), 'CRITICAL', [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    renderError(500, 'Template engine initialization failed');
    exit;
}
