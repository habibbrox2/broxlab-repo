<?php

/**
 * GitHub Webhook — Auto Deploy
 * cPanel / Shared Hosting — Signature-hardened edition
 *
 * Verified against GitHub's official test vector:
 *   secret  = "It's a Secret to Everybody"
 *   payload = "Hello, World!"
 *   result  = sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17
 *
 * Every known failure cause is handled:
 *   [1] php://input read FIRST — before require, before anything
 *   [2] Secret trimmed of accidental whitespace / newlines
 *   [3] Payload NOT modified before HMAC (only stripped after verification)
 *   [4] Header resolved via 3 strategies (CGI strips HTTP_* keys on cPanel)
 *   [5] Payload handled as raw bytes / UTF-8 (GitHub requirement)
 *   [6] Timing-safe comparison via hash_equals()
 *   [7] Debug mode logs exact received vs computed hash + all headers
 */

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════════════════════
// [1] Capture raw body IMMEDIATELY — absolute first statement, nothing before.
//     On cPanel CGI/FastCGI, php://input is a one-shot stream. Any I/O before
//     this read (even a require) can drain or close it, returning empty string.
// ══════════════════════════════════════════════════════════════════════════════
$rawPayload = (string) file_get_contents('php://input');

// ── Load config AFTER body is captured ────────────────────────────────────────
$config = require __DIR__ . '/deploy_config.php';

// ══════════════════════════════════════════════════════════════════════════════
// [2] Sanitise the secret
//     A trailing \n or space in deploy_config.php produces a completely
//     different HMAC. Always trim.
// ══════════════════════════════════════════════════════════════════════════════
$secret = trim((string)($config['secret'] ?? ''));

// ══════════════════════════════════════════════════════════════════════════════
// [3] Compute HMAC on the RAW body — before ANY normalisation.
//     GitHub signs the exact bytes it sends. If we strip BOM or CRLF first,
//     our hash won't match GitHub's hash. Compute first, clean later.
// ══════════════════════════════════════════════════════════════════════════════
$computedSig = 'sha256=' . hash_hmac('sha256', $rawPayload, $secret);

// ══════════════════════════════════════════════════════════════════════════════
// [4] Resolve the X-Hub-Signature-256 header — 3 strategies
//
//     Strategy A: Standard PHP CGI mapping  HTTP_X_HUB_SIGNATURE_256
//     Strategy B: Case-insensitive scan of all $_SERVER keys
//     Strategy C: getallheaders() — bypasses CGI stripping entirely
//     (Apache on cPanel FastCGI strips non-standard headers from $_SERVER)
// ══════════════════════════════════════════════════════════════════════════════
function resolveHeader(string $needle): string
{
    // A — direct PHP CGI key
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $needle));
    if (!empty($_SERVER[$key])) return (string)$_SERVER[$key];

    // B — case-insensitive scan
    $norm = strtolower(str_replace(['-', '_'], '', $needle));
    foreach ($_SERVER as $k => $v) {
        $kn = strtolower(str_replace(['-', '_', 'HTTP'], '', $k));
        if ($kn === $norm) return (string)$v;
    }

    // C — getallheaders() (works even when CGI strips HTTP_* keys)
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === strtolower($needle)) return (string)$value;
        }
    }

    return '';
}

$receivedSig = resolveHeader('X-Hub-Signature-256');
$event       = resolveHeader('X-GitHub-Event') ?: 'push';

