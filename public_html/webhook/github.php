<?php

/**
 * GitHub Webhook Endpoint - Enhanced Version 4.0
 * Project: BROXBHAI | User: tdhuedhn
 *
 * Upload to: /home/tdhuedhn/public_html/webhook/github.php
 *
 * @version 4.0.0
 */

// ============================================================
// CONFIGURATION
// ============================================================

$db_config = [
    'host'     => 'localhost',
    'username' => 'your_db_username',  // cPanel → MySQL Databases থেকে নিন
    'password' => 'your_db_password',
    'database' => 'your_db_name'
];

// ============================================================
// WEBHOOK SETTINGS
// ============================================================

$webhook_enabled = true;
$target_branch   = 'main';
$auto_deploy     = true;
$webhook_secret  = 'your_webhook_secret_here';  // GitHub Webhook Secret এর সাথে মিলিয়ে দিন

// ============================================================
// DEPLOY & BACKUP PATHS
// ============================================================

// Deploy path — GitHub repo এখানে pull হবে
$deploy_path = '/home/tdhuedhn/BROXBHAI';

// Git repository URL
$git_repo = 'https://github.com/habibbrox2/broxlab-repo.git';

// Backup চালু/বন্ধ
$create_backup = true;
$max_backups   = 5;

// Site/Project নাম — backup ফাইলের নামে ব্যবহার হবে
// যেমন: broxbhai → site_broxbhai_20240101_120000
$project_name = 'broxbhai';

// ============================================================
// SECURITY SETTINGS
// ============================================================

$admin_api_key      = 'change_this_to_a_strong_random_key_12345';
$enable_ip_whitelist = false;

// ============================================================
// NOTIFICATION SETTINGS
// ============================================================

$email_enabled  = false;
$notify_email   = 'your@email.com';
$notify_from    = 'webhook@yourdomain.com';

$slack_enabled     = false;
$slack_webhook_url = 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK';

// ============================================================
// CODE — সাধারণত পরিবর্তনের দরকার নেই
// ============================================================

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

define('SETTINGS_TABLE', 'deploy_webhook_settings');
define('LOGS_TABLE',     'deploy_webhook_logs');
define('VERSIONS_TABLE', 'deploy_versions');

function getDbConnection($config)
{
    $mysqli = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
    if ($mysqli->connect_error) return null;
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function ensureTablesExist($mysqli)
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS " . SETTINGS_TABLE . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type VARCHAR(20) DEFAULT 'string',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysqli->query("CREATE TABLE IF NOT EXISTS " . LOGS_TABLE . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_id VARCHAR(100),
        event_type VARCHAR(50),
        payload LONGTEXT,
        signature_verified TINYINT(1) DEFAULT 0,
        deployment_triggered TINYINT(1) DEFAULT 0,
        deployment_status VARCHAR(20),
        deploy_path VARCHAR(500),
        backup_created TINYINT(1) DEFAULT 0,
        version_tag VARCHAR(100),
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysqli->query("CREATE TABLE IF NOT EXISTS " . VERSIONS_TABLE . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        version_tag VARCHAR(100) NOT NULL,
        commit_hash VARCHAR(100),
        description TEXT,
        backup_path VARCHAR(500),
        db_backup_path VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_version_tag (version_tag)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $defaults = [
        ['key' => 'webhook_enabled',      'value' => $GLOBALS['webhook_enabled'] ? '1' : '0'],
        ['key' => 'webhook_secret',        'value' => $GLOBALS['webhook_secret']],
        ['key' => 'webhook_branch',        'value' => $GLOBALS['target_branch']],
        ['key' => 'webhook_auto_deploy',   'value' => $GLOBALS['auto_deploy'] ? '1' : '0'],
        ['key' => 'deploy_path',           'value' => $GLOBALS['deploy_path']],
        ['key' => 'create_backup',         'value' => $GLOBALS['create_backup'] ? '1' : '0'],
        ['key' => 'max_backups',           'value' => (string)$GLOBALS['max_backups']],
        ['key' => 'project_name',          'value' => $GLOBALS['project_name']],
        ['key' => 'admin_api_key',         'value' => $GLOBALS['admin_api_key']],
        ['key' => 'last_webhook_delivery', 'value' => 'Never'],
    ];

    foreach ($defaults as $default) {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO " . SETTINGS_TABLE . " (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $default['key'], $default['value']);
        $stmt->execute();
        $stmt->close();
    }
}

function getSetting($mysqli, $key, $default = null)
{
    $stmt = $mysqli->prepare("SELECT setting_value FROM " . SETTINGS_TABLE . " WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['setting_value'];
    }
    $stmt->close();
    return $default;
}

function verifySignature($payload, $signature, $secret)
{
    if (empty($secret)) return true;
    return hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $signature);
}

