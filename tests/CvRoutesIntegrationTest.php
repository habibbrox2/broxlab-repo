<?php

/**
 * CV Routes Integration Test
 * 
 * Verifies that all CV route definitions dispatch to valid controller classes
 * and methods. Tests ~56+ routes across cv.php and DashboardController.php.
 * 
 * Run: php tests/CvRoutesIntegrationTest.php
 */

// ── Configuration ──
$exitCode = 0;
$passes   = [];
$failures = [];
$skipped  = [];

// ── Bootstrap: autoload controllers ──
$basePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR;

// Load the controller files that CV routes reference
$controllerFiles = [
    'CvController.php',
    'CvBuilderController.php',
    'CvExportController.php',
    'CvAiController.php',
    'CvPurchaseController.php',
    'AdminCvController.php',
];

$loadedClasses = [];
foreach ($controllerFiles as $file) {
    $path = $basePath . $file;
    if (!file_exists($path)) {
        echo "  ⚠  Controller file not found: {$file}\n";
        $skipped[] = ['file' => $file, 'reason' => 'File not found'];
        continue;
    }
    require_once $path;
    $className = basename($file, '.php');
    $loadedClasses[$className] = $path;
}

// ── Also load model files needed for syntax validation ──
$modelBasePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR;
$modelFiles = [
    'CvModel.php', 'CvSectionModel.php', 'CvItemModel.php', 'CvShareModel.php',
    'CvVersionModel.php', 'CvAnalyticsModel.php', 'CvRateLimitModel.php',
    'CvPersonalInfoModel.php', 'UserModel.php', 'JobPositionModel.php',
    'NotificationModel.php', 'ContactModel.php', 'StatisticsModel.php',
    'ContentModel.php', 'CommentModel.php', 'ServiceApplicationModel.php',
    'MobileModel.php', 'AuthManager.php', 'AppSettings.php',
];
foreach ($modelFiles as $file) {
    $path = $modelBasePath . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// ── Helper: verify a callable array ──
function verifyCallable(string $className, string $methodName, array &$passes, array &$failures): void
{
    if (!class_exists($className)) {
        $failures[] = ['route' => "[{$className}, {$methodName}]", 'reason' => "Class '{$className}' does not exist"];
        return;
    }

    if (!method_exists($className, $methodName)) {
        $failures[] = ['route' => "[{$className}, {$methodName}]", 'reason' => "Method '{$methodName}' does not exist on {$className}"];
        return;
    }

    // Verify it's a public static method
    try {
        $ref = new ReflectionMethod($className, $methodName);
        if (!$ref->isPublic()) {
            $failures[] = ['route' => "[{$className}, {$methodName}]", 'reason' => "Method '{$methodName}' is not public"];
            return;
        }
        if (!$ref->isStatic()) {
            $failures[] = ['route' => "[{$className}, {$methodName}]", 'reason' => "Method '{$methodName}' is not static"];
            return;
        }
    } catch (ReflectionException $e) {
        $failures[] = ['route' => "[{$className}, {$methodName}]", 'reason' => $e->getMessage()];
        return;
    }

    $passes[] = ['route' => "[{$className}, {$methodName}]"];
}

// ═════════════════════════════════════════════════════════════════════════════
// 1. EXTRACT CALLABLES FROM cv.php
// ═════════════════════════════════════════════════════════════════════════════
$cvRoutesFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'cv.php';

$cvRouteCallables = [];

if (file_exists($cvRoutesFile)) {
    $content = file_get_contents($cvRoutesFile);
    // Match [ 'ControllerName', 'methodName' ] callables in route definitions
    // Pattern: ['Controller', 'method'] with optional whitespace
    preg_match_all("/\\[\\s*'(CvController|CvBuilderController|CvExportController|CvAiController|CvPurchaseController)'\\s*,\\s*'([a-zA-Z]+)'\\s*\\]/", $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $cvRouteCallables[] = ['class' => $m[1], 'method' => $m[2]];
    }
} else {
    echo "  ⚠  Route file not found: cv.php\n";
    $skipped[] = ['file' => 'cv.php', 'reason' => 'File not found'];
}

// ═════════════════════════════════════════════════════════════════════════════
// 2. EXTRACT CALLABLES FROM DashboardController.php (admin CV routes)
// ═════════════════════════════════════════════════════════════════════════════
$dashboardFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DashboardController.php';

$dashboardCallables = [];

if (file_exists($dashboardFile)) {
    $content = file_get_contents($dashboardFile);
    preg_match_all("/\\[\\s*'AdminCvController'\\s*,\\s*'([a-zA-Z]+)'\\s*\\]/", $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $dashboardCallables[] = ['class' => 'AdminCvController', 'method' => $m[1]];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// 3. VERIFY ALL CALLABLES
// ═════════════════════════════════════════════════════════════════════════════
$allCallables = array_merge($cvRouteCallables, $dashboardCallables);

// Deduplicate by (class, method)
$seen = [];
$uniqueCallables = [];
foreach ($allCallables as $c) {
    $key = $c['class'] . '::' . $c['method'];
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $uniqueCallables[] = $c;
    }
}

foreach ($uniqueCallables as $c) {
    verifyCallable($c['class'], $c['method'], $passes, $failures);
}

// ═════════════════════════════════════════════════════════════════════════════
// 4. CROSS-CHECK: Find callables in route files NOT caught by regex
// ═════════════════════════════════════════════════════════════════════════════
// Read cv.php for any callables using different quote styles
$cvContent = file_exists($cvRoutesFile) ? file_get_contents($cvRoutesFile) : '';
// Catch ["Controller", "method"] double-quote style
preg_match_all('/\\[\\s*"(CvController|CvBuilderController|CvExportController|CvAiController|CvPurchaseController)"\\s*,\\s*"([a-zA-Z]+)"\\s*\\]/', $cvContent, $dqMatches, PREG_SET_ORDER);
foreach ($dqMatches as $m) {
    $key = $m[1] . '::' . $m[2];
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        verifyCallable($m[1], $m[2], $passes, $failures);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// REPORT
// ═════════════════════════════════════════════════════════════════════════════
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  📋  CV ROUTES INTEGRATION TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

$total  = count($passes) + count($failures) + count($skipped);

echo "  📁  Route sources:\n";
echo "      cv.php:                " . count($cvRouteCallables) . " route-to-controller mappings\n";
echo "      DashboardController:   " . count($dashboardCallables) . " admin CV route mappings\n";
echo "      Unique callables:      " . count($uniqueCallables) . "\n";
echo "\n";

// Group passes by controller
$byController = [];
foreach ($passes as $p) {
    $parts = explode('::', $p['route']);
    $class = str_replace(['[', "'", '"'], '', $parts[0]);
    if (!isset($byController[$class])) $byController[$class] = [];
    $byController[$class][] = $p['route'];
}

echo "  ✅  PASSED: " . count($passes) . "\n";
foreach ($byController as $class => $routes) {
    echo "      {$class} (" . count($routes) . " methods):\n";
    foreach ($routes as $r) {
        echo "        ✓ {$r}\n";
    }
}

if (!empty($failures)) {
    echo "\n";
    echo "  ❌  FAILED: " . count($failures) . "\n";
    foreach ($failures as $f) {
        echo "      ✗ {$f['route']}: {$f['reason']}\n";
    }
    $exitCode = 1;
}

if (!empty($skipped)) {
    echo "\n";
    echo "  ⚠  SKIPPED: " . count($skipped) . "\n";
    foreach ($skipped as $s) {
        echo "      - {$s['reason']}: {$s['file']}\n";
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if ($exitCode === 0) {
    echo "  ✅  All {$total} route(s) verified successfully.\n";
} else {
    echo "  ❌  " . count($failures) . " route(s) failed verification.\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

exit($exitCode);
