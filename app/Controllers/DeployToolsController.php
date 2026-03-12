<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/DeployJobModel.php';

function deploy_tools_is_dev_env(): bool
{
    return function_exists('brox_is_development_env') && brox_is_development_env();
}

// GET /admin/deploy-tools
$router->get('/admin/deploy-tools', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    if (!deploy_tools_is_dev_env()) {
        http_response_code(404);
        echo $twig->render('error.twig', [
            'code' => 404,
            'title' => 'Not Found',
            'message' => 'Page not available in this environment.'
        ]);
        return;
    }

    $model = new DeployJobModel($mysqli);
    $model->ensureTablesExist();
    $jobs = $model->getQueue(50);

    echo $twig->render('admin/settings/deploy_tools.twig', [
        'title' => 'Deploy Tools',
        'jobs' => $jobs,
        'current_page' => 'deploy-tools',
        'csrf_token' => generateCsrfToken(),
    ]);
});

// POST /admin/deploy-tools/queue
$router->post('/admin/deploy-tools/queue', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () use ($mysqli) {
    if (!deploy_tools_is_dev_env()) {
        http_response_code(404);
        echo "Not available";
        return;
    }

    $jobType = trim((string)($_POST['job_type'] ?? ''));
    if (!in_array($jobType, ['deploy', 'db_backup'], true)) {
        showMessage('Invalid job type', 'danger');
        header('Location: /admin/deploy-tools');
        return;
    }

    $meta = [];
    if ($jobType === 'deploy') {
        $meta['with_vendor'] = !empty($_POST['with_vendor']);
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $model = new DeployJobModel($mysqli);
    $model->ensureTablesExist();
    $model->enqueueJob($jobType, $userId, $meta);

    showMessage('Job queued successfully', 'success');
    header('Location: /admin/deploy-tools');
});

// POST /admin/deploy-tools/run (Run deploy immediately)
$router->post('/admin/deploy-tools/run', ['middleware' => ['auth', 'super_admin_only', 'csrf']], function () {
    if (!deploy_tools_is_dev_env()) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not available']);
        return;
    }

    header('Content-Type: application/json');

    $jobType = trim((string)($_POST['job_type'] ?? ''));
    if (!in_array($jobType, ['deploy', 'db_backup'], true)) {
        echo json_encode(['error' => 'Invalid job type']);
        return;
    }

    // Start deploy in background and return progress ID
    $progressId = uniqid('deploy_');
    $progressFile = sys_get_temp_dir() . '/' . $progressId . '.json';

    file_put_contents($progressFile, json_encode([
        'id' => $progressId,
        'status' => 'running',
        'started_at' => date('Y-m-d H:i:s'),
        'steps' => [],
        'current_step' => 'Initializing...',
        'progress' => 0
    ]));

    // Fork to background process
    $cmd = '';
    if ($jobType === 'deploy') {
        $withVendor = !empty($_POST['with_vendor']) ? ' --with-vendor' : '';
        $cmd = 'powershell -ExecutionPolicy Bypass -File "' . dirname(__DIR__, 2) . '/scripts/deploy.ps1"' . $withVendor;
    } else {
        $cmd = 'powershell -ExecutionPolicy Bypass -File "' . dirname(__DIR__, 2) . '/scripts/db_backup.ps1"';
    }

    // Windows: start /b doesn't work well, use Start-Process
    $psScript = @"
\$progressId = '{$progressId}'
\$progressFile = '{$progressFile}'

function Update-Progress(\$step, \$message, \$progress) {
    \$data = json_decode((Get-Content \$progressFile -Raw), true)
    \$data['steps'] += @([
        @{
            'step' = \$step
            'message' = \$message
            'timestamp' = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
            'progress' = \$progress
        }
    ])
    \$data['current_step'] = \$message
    \$data['progress'] = \$progress
    Set-Content -Path \$progressFile -Value (\$data | ConvertTo-Json -Depth 10)
}

Update-Progress 'init' 'Starting deploy...' 5
{$cmd}
Update-Progress 'done' 'Deploy completed!' 100
";

    $tempScript = sys_get_temp_dir() . '/deploy_progress_' . $progressId . '.ps1';
    file_put_contents($tempScript, $psScript);

    // Start the script in background
    pclose(popen('start /b powershell -ExecutionPolicy Bypass -File "' . $tempScript . '"', 'r'));

    echo json_encode([
        'progress_id' => $progressId,
        'status' => 'started'
    ]);
});

