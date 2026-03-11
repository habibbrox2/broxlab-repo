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
