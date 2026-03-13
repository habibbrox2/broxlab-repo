<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Db.php';
require_once __DIR__ . '/../Config/Constants.php';
require_once __DIR__ . '/../app/Helpers/ErrorLogging.php';
require_once __DIR__ . '/../app/Models/DeployJobModel.php';

initializeErrorLogging();

$model = new DeployJobModel($mysqli);
$model->ensureTablesExist();

$job = $model->getOldestQueued();

if (!$job) {
    echo "No queued jobs.\n";
    exit(0);
}

$id = (int)$job['id'];
if (!$model->markRunning($id)) {
    echo "Failed to lock job.\n";
    exit(1);
}

$logDir = rtrim(LOG_DIR, '/\\') . DIRECTORY_SEPARATOR . 'deploy_jobs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logPath = $logDir . DIRECTORY_SEPARATOR . $id . '.log';

$repoRoot = realpath(__DIR__ . '/..');
$jobType = (string)($job['job_type'] ?? '');
$meta = [];
if (!empty($job['meta_json'])) {
    $meta = json_decode((string)$job['meta_json'], true) ?: [];
}

if ($jobType === 'deploy') {
    $script = $repoRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'deploy.ps1';
    $args = ['powershell', '-ExecutionPolicy', 'Bypass', '-File', $script];
    if (!empty($meta['with_vendor'])) {
        $args[] = '--with-vendor';
    }
} elseif ($jobType === 'db_backup') {
    $script = $repoRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'db_backup.ps1';
    $args = ['powershell', '-ExecutionPolicy', 'Bypass', '-File', $script];
} else {
    $model->markFailed($id, 'Unknown job type', $logPath);
    exit(1);
}

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['file', $logPath, 'a'],
    2 => ['file', $logPath, 'a'],
];

try {
    $process = proc_open($args, $descriptorSpec, $pipes, $repoRoot);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start job process.');
    }

    $exitCode = proc_close($process);
    if ($exitCode === 0) {
        $model->markSuccess($id, $logPath);
        exit(0);
    }

    $model->markFailed($id, 'Job failed with exit code ' . $exitCode, $logPath);
    exit(1);
} catch (Throwable $e) {
    $model->markFailed($id, $e->getMessage(), $logPath);
    exit(1);
}
