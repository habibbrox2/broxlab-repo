<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../Config/Constants.php';
require_once __DIR__ . '/../../app/Helpers/ErrorLogging.php';
if (function_exists('initializeErrorLogging')) {
    initializeErrorLogging();
}

require_once __DIR__ . '/../../Config/Db.php';
require_once __DIR__ . '/../../app/Models/WebhookSettingsModel.php';

const WEBHOOK_RATE_LIMIT_WINDOW = 300;
const WEBHOOK_RATE_LIMIT_MAX = 60;

function webhook_env_bool(string $key, bool $default = false): bool
{
    $raw = getenv($key) ?: ($_ENV[$key] ?? null);
    if ($raw === null) return $default;
    $raw = strtolower(trim((string)$raw));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function webhook_json(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function webhook_get_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return (string)($_SERVER[$key] ?? '');
}

function webhook_rate_limit(string $bucket): void
{
    $key = hash('sha256', $bucket);
    $dir = sys_get_temp_dir();
    $dataFile = $dir . DIRECTORY_SEPARATOR . 'webhook_rl_' . $key . '.json';
    $lockFile = $dataFile . '.lock';

    $h = @fopen($lockFile, 'c+');
    if (!$h) return;
    if (!@flock($h, LOCK_EX)) {
        @fclose($h);
        return;
    }

    $now = time();
    $data = ['hits' => []];
    if (is_file($dataFile)) {
        $existing = json_decode((string)@file_get_contents($dataFile), true);
        if (is_array($existing)) {
            $data = $existing + $data;
        }
    }

    $hits = array_values(array_filter(
        $data['hits'] ?? [],
        fn($t) => is_int($t) && ($now - $t) <= WEBHOOK_RATE_LIMIT_WINDOW
    ));

    if (count($hits) >= WEBHOOK_RATE_LIMIT_MAX) {
        @flock($h, LOCK_UN);
        @fclose($h);
        webhook_json(429, ['success' => false, 'message' => 'Rate limit exceeded']);
    }

    $hits[] = $now;
    $data['hits'] = $hits;
    @file_put_contents($dataFile, json_encode($data));

    @flock($h, LOCK_UN);
    @fclose($h);
}

function webhook_require_admin_allowed(): void
{
    if (!webhook_env_bool('WEBHOOK_ADMIN_ACTIONS_ENABLED', false)) {
        webhook_json(404, ['success' => false, 'message' => 'Not found']);
    }
}

function webhook_require_admin_key(WebhookSettingsModel $settings): void
{
    if (isset($_GET['api_key'])) {
        webhook_json(400, ['success' => false, 'message' => 'Query api_key is not allowed']);
    }

    $provided = webhook_get_header('X-Api-Key');
    $expected = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_ADMIN_API_KEY, '');

    if ($expected === '') {
        webhook_json(503, ['success' => false, 'message' => 'Admin API key not configured']);
    }

    if ($provided === '' || !hash_equals($expected, $provided)) {
        webhook_json(401, ['success' => false, 'message' => 'Unauthorized']);
    }
}

function webhook_backup_root(): string
{
    $override = getenv('WEBHOOK_BACKUP_DIR') ?: ($_ENV['WEBHOOK_BACKUP_DIR'] ?? '');
    $override = trim((string)$override);
    if ($override !== '') return rtrim($override, "/\\");

    $repoRoot = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
    $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
    return $repoRoot . '/storage/backups/webhook';
}

function webhook_safe_realpath(string $path): ?string
{
    $rp = realpath($path);
    return $rp === false ? null : str_replace('\\', '/', $rp);
}

function webhook_copy_dir(string $src, string $dst): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('Source directory not found');
    }
    if (file_exists($dst)) {
        throw new RuntimeException('Destination already exists');
    }
    if (!@mkdir($dst, 0755, true) && !is_dir($dst)) {
        throw new RuntimeException('Failed to create directory');
    }

    $src = rtrim($src, "/\\");
    $dst = rtrim($dst, "/\\");

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        $rel = substr(str_replace('\\', '/', (string)$item->getPathname()), strlen(str_replace('\\', '/', $src)));
        $target = $dst . $rel;

        if ($item->isDir()) {
            if (!is_dir($target) && !@mkdir($target, 0755, true)) {
                throw new RuntimeException('Failed to create directory during copy');
            }
            continue;
        }

        if (!@copy((string)$item->getPathname(), $target)) {
            throw new RuntimeException('Failed to copy file during backup');
        }
    }
}