// GET /admin/deploy-tools/progress (AJAX - get deploy progress)
$router->get('/admin/deploy-tools/progress', ['middleware' => ['auth', 'super_admin_only']], function () {
    if (!deploy_tools_is_dev_env()) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not available']);
        return;
    }

    header('Content-Type: application/json');

    $progressId = $_GET['id'] ?? '';
    if (empty($progressId)) {
        echo json_encode(['error' => 'No progress ID']);
        return;
    }

    $progressFile = sys_get_temp_dir() . '/' . $progressId . '.json';
    if (!file_exists($progressFile)) {
        echo json_encode(['error' => 'Progress not found', 'status' => 'unknown']);
        return;
    }

    $data = json_decode(file_get_contents($progressFile), true);
    echo json_encode($data);
});

// GET /admin/deploy-tools/log
$router->get('/admin/deploy-tools/log', ['middleware' => ['auth', 'super_admin_only']], function () use ($twig, $mysqli) {
    if (!deploy_tools_is_dev_env()) {
        http_response_code(404);
        echo $twig->render('error.twig', [
            'code' => 404,
            'title' => 'Not Found',
            'message' => 'Page not available in this environment.'
        ]);
        return;
    }

    $id = (int)($_GET['id'] ?? 0);
    $model = new DeployJobModel($mysqli);
    $model->ensureTablesExist();
    $job = $model->getJobById($id);
    if (!$job) {
        showMessage('Job not found', 'danger');
        header('Location: /admin/deploy-tools');
        return;
    }

    $logContent = '';
    $logPath = $job['log_path'] ?? '';
    if ($logPath !== '' && is_file($logPath)) {
        $base = rtrim(str_replace('\\', '/', LOG_DIR), '/') . '/deploy_jobs/';
        $normalized = str_replace('\\', '/', $logPath);
        if (strpos($normalized, $base) === 0) {
            $logContent = file_get_contents($logPath) ?: '';
        }
    }

    echo $twig->render('admin/settings/deploy_tools.twig', [
        'title' => 'Deploy Tools',
        'jobs' => $model->getQueue(50),
        'current_page' => 'deploy-tools',
        'csrf_token' => generateCsrfToken(),
        'log_job' => $job,
        'log_content' => $logContent,
    ]);
});

// GET /admin/deploy-tools/api/file-tree (AJAX)
$router->get('/admin/deploy-tools/api/file-tree', ['middleware' => ['auth', 'super_admin_only']], function () {
    if (!deploy_tools_is_dev_env()) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not available']);
        return;
    }

    header('Content-Type: application/json');

    $rootPath = defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__, 2);
    $currentPath = $_GET['path'] ?? '';
    $refresh = isset($_GET['refresh']);

    // Security: Only allow paths within BASE_DIR
    $safePath = $rootPath;
    if ($currentPath !== '') {
        $requestedPath = realpath($rootPath . '/' . $currentPath);
        if ($requestedPath && strpos($requestedPath, realpath($rootPath)) === 0) {
            $safePath = $requestedPath;
        }
    }

    $result = buildFileTree($safePath, $rootPath);
    echo json_encode($result);
});

/**
 * Build file tree structure for JSON response
 */
function buildFileTree(string $path, string $rootPath): array
{
    $items = [];
    $baseName = basename($path);

    // Skip hidden files and certain directories
    $skipDirs = ['.git', 'node_modules', 'vendor', '.env', 'storage/logs', 'storage/cache'];
    $relativePath = str_replace('\\', '/', str_replace($rootPath, '', $path));

    foreach ($skipDirs as $skip) {
        if (strpos($relativePath . '/', '/' . $skip . '/') === 0 || $relativePath === '/' . $skip) {
            return ['name' => $baseName, 'type' => 'folder', 'children' => []];
        }
    }

    if (!is_dir($path)) {
        return [
            'name' => $baseName,
            'type' => 'file',
            'size' => filesize($path),
            'modified' => filemtime($path),
            'path' => str_replace($rootPath, '', str_replace('\\', '/', $path)),
        ];
    }

    $entries = scandir($path);
    $children = [];

    foreach ($entries as $entry) {
        if ($entry[0] === '.') continue;

        $entryPath = $path . '/' . $entry;
        $childRelative = str_replace($rootPath, '', str_replace('\\', '/', $entryPath));

        // Skip certain directories
        $skip = false;
        foreach ($skipDirs as $skipDir) {
            if (strpos($childRelative . '/', '/' . $skipDir . '/') === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        if (is_dir($entryPath)) {
            $children[] = [
                'name' => $entry,
                'type' => 'folder',
                'path' => $childRelative,
                'children' => [], // Lazy load
            ];
        } else {
            $children[] = [
                'name' => $entry,
                'type' => 'file',
                'size' => filesize($entryPath),
                'modified' => filemtime($entryPath),
                'path' => $childRelative,
            ];
        }
    }

    // Sort: folders first, then files, alphabetically
    usort($children, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'folder' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    return [
        'name' => $baseName ?: '/',
        'type' => 'folder',
        'path' => str_replace($rootPath, '', str_replace('\\', '/', $path)),
        'children' => $children,
    ];
}