// ══════════════════════════════════════════════════════════════════════════════
// [5] + [6] Verify — timing-safe, raw bytes, UTF-8 (GitHub requirement)
//
//     Debug mode: set 'debug' => true in deploy_config.php, redeliver once,
//     then read  <log_dir>/sig-debug.log  to see exactly what went wrong.
// ══════════════════════════════════════════════════════════════════════════════
if (!empty($config['debug'])) {
    $allHeaders  = function_exists('getallheaders') ? getallheaders() : ['getallheaders()' => 'not available'];
    $serverHttps = array_filter($_SERVER, fn($k) => str_starts_with($k, 'HTTP_'), ARRAY_FILTER_USE_KEY);

    // Self-test against GitHub's official test vector
    $testSig = hash_hmac('sha256', 'Hello, World!', "It's a Secret to Everybody");
    $testOk  = ($testSig === '757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17');

    $entry = implode("\n", [
        str_repeat('═', 60),
        'Time         : ' . date('c'),
        'HMAC self-test: ' . ($testOk ? 'PASS ✓' : 'FAIL ✗ — PHP HMAC broken on this server'),
        '─── Signature ───',
        'Received     : ' . ($receivedSig ?: '(EMPTY — header not found by any method)'),
        'Computed     : ' . $computedSig,
        'Match        : ' . (($receivedSig && hash_equals($computedSig, $receivedSig)) ? 'YES ✓' : 'NO ✗'),
        'Secret len   : ' . strlen($secret) . ' chars  (if 0, secret is empty in config)',
        '─── Payload ───',
        'Body length  : ' . strlen($rawPayload) . ' bytes',
        'Body hex[20] : ' . bin2hex(substr($rawPayload, 0, 20)),
        'Body text[80]: ' . substr($rawPayload, 0, 80),
        'Starts BOM   : ' . (str_starts_with($rawPayload, "\xEF\xBB\xBF") ? 'YES — proxy injected BOM' : 'no'),
        'Contains CRLF: ' . (str_contains($rawPayload, "\r\n") ? 'YES — proxy converted line endings' : 'no'),
        '─── All headers from getallheaders() ───',
        json_encode($allHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        '─── $_SERVER HTTP_* keys ───',
        json_encode($serverHttps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        '',
    ]);
    @file_put_contents(dirname($config['log_file']) . '/sig-debug.log', $entry, FILE_APPEND);
}

// ── Guard: header missing ──────────────────────────────────────────────────────
if ($receivedSig === '') {
    http_response_code(401);
    error_log('[deploy] X-Hub-Signature-256 header not found. Set debug=true in config to diagnose.');
    die('Unauthorized: signature header missing');
}

// ── Guard: signature mismatch ─────────────────────────────────────────────────
if (!hash_equals($computedSig, $receivedSig)) {
    http_response_code(401);
    error_log('[deploy] Signature mismatch. Set debug=true in deploy_config.php to diagnose.');
    die('Unauthorized: signature mismatch');
}

// ══════════════════════════════════════════════════════════════════════════════
// Signature verified. Now safe to parse + act on the payload.
// ══════════════════════════════════════════════════════════════════════════════
$data = json_decode($rawPayload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die('Bad Request: invalid JSON');
}

// Ping event — GitHub fires this on webhook creation to confirm the endpoint
if ($event === 'ping') {
    http_response_code(200);
    echo json_encode(['status' => 'pong', 'webhook' => 'active', 'zen' => $data['zen'] ?? '']);
    exit;
}

// Branch filter
if (($data['ref'] ?? '') !== 'refs/heads/' . $config['branch']) {
    http_response_code(200);
    echo json_encode(['status' => 'skipped', 'reason' => 'branch mismatch', 'ref' => $data['ref'] ?? '']);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// Run deployer
// ══════════════════════════════════════════════════════════════════════════════
$deployer = new Deployer($config);
$deployer->run();


// ══════════════════════════════════════════════════════════════════════════════
// DEPLOYER CLASS — original working logic, hardened shell escaping
// ══════════════════════════════════════════════════════════════════════════════
class Deployer
{
    private array   $config;
    private ?string $newVersion = null;
    private ?string $oldVersion = null;
    private ?string $backupPath = null;
    private array   $log        = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function run(): void
    {
        try {
            $this->log('🚀 Deploy শুরু হচ্ছে...');
            $this->loadVersions();
            $this->createBackup();
            $this->gitPull();
            $this->detectNewVersion();
            $this->runMigrations();
            $this->saveVersion();
            $this->cleanOldBackups();
            $this->log('✅ Deploy সফল! Version: ' . $this->newVersion);
            $this->writeLog();
            http_response_code(200);
            echo implode("\n", $this->log);
        } catch (Throwable $e) {
            $this->log('❌ Error: ' . $e->getMessage());
            $this->log('⏪ Rollback শুরু হচ্ছে...');
            $this->rollback();
            $this->writeLog();
            http_response_code(500);
            echo implode("\n", $this->log);
        }
    }

    private function loadVersions(): void
    {
        $vfile = $this->config['version_file'];
        if (file_exists($vfile)) {
            $data = json_decode((string)file_get_contents($vfile), true) ?? [];
            $this->oldVersion = $data['version'] ?? 'v0.0.0';
        } else {
            $this->oldVersion = 'v0.0.0';
        }
        $this->log('📌 Current version: ' . $this->oldVersion);
    }

    private function createBackup(): void
    {
        $backupDir  = $this->config['backup_dir'];
        $folderName = $this->oldVersion . '_' . date('Y-m-d_H-i-s');
        $this->backupPath = $backupDir . '/' . $folderName;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

        $this->exec('cp -r ' . escapeshellarg($this->config['project_dir'])
            . ' '      . escapeshellarg($this->backupPath));
        $this->log('💾 File backup নেওয়া হয়েছে: ' . $folderName);
        $this->backupDatabase($this->backupPath);
    }

    private function backupDatabase(string $backupPath): void
    {
        $c   = $this->config;
        $cmd = 'mysqldump'
            . ' -h' . escapeshellarg($c['db_host'])
            . ' -u' . escapeshellarg($c['db_user'])
            . ' -p' . escapeshellarg($c['db_pass'])
            . ' '   . escapeshellarg($c['db_name'])
            . ' > ' . escapeshellarg($backupPath . '/db_backup.sql')
            . ' 2>&1';
        $this->exec($cmd);
        $this->log('🗄️ Database backup নেওয়া হয়েছে।');
    }

    private function gitPull(): void
    {
        $dir    = escapeshellarg($this->config['project_dir']);
        $branch = escapeshellarg($this->config['branch']);

        // Preserve deploy_config.php across git reset --hard
        $configFile   = $this->config['project_dir'] . '/webhook/deploy_config.php';
        $configBackup = sys_get_temp_dir() . '/dpl_cfg_' . md5($this->config['project_dir']) . '.php';
        $hasConfig    = file_exists($configFile);
        if ($hasConfig) {
            copy($configFile, $configBackup);
            $this->log('💾 deploy_config.php backed up');
        }

        $out = $this->exec("cd {$dir} && git fetch origin && git reset --hard origin/{$branch}");
        $this->log('📥 Git reset --hard: ' . trim($out));

        if ($hasConfig && file_exists($configBackup)) {
            if (!is_dir(dirname($configFile))) @mkdir(dirname($configFile), 0755, true);
            copy($configBackup, $configFile);
            @unlink($configBackup);
            $this->log('💾 deploy_config.php restored');
        }

        // Composer
        if (file_exists($this->config['project_dir'] . '/composer.json')) {
            $this->log('📦 Composer install...');
            $this->exec("cd {$dir} && composer install --no-dev --optimize-autoloader 2>&1");
            $this->log('✅ Composer done');
        }

        // NPM
        if (file_exists($this->config['project_dir'] . '/package.json')) {
            $lock = file_exists($this->config['project_dir'] . '/package-lock.json') ? 'ci' : 'install';
            $this->log("📦 NPM {$lock}...");
            $this->exec("cd {$dir} && npm {$lock} --production 2>&1");
            $this->log('✅ NPM done');
        }
    }

    private function detectNewVersion(): void
    {
        $dir = escapeshellarg($this->config['project_dir']);
        $tag = trim((string)(@shell_exec("cd {$dir} && git describe --tags --abbrev=0 2>/dev/null") ?? ''));
        $this->newVersion = $tag !== '' ? $tag : $this->incrementVersion($this->oldVersion ?? 'v0.0.0');
        $this->log('🏷️ নতুন version: ' . $this->newVersion);
    }

    private function incrementVersion(string $v): string
    {
        preg_match('/v?(\d+)\.(\d+)\.(\d+)/', $v, $m);
        return $m ? 'v' . $m[1] . '.' . $m[2] . '.' . ($m[3] + 1) : 'v1.0.1';
    }

    private function runMigrations(): void
    {
        $migDir = $this->config['migration_dir'];
        $vfile  = $this->config['version_file'];
        if (!is_dir($migDir)) {
            $this->log('ℹ️ Migration folder নেই, skip।');
            return;
        }

        $ran = [];
        if (file_exists($vfile)) {
            $data = json_decode((string)file_get_contents($vfile), true) ?? [];
            $ran  = $data['migrations'] ?? [];
        }

        $files = glob($migDir . '/*.sql') ?: [];
        sort($files);
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) continue;
            $this->runSQL((string)file_get_contents($file));
            $ran[] = $name;
            $count++;
            $this->log('🔧 Migration: ' . $name);
        }

        $this->log($count === 0 ? 'ℹ️ কোনো নতুন migration নেই।' : "{$count}টি migration সম্পন্ন।");
        $this->saveVersion($ran);
    }

    private function runSQL(string $sql): void
    {
        $c   = $this->config;
        $pdo = new PDO(
            "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->beginTransaction();
        try {
            foreach (explode(';', $sql) as $s) {
                $s = trim($s);
                if ($s !== '') $pdo->exec($s);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function saveVersion(?array $migrations = null): void
    {
        $vfile   = $this->config['version_file'];
        $old     = file_exists($vfile) ? (json_decode((string)file_get_contents($vfile), true) ?? []) : [];
        $history = $old['history'] ?? [];
        $history[] = ['version' => $this->newVersion ?? $this->oldVersion, 'date' => date('Y-m-d H:i:s')];

        file_put_contents($vfile, json_encode([
            'version'     => $this->newVersion ?? $this->oldVersion,
            'deployed_at' => date('Y-m-d H:i:s'),
            'backup'      => basename($this->backupPath ?? ''),
            'migrations'  => $migrations ?? ($old['migrations'] ?? []),
            'history'     => array_slice($history, -20),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function rollback(): void
    {
        if (!$this->backupPath || !is_dir($this->backupPath)) {
            $this->log('⚠️ Backup পাওয়া যায়নি, rollback সম্ভব না।');
            return;
        }
        $dir = escapeshellarg($this->config['project_dir']);
        $bak = escapeshellarg($this->backupPath);
        $this->exec("rm -rf {$dir} && cp -r {$bak} {$dir}");
        $this->log('📂 Files restore হয়েছে।');

        $sqlFile = $this->backupPath . '/db_backup.sql';
        if (file_exists($sqlFile)) {
            $c = $this->config;
            $this->exec('mysql'
                . ' -h' . escapeshellarg($c['db_host'])
                . ' -u' . escapeshellarg($c['db_user'])
                . ' -p' . escapeshellarg($c['db_pass'])
                . ' '   . escapeshellarg($c['db_name'])
                . ' < ' . escapeshellarg($sqlFile) . ' 2>&1');
            $this->log('🗄️ Database restore হয়েছে।');
        }
        $this->log('✅ Rollback সম্পন্ন। Version: ' . $this->oldVersion);
    }

    private function cleanOldBackups(): void
    {
        $backupDir = $this->config['backup_dir'];
        $keep      = (int)($this->config['keep_backups'] ?? 5);
        $dirs      = glob($backupDir . '/*', GLOB_ONLYDIR) ?: [];
        if (count($dirs) <= $keep) return;
        sort($dirs);
        foreach (array_slice($dirs, 0, count($dirs) - $keep) as $d) {
            $this->exec('rm -rf ' . escapeshellarg($d));
            $this->log('🗑️ পুরনো backup মুছা হয়েছে: ' . basename($d));
        }
    }

    private function exec(string $cmd): string
    {
        $out = shell_exec($cmd . ' 2>&1');
        if ($out === null) throw new RuntimeException("Command failed: $cmd");
        return $out;
    }

    private function log(string $msg): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $msg;
    }

    private function writeLog(): void
    {
        @file_put_contents(
            $this->config['log_file'],
            date('Y-m-d') . " Deploy Log\n" . str_repeat('─', 40) . "\n"
                . implode("\n", $this->log) . "\n\n",
            FILE_APPEND
        );
    }
}