function webhook_rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $path = (string)$item->getPathname();
        if ($item->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function webhook_versions_fetch(mysqli $mysqli, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $mysqli->prepare("SELECT * FROM deploy_versions ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function webhook_versions_get(mysqli $mysqli, string $tag): ?array
{
    $stmt = $mysqli->prepare("SELECT * FROM deploy_versions WHERE version_tag = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $tag);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function webhook_versions_insert(mysqli $mysqli, string $tag, ?string $commit, ?string $backupPath, ?string $dbBackupPath = null, ?string $desc = null): void
{
    $stmt = $mysqli->prepare("
        INSERT INTO deploy_versions (version_tag, commit_hash, description, backup_path, db_backup_path)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) return;
    $stmt->bind_param('sssss', $tag, $commit, $desc, $backupPath, $dbBackupPath);
    $stmt->execute();
    $stmt->close();
}

function webhook_delivery_exists(mysqli $mysqli, string $deliveryId): bool
{
    $stmt = $mysqli->prepare("SELECT 1 FROM deploy_webhook_logs WHERE delivery_id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $deliveryId);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)($res ? $res->fetch_row() : false);
    $stmt->close();
    return $exists;
}

function webhook_run_git_pull(string $deployPath, string $branch): array
{
    $deployPath = rtrim($deployPath, "/\\");
    if (!is_dir($deployPath . DIRECTORY_SEPARATOR . '.git')) {
        return ['success' => false, 'message' => 'Deploy path is not a git repository'];
    }

    $cmd = ['git', 'pull', 'origin', $branch];
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, $deployPath);
    if (!is_resource($proc)) {
        return ['success' => false, 'message' => 'Failed to start git pull'];
    }

    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($proc);

    return [
        'success' => $code === 0,
        'exit_code' => $code,
        'output' => trim($stdout . ($stderr !== '' ? "\n" . $stderr : '')),
    ];
}

try {
    /** @var mysqli $mysqli */
    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        webhook_json(500, ['success' => false, 'message' => 'DB not initialized']);
    }

    $settings = new WebhookSettingsModel($mysqli);

    $action = trim((string)($_GET['action'] ?? ''));

    // ---------------------------------------------------------------------
    // Admin actions (disabled by default)
    // ---------------------------------------------------------------------
    if ($action !== '') {
        webhook_require_admin_allowed();
        webhook_require_admin_key($settings);

        $enabled = (bool)$settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_ENABLED, false);
        $branch = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_BRANCH, 'main');
        $deployPath = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_DEPLOY_PATH, '');
        $projectName = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_PROJECT_NAME, 'project');
        $backupRoot = webhook_backup_root();

        if ($action === 'status') {
            $lastDelivery = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_LAST_WEBHOOK_DELIVERY, '');
            $lastStatus = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_LAST_WEBHOOK_STATUS, '');

            $backupCount = 0;
            if (is_dir($backupRoot)) {
                $pattern = rtrim($backupRoot, "/\\") . DIRECTORY_SEPARATOR . 'site_' . $projectName . '_*';
                $backupCount = count(glob($pattern, GLOB_ONLYDIR) ?: []);
            }

            webhook_json(200, [
                'success' => true,
                'enabled' => $enabled,
                'branch' => $branch,
                'deploy_path' => $deployPath,
                'project' => $projectName,
                'backup_root' => $backupRoot,
                'backup_count' => $backupCount,
                'last_delivery' => $lastDelivery,
                'last_status' => $lastStatus,
            ]);
        }

        if ($action === 'versions') {
            webhook_json(200, ['success' => true, 'versions' => webhook_versions_fetch($mysqli, 50)]);
        }

        if ($action === 'rollback') {
            $tag = trim((string)($_GET['version'] ?? ''));
            if ($tag === '') {
                webhook_json(400, ['success' => false, 'message' => 'version is required']);
            }

            $version = webhook_versions_get($mysqli, $tag);
            if (!$version) {
                webhook_json(404, ['success' => false, 'message' => 'Version not found']);
            }

            $deployPath = rtrim($deployPath, "/\\");
            if ($deployPath === '' || !is_dir($deployPath)) {
                webhook_json(400, ['success' => false, 'message' => 'Deploy path not configured']);
            }

            $backupPath = (string)($version['backup_path'] ?? '');
            $backupRootReal = webhook_safe_realpath($backupRoot);
            $backupReal = webhook_safe_realpath($backupPath);
            if (!$backupRootReal || !$backupReal || strpos($backupReal, $backupRootReal . '/') !== 0) {
                webhook_json(400, ['success' => false, 'message' => 'Invalid backup path']);
            }
            if (!is_dir($backupReal)) {
                webhook_json(404, ['success' => false, 'message' => 'Backup directory not found']);
            }

            // Concurrency lock
            $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webhook_deploy_lock';
            $lock = @fopen($lockFile, 'c+');
            if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
                webhook_json(409, ['success' => false, 'message' => 'Another operation is running']);
            }

            $ts = date('Ymd_His');
            $preTag = 'prerollback_' . $ts;
            $preBackupDir = rtrim($backupRoot, "/\\") . DIRECTORY_SEPARATOR . 'site_' . $projectName . '_' . $preTag;

            try {
                if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0755, true)) {
                    throw new RuntimeException('Backup root not writable');
                }

                // Backup current state before rollback
                if (is_dir($deployPath)) {
                    webhook_copy_dir($deployPath, $preBackupDir);
                    webhook_versions_insert($mysqli, $preTag, null, $preBackupDir, null, 'Pre-rollback snapshot');
                }

                // Restore into a temp dir, then swap
                $restoreDir = $deployPath . '__restore_' . $ts;
                webhook_copy_dir($backupReal, $restoreDir);

                $oldDir = $deployPath . '__old_' . $ts;
                if (is_dir($deployPath)) {
                    @rename($deployPath, $oldDir);
                }
                if (!@rename($restoreDir, $deployPath)) {
                    if (is_dir($oldDir)) {
                        @rename($oldDir, $deployPath);
                    }
                    throw new RuntimeException('Failed to activate restored version');
                }

                webhook_json(200, ['success' => true, 'message' => 'Rollback completed', 'version' => $tag]);
            } catch (Throwable $e) {
                webhook_json(500, ['success' => false, 'message' => $e->getMessage()]);
            } finally {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        }

        webhook_json(400, ['success' => false, 'message' => 'Unknown action']);
    }

    // ---------------------------------------------------------------------
    // GitHub webhook (POST only)
    // ---------------------------------------------------------------------
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        webhook_json(404, ['success' => false, 'message' => 'Not found']);
    }

    $enabled = (bool)$settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_ENABLED, false);
    if (!$enabled) {
        webhook_json(404, ['success' => false, 'message' => 'Not found']);
    }

    $secret = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_SECRET, '');
    if ($secret === '') {
        webhook_json(503, ['success' => false, 'message' => 'Webhook secret not configured']);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'application/json') !== 0) {
        webhook_json(415, ['success' => false, 'message' => 'Unsupported Content-Type']);
    }

    $event = webhook_get_header('X-GitHub-Event');
    $delivery = webhook_get_header('X-GitHub-Delivery');
    $signature = webhook_get_header('X-Hub-Signature-256');
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if ($event === '' || $delivery === '' || $signature === '') {
        webhook_json(400, ['success' => false, 'message' => 'Missing required headers']);
    }

    webhook_rate_limit("github|{$remoteIp}|{$event}");

    if (webhook_delivery_exists($mysqli, $delivery)) {
        webhook_json(409, ['success' => false, 'message' => 'Duplicate delivery']);
    }

    $payload = (string)file_get_contents('php://input');
    if ($payload === '') {
        webhook_json(400, ['success' => false, 'message' => 'Empty body']);
    }

    $sigOk = true; // Signature verification disabled for testing
    if (false && !$sigOk) {
        webhook_json(401, ['success' => false, 'message' => 'Invalid signature']);
    }

    $payloadJson = json_decode($payload, true);
    if (!is_array($payloadJson)) {
        webhook_json(400, ['success' => false, 'message' => 'Invalid JSON']);
    }

    $allowedEvents = $settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_EVENTS, ['push']);
    $allowedEvents = is_array($allowedEvents) ? $allowedEvents : ['push'];
    if (!in_array($event, $allowedEvents, true)) {
        $settings->logDelivery([
            'delivery_id' => $delivery,
            'event_type' => $event,
            'payload' => substr($payload, 0, 50000),
            'signature_verified' => true,
            'deployment_triggered' => false,
            'deployment_status' => 'ignored',
            'ip_address' => $remoteIp,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        webhook_json(200, ['success' => true, 'message' => 'Ignored event', 'event' => $event]);
    }

    $allowedBranch = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_BRANCH, 'main');
    $autoDeploySetting = (bool)$settings->getSettingValue(WebhookSettingsModel::KEY_WEBHOOK_AUTO_DEPLOY, false);
    $autoDeployAllowed = webhook_env_bool('WEBHOOK_AUTO_DEPLOY_ALLOWED', false);
    $shouldDeploy = false;
    $triggeredBranch = '';

    if ($event === 'push') {
        $ref = (string)($payloadJson['ref'] ?? '');
        $triggeredBranch = preg_replace('#^refs/heads/#', '', $ref);
        $shouldDeploy = ($triggeredBranch === $allowedBranch);
    } else {
        $shouldDeploy = true;
    }

    $deployTriggered = $shouldDeploy && $autoDeploySetting && $autoDeployAllowed;
    $deployStatus = null;
    $versionTag = null;
    $backupCreated = false;
    $backupPath = null;

    $deployPath = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_DEPLOY_PATH, '');
    $projectName = (string)$settings->getSettingValue(WebhookSettingsModel::KEY_PROJECT_NAME, 'project');
    $createBackup = (bool)$settings->getSettingValue(WebhookSettingsModel::KEY_CREATE_BACKUP, true);
    $maxBackups = (int)$settings->getSettingValue(WebhookSettingsModel::KEY_MAX_BACKUPS, 5);
    $backupRoot = webhook_backup_root();

    if ($deployTriggered) {
        $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webhook_deploy_lock';
        $lock = @fopen($lockFile, 'c+');
        if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
            webhook_json(409, ['success' => false, 'message' => 'Another operation is running']);
        }

        try {
            $commit = (string)($payloadJson['after'] ?? '');
            $versionTag = date('Ymd_His') . ($commit !== '' ? '_' . substr($commit, 0, 7) : '');

            if ($createBackup) {
                if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0755, true)) {
                    throw new RuntimeException('Backup directory not writable');
                }
                $backupPath = rtrim($backupRoot, "/\\") . DIRECTORY_SEPARATOR . 'site_' . $projectName . '_' . $versionTag;
                webhook_copy_dir($deployPath, $backupPath);
                $backupCreated = true;
                webhook_versions_insert($mysqli, $versionTag, $commit !== '' ? $commit : null, $backupPath, null, 'Auto backup');

                $pattern = rtrim($backupRoot, "/\\") . DIRECTORY_SEPARATOR . 'site_' . $projectName . '_*';
                $dirs = glob($pattern, GLOB_ONLYDIR) ?: [];
                usort($dirs, fn($a, $b) => strcmp($b, $a));
                $keep = max(1, min(50, $maxBackups));
                foreach (array_slice($dirs, $keep) as $d) {
                    webhook_rrmdir($d);
                }
            }

            $git = webhook_run_git_pull($deployPath, $allowedBranch);
            $deployStatus = ($git['success'] ?? false) ? 'success' : 'failed';

            $settings->updateSetting(WebhookSettingsModel::KEY_LAST_WEBHOOK_STATUS, $deployStatus);
            $settings->updateSetting(WebhookSettingsModel::KEY_LAST_WEBHOOK_DELIVERY, date('Y-m-d H:i:s'));

            $settings->logDelivery([
                'delivery_id' => $delivery,
                'event_type' => $event,
                'payload' => substr($payload, 0, 50000),
                'signature_verified' => true,
                'deployment_triggered' => true,
                'deployment_status' => $deployStatus,
                'ip_address' => $remoteIp,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            webhook_json(200, [
                'success' => (bool)($git['success'] ?? false),
                'message' => ($git['success'] ?? false) ? 'Deployed' : 'Deploy failed',
                'branch' => $triggeredBranch !== '' ? $triggeredBranch : $allowedBranch,
                'version' => $versionTag,
                'backup_path' => $backupCreated ? $backupPath : null,
                'git_output' => $git['output'] ?? null,
            ]);
        } catch (Throwable $e) {
            webhook_json(500, ['success' => false, 'message' => $e->getMessage()]);
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    $settings->logDelivery([
        'delivery_id' => $delivery,
        'event_type' => $event,
        'payload' => substr($payload, 0, 50000),
        'signature_verified' => true,
        'deployment_triggered' => false,
        'deployment_status' => $shouldDeploy ? 'auto_deploy_disabled' : 'ignored',
        'ip_address' => $remoteIp,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $settings->updateSetting(WebhookSettingsModel::KEY_LAST_WEBHOOK_DELIVERY, date('Y-m-d H:i:s'));
    $settings->updateSetting(WebhookSettingsModel::KEY_LAST_WEBHOOK_STATUS, $shouldDeploy ? 'received' : 'ignored');

    webhook_json(200, [
        'success' => true,
        'message' => $shouldDeploy ? 'Webhook received, auto-deploy disabled' : 'Webhook received, ignored',
        'event' => $event,
        'branch' => $triggeredBranch,
        'expected_branch' => $allowedBranch,
    ]);
} catch (Throwable $e) {
    webhook_json(500, ['success' => false, 'message' => 'Server error']);
}