function verifyAdminApiKey($provided_key)
{
    global $adminKey;
    $expected = $adminKey;
    if (empty($expected) || $expected === 'change_this_to_a_strong_random_key_12345') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '$admin_api_key সেট করা হয়নি।']);
        exit;
    }
    if (empty($provided_key) || !hash_equals($expected, $provided_key)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid API Key']);
        exit;
    }
}

function verifyGitHubIP($remoteIP)
{
    $ranges = ['192.30.252.0/22', '185.199.108.0/22', '140.82.112.0/20', '143.55.64.0/20'];
    foreach ($ranges as $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        if ((ip2long($remoteIP) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet)) return true;
    }
    return false;
}

function logDelivery($mysqli, $data)
{
    $stmt = $mysqli->prepare("INSERT INTO " . LOGS_TABLE . "
        (delivery_id, event_type, payload, signature_verified, deployment_triggered,
         deployment_status, deploy_path, backup_created, version_tag, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sssiississ",
        $data['delivery_id'],
        $data['event_type'],
        $data['payload'],
        $data['signature_verified'],
        $data['deployment_triggered'],
        $data['deployment_status'],
        $data['deploy_path'],
        $data['backup_created'],
        $data['version_tag'],
        $data['ip_address']
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Site backup তৈরি করা
 * Path pattern: /home/tdhuedhn/repo/site_{projectname}_{datetime}/
 */
function createBackup($deployPath, $projectName, $timestamp)
{
    $backupBaseDir = '/home/tdhuedhn/repo';
    $backupDir     = $backupBaseDir . '/site_' . $projectName . '_' . $timestamp;

    if (!is_dir($backupBaseDir)) mkdir($backupBaseDir, 0755, true);
    if (!is_dir($backupDir))     mkdir($backupDir,     0755, true);

    $exclude = ['.git', 'vendor', 'node_modules', '.env'];
    copyDirectory($deployPath, $backupDir, $exclude);

    return $backupDir;
}

/**
 * DB backup তৈরি করা
 * Path pattern: /home/tdhuedhn/repo/db/site_{projectname}_{datetime}.sql
 */
function createDbBackup($dbConfig, $projectName, $timestamp)
{
    $dbBackupDir = '/home/tdhuedhn/repo/db';
    if (!is_dir($dbBackupDir)) mkdir($dbBackupDir, 0755, true);

    $backupFile = $dbBackupDir . '/site' . $projectName . '_' . $timestamp . '.sql';

    $cmd = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
        escapeshellarg($dbConfig['host']),
        escapeshellarg($dbConfig['username']),
        escapeshellarg($dbConfig['password']),
        escapeshellarg($dbConfig['database']),
        escapeshellarg($backupFile)
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($backupFile)) {
        return ['success' => false, 'path' => null, 'error' => implode("\n", $output)];
    }

    return ['success' => true, 'path' => $backupFile];
}

function copyDirectory($src, $dst, $exclude = [])
{
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if (in_array($file, $exclude) || $file[0] === '.') continue;
        $s = $src . '/' . $file;
        $d = $dst . '/' . $file;
        is_dir($s) ? copyDirectory($s, $d, $exclude) : copy($s, $d);
    }
    closedir($dir);
    return true;
}

function cleanupOldBackups($projectName, $maxBackups)
{
    $backupBaseDir = '/home/tdhuedhn/repo';
    $backups = glob($backupBaseDir . '/site_' . $projectName . '_*');
    if (!$backups || count($backups) <= $maxBackups) return;
    usort($backups, fn($a, $b) => filemtime($a) - filemtime($b));
    while (count($backups) > $maxBackups) {
        deleteDirectory(array_shift($backups));
    }
}

function cleanupOldDbBackups($projectName, $maxBackups)
{
    $dbBackupDir = '/home/tdhuedhn/repo/db';
    $backups = glob($dbBackupDir . '/site' . $projectName . '_*.sql');
    if (!$backups || count($backups) <= $maxBackups) return;
    usort($backups, fn($a, $b) => filemtime($a) - filemtime($b));
    while (count($backups) > $maxBackups) {
        $oldest = array_shift($backups);
        if (file_exists($oldest)) unlink($oldest);
    }
}

function deleteDirectory($dir)
{
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

// [BUG FIX] branch এখন parameter থেকে আসে, hardcode নয়
function runGitPull($deployPath, $branch = 'main')
{
    if (!is_dir($deployPath . '/.git')) {
        return ['success' => false, 'message' => 'Git repo পাওয়া যায়নি। আগে git clone করুন।'];
    }
    chdir($deployPath);
    $output = [];
    $returnCode = 0;
    exec('git pull origin ' . escapeshellarg($branch) . ' 2>&1', $output, $returnCode);
    return ['success' => ($returnCode === 0), 'output' => implode("\n", $output), 'return_code' => $returnCode];
}

function getVersions($mysqli, $limit = 10)
{
    $result   = $mysqli->query("SELECT * FROM " . VERSIONS_TABLE . " ORDER BY created_at DESC LIMIT " . (int)$limit);
    $versions = [];
    while ($row = $result->fetch_assoc()) $versions[] = $row;
    return $versions;
}

function sendEmailNotification($status, $details, $projectName = 'BROXBHAI')
{
    if (!$GLOBALS['email_enabled']) return;
    $emoji   = $status === 'success' ? '✅' : '❌';
    $subject = $emoji . ' ' . strtoupper($projectName) . ' Deploy ' . strtoupper($status) . ' — ' . date('Y-m-d H:i:s');
    $body    = "GitHub Webhook Deployment — " . strtoupper($projectName) . "\n";
    $body   .= "======================================\n";
    $body   .= "Status     : " . strtoupper($status) . "\n";
    $body   .= "সময়       : " . date('Y-m-d H:i:s') . "\n";
    $body   .= "Branch     : " . ($details['branch']     ?? 'N/A') . "\n";
    $body   .= "Commit     : " . ($details['commit']     ?? 'N/A') . "\n";
    $body   .= "Message    : " . ($details['message']    ?? 'N/A') . "\n";
    $body   .= "Deploy Path: " . ($details['path']       ?? 'N/A') . "\n";
    $body   .= "Site Backup: " . ($details['backup']     ?? 'N/A') . "\n";
    $body   .= "DB Backup  : " . ($details['db_backup']  ?? 'N/A') . "\n";
    if (!empty($details['git_output'])) $body .= "\nGit Output:\n" . $details['git_output'] . "\n";
    $headers  = "From: {$GLOBALS['notify_from']}\r\nReply-To: {$GLOBALS['notify_from']}\r\nX-Mailer: " . strtoupper($projectName) . "-Webhook\r\n";
    @mail($GLOBALS['notify_email'], $subject, $body, $headers);
}

function sendSlackNotification($status, $details, $projectName = 'BROXBHAI')
{
    if (!$GLOBALS['slack_enabled'] || empty($GLOBALS['slack_webhook_url'])) return;
    $emoji = $status === 'success' ? ':white_check_mark:' : ':x:';
    $color = $status === 'success' ? '#36a64f' : '#cc0000';
    $payload = json_encode(['attachments' => [[
        'color'  => $color,
        'title'  => $emoji . ' ' . strtoupper($projectName) . ' Deploy ' . strtoupper($status),
        'fields' => [
            ['title' => 'Branch',      'value' => $details['branch']    ?? 'N/A', 'short' => true],
            ['title' => 'Status',      'value' => strtoupper($status),             'short' => true],
            ['title' => 'Commit',      'value' => $details['commit']    ?? 'N/A', 'short' => true],
            ['title' => 'সময়',        'value' => date('Y-m-d H:i:s'),             'short' => true],
            ['title' => 'Commit Msg',  'value' => $details['message']   ?? 'N/A', 'short' => false],
            ['title' => 'Site Backup', 'value' => $details['backup']    ?? 'N/A', 'short' => false],
            ['title' => 'DB Backup',   'value' => $details['db_backup'] ?? 'N/A', 'short' => false],
        ],
        'footer' => strtoupper($projectName) . ' GitHub Auto-Deploy',
        'ts'     => time(),
    ]]]);
    $ch = curl_init($GLOBALS['slack_webhook_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ============================================================
// MAIN HANDLER
// ============================================================

try {
    $mysqli = getDbConnection($db_config);
    if (!$mysqli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database সংযোগ ব্যর্থ।']);
        exit;
    }

    ensureTablesExist($mysqli);

    $enabled    = getSetting($mysqli, 'webhook_enabled',    $webhook_enabled);
    $secret     = getSetting($mysqli, 'webhook_secret',     $webhook_secret);
    $branch     = getSetting($mysqli, 'webhook_branch',     $target_branch);
    $auto       = getSetting($mysqli, 'webhook_auto_deploy', $auto_deploy);
    $deployPath = getSetting($mysqli, 'deploy_path',        $deploy_path);
    $doBackup   = getSetting($mysqli, 'create_backup',      $create_backup);
    $maxBackups = (int)getSetting($mysqli, 'max_backups',   $max_backups);
    $projectName = getSetting($mysqli, 'project_name',     $project_name);
    $adminKey   = getSetting($mysqli, 'admin_api_key',     $admin_api_key);

    $action = $_GET['action'] ?? 'webhook';

    // ===== ACTION: webhook =====
    if ($action === 'webhook') {

        if (!$enabled) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Webhook বন্ধ আছে।']);
            exit;
        }

        $remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($enable_ip_whitelist && !verifyGitHubIP($remoteIP)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: অননুমোদিত IP।']);
            exit;
        }

        $signature   = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        $event       = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
        $delivery    = $_SERVER['HTTP_X_GITHUB_DELIVERY'] ?? uniqid();
        $payload     = file_get_contents('php://input');
        $payloadJson = json_decode($payload, true);

        if (!empty($secret) && !verifySignature($payload, $signature, $secret)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid signature।']);
            exit;
        }

        if ($event !== 'push') {
            echo json_encode(['success' => true, 'message' => 'Event উপেক্ষা করা হয়েছে।', 'event' => $event]);
            exit;
        }

        $ref             = $payloadJson['ref'] ?? '';
        $triggeredBranch = preg_replace('#^refs/heads/#', '', $ref);
        $shouldDeploy    = ($triggeredBranch === $branch);

        $backupCreated = 0;
        $versionTag    = '';
        $gitResult     = [];
        $deployStatus  = 'ignored';
        $backupPath    = null;
        $dbBackupPath  = null;
        $commitHash    = $payloadJson['after'] ?? '';
        $commitMessage = $payloadJson['head_commit']['message'] ?? 'Webhook deployment';

        if ($shouldDeploy && $auto) {
            $timestamp  = date('Y-m-d_His');
            $versionTag = 'v' . date('YmdHis');

            if ($doBackup) {
                // Site files backup → /home/tdhuedhn/repo/site_broxbhai_TIMESTAMP/
                $backupPath = createBackup($deployPath, $projectName, $timestamp);
                cleanupOldBackups($projectName, $maxBackups);

                // DB backup → /home/tdhuedhn/repo/db/sitebroxbhai_TIMESTAMP.sql
                $dbResult   = createDbBackup($db_config, $projectName, $timestamp);
                $dbBackupPath = $dbResult['success'] ? $dbResult['path'] : 'DB backup ব্যর্থ';
                cleanupOldDbBackups($projectName, $maxBackups);

                // Version DB তে সেভ করা
                $stmt = $mysqli->prepare("INSERT INTO " . VERSIONS_TABLE . " (version_tag, commit_hash, description, backup_path, db_backup_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $versionTag, $commitHash, $commitMessage, $backupPath, $dbBackupPath);
                $stmt->execute();
                $stmt->close();

                $backupCreated = 1;
            }

            // Git pull
            $gitResult    = runGitPull($deployPath, $branch);
            $deployStatus = $gitResult['success'] ? 'success' : 'failed';

            // Notification
            $notifyDetails = [
                'branch'    => $triggeredBranch,
                'commit'    => substr($commitHash, 0, 7),
                'message'   => $commitMessage,
                'path'      => $deployPath,
                'backup'    => $backupPath    ?? 'তৈরি হয়নি',
                'db_backup' => $dbBackupPath  ?? 'তৈরি হয়নি',
                'git_output' => $gitResult['output'] ?? '',
            ];
            sendEmailNotification($deployStatus, $notifyDetails, $projectName);
            sendSlackNotification($deployStatus, $notifyDetails, $projectName);
        }

        logDelivery($mysqli, [
            'delivery_id'          => $delivery,
            'event_type'           => $event,
            'payload'              => $payload,
            'signature_verified'   => !empty($signature) ? 1 : 0,
            'deployment_triggered' => ($shouldDeploy && $auto) ? 1 : 0,
            'deployment_status'    => $deployStatus,
            'deploy_path'          => $deployPath,
            'backup_created'       => $backupCreated,
            'version_tag'          => $versionTag,
            'ip_address'           => $remoteIP
        ]);

        $mysqli->query("INSERT INTO " . SETTINGS_TABLE . " (setting_key, setting_value) VALUES ('last_webhook_delivery', NOW())
            ON DUPLICATE KEY UPDATE setting_value = NOW()");

        echo json_encode([
            'success'        => $gitResult['success'] ?? !$shouldDeploy,
            'message'        => $shouldDeploy ? ($gitResult['success'] ? 'Deploy সফল!' : 'Deploy ব্যর্থ।') : 'Branch মেলেনি।',
            'branch'         => $triggeredBranch,
            'version'        => $versionTag,
            'backup_path'    => $backupPath,
            'db_backup_path' => $dbBackupPath,
            'git_output'     => $gitResult['output'] ?? null
        ]);
    }

    // ===== ACTION: versions =====
    elseif ($action === 'versions') {
        verifyAdminApiKey($_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
        echo json_encode(['success' => true, 'versions' => getVersions($mysqli)]);
    }

    // ===== ACTION: rollback =====
    elseif ($action === 'rollback') {
        verifyAdminApiKey($_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');

        $versionTag = $_GET['version'] ?? '';
        if (empty($versionTag)) {
            echo json_encode(['success' => false, 'message' => '?version= দরকার।']);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT * FROM " . VERSIONS_TABLE . " WHERE version_tag = ?");
        $stmt->bind_param("s", $versionTag);
        $stmt->execute();
        $version = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$version) {
            echo json_encode(['success' => false, 'message' => 'Version পাওয়া যায়নি।']);
            exit;
        }

        // Rollback-এর আগে current state সংরক্ষণ
        $ts            = date('Y-m-d_His');
        $currentBackup = createBackup($deployPath, $projectName . '_prerollback', $ts);
        createDbBackup($db_config, $projectName . '_prerollback', $ts);

        if (is_dir($version['backup_path'])) {
            copyDirectory($version['backup_path'], $deployPath);
            sendEmailNotification('success', [
                'branch' => $branch,
                'commit' => $version['commit_hash'],
                'message' => 'Rollback to ' . $versionTag,
                'path' => $deployPath,
                'backup' => $currentBackup
            ], $projectName);
            sendSlackNotification('success', [
                'branch' => $branch,
                'commit' => $version['commit_hash'],
                'message' => 'Rollback to ' . $versionTag,
                'path' => $deployPath,
                'backup' => $currentBackup
            ], $projectName);
            echo json_encode(['success' => true, 'message' => 'Rollback সফল।', 'version' => $versionTag]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Backup ফোল্ডার নেই।']);
        }
    }

    // ===== ACTION: status =====
    elseif ($action === 'status') {
        verifyAdminApiKey($_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
        $lastDelivery = getSetting($mysqli, 'last_webhook_delivery', 'কখনো হয়নি');
        $siteBackups  = count(glob('/home/tdhuedhn/repo/site_' . $projectName . '_*') ?: []);
        $dbBackups    = count(glob('/home/tdhuedhn/repo/db/site' . $projectName . '_*.sql') ?: []);
        echo json_encode([
            'success'       => true,
            'project'       => $projectName,
            'enabled'       => (bool)$enabled,
            'branch'        => $branch,
            'deploy_path'   => $deployPath,
            'last_delivery' => $lastDelivery,
            'site_backups'  => $siteBackups,
            'db_backups'    => $dbBackups,
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'অজানা action।']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
