<?php

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║         GitHub Webhook → Enterprise Auto Deploy System          ║
 * ║         Single File | cPanel Shared Hosting Compatible          ║
 * ╠══════════════════════════════════════════════════════════════════╣
 * ║  ✔ Atomic Zero-Downtime Deploy (symlink switching)              ║
 * ║  ✔ GitHub IP Whitelist + HMAC Signature Verification            ║
 * ║  ✔ Deploy Lock (Race Condition Prevention)                      ║
 * ║  ✔ Smart Diff Deploy (composer/npm শুধু দরকারে)                 ║
 * ║  ✔ DB Migration (Transaction-safe)                              ║
 * ║  ✔ Auto Rollback on Failure                                     ║
 * ║  ✔ Health Check After Deploy                                    ║
 * ║  ✔ Webhook Rate Limiting                                        ║
 * ║  ✔ GitHub Commit Info Logging                                   ║
 * ║  ✔ JSON Structured Logs                                         ║
 * ║  ✔ Deploy Status API                                            ║
 * ║  ✔ Command Injection Protection (escapeshellarg everywhere)     ║
 * ╠══════════════════════════════════════════════════════════════════╣
 * ║  Setup:                                                         ║
 * ║   1. নিচের CONFIGURATION section আপনার তথ্য দিয়ে পূরণ করুন    ║
 * ║   2. public_html/webhook/deploy.php তে আপলোড করুন              ║
 * ║   3. GitHub Webhook URL: https://yourdomain.com/webhook/github.php ║
 * ║   4. Content-Type: application/json | Events: Just the push event ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */

// ╔══════════════════════════════════════════════════════════════════╗
// ║                       CONFIGURATION                             ║
// ╚══════════════════════════════════════════════════════════════════╝

$config = [

    // ── Security ────────────────────────────────────────────────────
    'secret'           => 'your_github_webhook_secret_here',  // <-- পরিবর্তন করুন
    'branch'           => 'main',

    // GitHub IP whitelist check (true = enable, false = disable)
    // Note: cPanel reverse proxy থাকলে false রাখুন
    'verify_github_ip' => false,

    // ── Paths (YOUR_USERNAME → আপনার cPanel username দিন) ──────────
    // Project root যেখানে git repo আছে
    'project_dir'      => '/home/YOUR_USERNAME/public_html',

    // Atomic deploy এর জন্য releases folder (public_html এর বাইরে)
    'releases_dir'     => '/home/YOUR_USERNAME/deploys/releases',

    // Symlink যেটা "current" release point করবে
    'current_link'     => '/home/YOUR_USERNAME/deploys/current',

    // Shared files/dirs (releases এ symlink হবে, deploy এ মুছবে না)
    'shared_dirs'      => ['storage', 'public/uploads'],
    'shared_files'     => ['.env'],
    'shared_path'      => '/home/YOUR_USERNAME/deploys/shared',

    // DB backup রাখার জন্য
    'backup_dir'       => '/home/YOUR_USERNAME/deploys/backups',

    // Version + log files
    'version_file'     => '/home/YOUR_USERNAME/deploys/version.json',
    'log_file'         => '/home/YOUR_USERNAME/deploys/deploy.log',
    'migration_dir'    => '/home/YOUR_USERNAME/public_html/migrations',

    // Health check URL (deploy শেষে verify করবে, '' হলে skip)
    'health_check_url' => 'https://yourdomain.com/health-check.php',

    // ── Release Settings ─────────────────────────────────────────────
    'keep_releases'    => 5,

    // ── Database ─────────────────────────────────────────────────────
    'db_host'          => 'localhost',
    'db_name'          => 'your_database_name',      // <-- পরিবর্তন করুন
    'db_user'          => 'your_database_user',      // <-- পরিবর্তন করুন
    'db_pass'          => 'your_database_password',  // <-- পরিবর্তন করুন

    // ── Advanced ─────────────────────────────────────────────────────
    'auto_deploy'      => true,   // false → শুধু log হবে
    'dry_run'          => false,  // true  → simulate, আসলে কিছু হবে না

    // দুই deploy এর মধ্যে কমপক্ষে কত সেকেন্ড বিরতি
    'rate_limit_sec'   => 15,
];


// ╔══════════════════════════════════════════════════════════════════╗
// ║                 INITIALIZATION (Auto-Generate)                  ║
// ║    Creates version.json if it doesn't exist on first run         ║
// ╚══════════════════════════════════════════════════════════════════╝

function initializeVersionFile(array $config): void
{
    $vfile = $config['version_file'];

    // যদি version.json ইতিমধ্যে থাকে, তাহলে কিছু করো না
    if (file_exists($vfile)) {
        return;
    }

    // Directory তৈরি করো যদি না থাকে
    $dir = dirname($vfile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // Initial version data structure
    $initialData = [
        'version'      => 'v0.0.0',
        'deployed_at'  => date('Y-m-d H:i:s'),
        'last_status'  => 'initialized',
        'release_path' => null,
        'last_commit'  => null,
        'last_author'  => null,
        'last_message' => null,
        'migrations'   => [],
        'history'      => [[
            'version'   => 'v0.0.0',
            'date'      => date('Y-m-d H:i:s'),
            'status'    => 'initialized',
            'commit'    => null,
            'author'    => null,
            'message'   => 'System initialized',
            'release'   => null,
        ]],
    ];

    // JSON file এ লেখো
    if (@file_put_contents($vfile, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        error_log('[Deploy] Version file generated: ' . $vfile);
    } else {
        error_log('[Deploy] Warning: Could not create version file: ' . $vfile);
    }
}

// Initialize করার সময় আমন্ত্রণ জানাও
initializeVersionFile($config);


// ╔══════════════════════════════════════════════════════════════════╗
// ║                    DEPLOY STATUS API                            ║
// ║   GET /deploy.php?status=1&token=YOUR_SECRET                   ║
// ╚══════════════════════════════════════════════════════════════════╝

if (isset($_GET['status'])) {
    header('Content-Type: application/json');

    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_GET['token'] ?? '');
    if ($token !== 'Bearer ' . $config['secret'] && $token !== $config['secret']) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $vfile = $config['version_file'];
    if (file_exists($vfile)) {
        $data = json_decode(file_get_contents($vfile), true) ?? [];
        echo json_encode([
            'version'     => $data['version']      ?? 'unknown',
            'status'      => $data['last_status']  ?? 'unknown',
            'last_deploy' => $data['deployed_at']  ?? 'never',
            'commit'      => $data['last_commit']  ?? null,
            'author'      => $data['last_author']  ?? null,
            'message'     => $data['last_message'] ?? null,
            'history'     => array_slice(array_reverse($data['history'] ?? []), 0, 10),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['version' => 'v0.0.0', 'status' => 'never_deployed']);
    }
    exit;
}


// ╔══════════════════════════════════════════════════════════════════╗
// ║                    WEBHOOK HANDLER                              ║
// ╚══════════════════════════════════════════════════════════════════╝

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$event     = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

// ── 1. Rate Limit ────────────────────────────────────────────────────
$rateLockFile = sys_get_temp_dir() . '/deploy.last';
if (file_exists($rateLockFile)) {
    $elapsed = time() - (int) file_get_contents($rateLockFile);
    if ($elapsed < $config['rate_limit_sec']) {
        http_response_code(429);
        die('Too many deploy requests. Wait ' . ($config['rate_limit_sec'] - $elapsed) . 's.');
    }
}

// ── 2. GitHub IP Whitelist (optional) ────────────────────────────────
if ($config['verify_github_ip']) {
    $clientIp = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
        : ($_SERVER['REMOTE_ADDR'] ?? '');

    $githubCidrs = [
        '192.30.252.0/22',
        '185.199.108.0/22',
        '140.82.112.0/20',
        '143.55.64.0/20',
    ];

    $allowed = false;
    foreach ($githubCidrs as $cidr) {
        if (ipInCidr(trim($clientIp), $cidr)) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        error_log("Webhook blocked: IP $clientIp not in GitHub CIDR range.");
        die('Forbidden: IP not whitelisted');
    }
}

// ── 3. HMAC Signature Verification ───────────────────────────────────
if (!empty($config['secret'])) {
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $config['secret']);
    if (!hash_equals($expected, $signature)) {
        http_response_code(401);
        error_log('Webhook signature mismatch');
        die('Unauthorized');
    }
}

// ── 4. JSON Decode ────────────────────────────────────────────────────
$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die('Bad Request: Invalid JSON');
}

// ── 5. Event Routing ──────────────────────────────────────────────────
switch ($event) {

    case 'ping':
        echo json_encode(['status' => 'ok', 'message' => 'Webhook is active!']);
        break;

    case 'push':
        $ref = $data['ref'] ?? '';
        if ($ref !== 'refs/heads/' . $config['branch']) {
            echo json_encode(['status' => 'skipped', 'reason' => 'branch mismatch']);
            break;
        }
        if (!$config['auto_deploy']) {
            echo json_encode(['status' => 'skipped', 'reason' => 'auto_deploy disabled']);
            break;
        }

        // Rate limit timestamp update
        file_put_contents($rateLockFile, time());

        // Commit info
        $commitInfo = [
            'id'      => substr($data['head_commit']['id'] ?? 'unknown', 0, 7),
            'author'  => $data['head_commit']['author']['name'] ?? 'unknown',
            'message' => trim($data['head_commit']['message'] ?? ''),
            'repo'    => $data['repository']['full_name'] ?? 'unknown',
        ];

        $deployer = new Deployer($config, $commitInfo);
        $deployer->run();
        break;

    default:
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'event' => $event]);
        break;
}

// ── Helper: CIDR Check ────────────────────────────────────────────────
function ipInCidr(string $ip, string $cidr): bool
{
    if (strpos($cidr, '/') === false) return $ip === $cidr;
    [$subnet, $bits] = explode('/', $cidr);
    $ipLong     = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) return false;
    $mask = -1 << (32 - (int)$bits);
    return ($ipLong & $mask) === ($subnetLong & $mask);
}


// ╔══════════════════════════════════════════════════════════════════╗
// ║                      DEPLOYER CLASS                             ║
// ╚══════════════════════════════════════════════════════════════════╝

class Deployer
{
    private array  $config;
    private array  $commit;
    private string $newVersion  = '';
    private string $oldVersion  = 'v0.0.0';
    private string $releasePath = '';
    private array  $log         = [];
    private string $lockFile;

    public function __construct(array $config, array $commit)
    {
        $this->config   = $config;
        $this->commit   = $commit;
        $this->lockFile = sys_get_temp_dir() . '/deploy.lock';
    }

    // ── Main Entry Point ───────────────────────────────────────────
    public function run(): void
    {
        // ── Deploy Lock (Race Condition Prevention) ────────────────
        if (file_exists($this->lockFile)) {
            $since = time() - (int) file_get_contents($this->lockFile);
            if ($since < 600) { // 10 মিনিটের বেশি পুরনো হলে stale lock ধরা হবে
                http_response_code(423);
                die(json_encode(['status' => 'locked', 'message' => 'Deploy already running']));
            }
        }
        file_put_contents($this->lockFile, time());
        register_shutdown_function(fn() => @unlink($this->lockFile));

        try {
            $this->log('🚀 Deploy শুরু হচ্ছে...');
            $this->log("📋 Commit [{$this->commit['id']}]: {$this->commit['message']} — by {$this->commit['author']}");

            $this->loadVersions();
            $this->createDbBackup();       // Rollback এর জন্য DB backup
            $this->prepareRelease();       // নতুন release dir তৈরি করো
            $this->gitCloneOrPull();       // Code আনো
            $this->detectNewVersion();     // Version detect
            $this->linkSharedResources();  // Shared files/dirs symlink
            $this->runSmartDependencies(); // Diff-based composer/npm
            $this->runMigrations();        // DB migration (transaction-safe)
            $this->switchSymlink();        // Atomic: current → new release
            $this->runHealthCheck();       // Deploy সফল কিনা verify
            $this->saveVersion('success');
            $this->cleanOldReleases();

            $this->log('✅ Deploy সফল! Version: ' . $this->newVersion);
            $this->writeLog();
            http_response_code(200);
            echo json_encode([
                'status'  => 'success',
                'version' => $this->newVersion,
                'commit'  => $this->commit,
                'log'     => $this->log,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->log('❌ Error: ' . $e->getMessage());
            $this->log('⏪ Rollback শুরু হচ্ছে...');
            $this->rollback();
            $this->saveVersion('failed');
            $this->writeLog();
            http_response_code(500);
            echo json_encode([
                'status' => 'failed',
                'error'  => $e->getMessage(),
                'log'    => $this->log,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    // ── Version Load ───────────────────────────────────────────────
    private function loadVersions(): void
    {
        $vfile = $this->config['version_file'];
        if (file_exists($vfile)) {
            $data             = json_decode(file_get_contents($vfile), true) ?? [];
            $this->oldVersion = $data['version'] ?? 'v0.0.0';
        }
        $this->log('📌 Current version: ' . $this->oldVersion);
    }

    // ── DB Backup (Rollback এর জন্য) ──────────────────────────────
    private function createDbBackup(): void
    {
        $backupDir = $this->config['backup_dir'];
        $timestamp = date('Ymd_His');

        if (!is_dir($backupDir) && !$this->config['dry_run']) {
            mkdir($backupDir, 0755, true);
        }

        $c    = $this->config;
        $file = $backupDir . '/db_' . $timestamp . '.sql';

        $host = escapeshellarg($c['db_host']);
        $user = escapeshellarg($c['db_user']);
        $pass = escapeshellarg($c['db_pass']);
        $db   = escapeshellarg($c['db_name']);
        $out  = escapeshellarg($file);

        if (!$this->config['dry_run']) {
            $this->exec("mysqldump -h{$host} -u{$user} -p{$pass} {$db} > {$out} 2>&1");
        }
        $this->log('🗄️ DB backup নেওয়া হয়েছে: db_' . $timestamp . '.sql');
    }

    // ── Prepare Release Directory ──────────────────────────────────
    private function prepareRelease(): void
    {
        $releasesDir = $this->config['releases_dir'];
        if (!is_dir($releasesDir) && !$this->config['dry_run']) {
            mkdir($releasesDir, 0755, true);
        }

        $this->releasePath = $releasesDir . '/' . date('Ymd_His');

        if (!$this->config['dry_run']) {
            mkdir($this->releasePath, 0755, true);
        }
        $this->log('📁 Release directory তৈরি: ' . basename($this->releasePath));
    }

    // ── Git Clone / Pull ───────────────────────────────────────────
    private function gitCloneOrPull(): void
    {
        $projectDir  = $this->config['project_dir'];
        $releasePath = escapeshellarg($this->releasePath);
        $branch      = escapeshellarg($this->config['branch']);

        if ($this->config['dry_run']) {
            $this->log('🔵 [dry_run] git clone simulate।');
            return;
        }

        if (is_dir($projectDir . '/.git')) {
            // Local clone — fast (git objects reuse করে)
            $srcDir = escapeshellarg($projectDir);
            $this->exec("cd {$srcDir} && git fetch origin 2>&1");
            $this->exec("git clone --local --branch {$branch} {$srcDir} {$releasePath} 2>&1");
        } else {
            // Fallback: project_dir তে pull করে release এ copy
            $srcDir = escapeshellarg($projectDir);
            $this->exec("cd {$srcDir} && git fetch origin && git checkout {$branch} && git pull origin {$branch} 2>&1");
            $this->exec("cp -r {$srcDir}/. {$releasePath}/");
        }
        $this->log('📥 Code আনা হয়েছে।');
    }

    // ── Detect New Version ─────────────────────────────────────────
    private function detectNewVersion(): void
    {
        $dir = escapeshellarg($this->releasePath ?: $this->config['project_dir']);
        $tag = trim((string)($this->exec("cd {$dir} && git describe --tags --abbrev=0 2>/dev/null") ?? ''));

        $this->newVersion = $tag ?: $this->incrementVersion($this->oldVersion);
        $this->log('🏷️ নতুন version: ' . $this->newVersion);
    }

    private function incrementVersion(string $v): string
    {
        preg_match('/v?(\d+)\.(\d+)\.(\d+)/', $v, $m);
        return $m ? "v{$m[1]}.{$m[2]}." . ($m[3] + 1) : 'v1.0.1';
    }

    // ── Shared Resources Symlink ───────────────────────────────────
    private function linkSharedResources(): void
    {
        $sharedPath  = $this->config['shared_path'];
        $releasePath = $this->releasePath;

        if ($this->config['dry_run']) {
            $this->log('🔵 [dry_run] shared resources link simulate।');
            return;
        }

        if (!is_dir($sharedPath)) mkdir($sharedPath, 0755, true);

        foreach ($this->config['shared_dirs'] as $dir) {
            $sharedDir  = $sharedPath . '/' . $dir;
            $releaseDir = $releasePath . '/' . $dir;

            if (!is_dir($sharedDir)) mkdir($sharedDir, 0755, true);
            if (is_dir($releaseDir)) $this->exec("rm -rf " . escapeshellarg($releaseDir));

            $this->exec("ln -sfn " . escapeshellarg($sharedDir) . " " . escapeshellarg($releaseDir));
            $this->log("🔗 Dir linked: $dir");
        }

        foreach ($this->config['shared_files'] as $file) {
            $sharedFile  = $sharedPath . '/' . $file;
            $releaseFile = $releasePath . '/' . $file;

            if (!file_exists($sharedFile)) touch($sharedFile);
            if (file_exists($releaseFile)) @unlink($releaseFile);

            $this->exec("ln -sfn " . escapeshellarg($sharedFile) . " " . escapeshellarg($releaseFile));
            $this->log("🔗 File linked: $file");
        }
    }

    // ── Smart Diff-based Dependencies ─────────────────────────────
    private function runSmartDependencies(): void
    {
        $srcDir = $this->releasePath ?: $this->config['project_dir'];
        $dir    = escapeshellarg($srcDir);

        $diff = (string)($this->exec("cd {$dir} && git diff --name-only HEAD@{1} HEAD 2>/dev/null") ?? '');

        // Composer
        if (file_exists($srcDir . '/composer.json')) {
            $needsComposer = $diff === ''
                || str_contains($diff, 'composer.json')
                || str_contains($diff, 'composer.lock');

            if ($needsComposer) {
                $this->log('📦 Composer install শুরু...');
                if (!$this->config['dry_run']) {
                    $this->exec("cd {$dir} && composer install --no-dev --optimize-autoloader 2>&1");
                }
                $this->log('✅ Composer install সম্পন্ন।');
            } else {
                $this->log('⏭️ composer.json অপরিবর্তিত, install skip।');
            }
        }

        // NPM
        if (file_exists($srcDir . '/package.json')) {
            $needsNpm = $diff === ''
                || str_contains($diff, 'package.json')
                || str_contains($diff, 'package-lock.json');

            if ($needsNpm) {
                $this->log('📦 NPM install শুরু...');
                if (!$this->config['dry_run']) {
                    $this->exec("cd {$dir} && npm install --production 2>&1");
                }
                $this->log('✅ NPM install সম্পন্ন।');
            } else {
                $this->log('⏭️ package.json অপরিবর্তিত, install skip।');
            }
        }
    }

    // ── DB Migration (Transaction-Safe) ───────────────────────────
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
            $data = json_decode(file_get_contents($vfile), true) ?? [];
            $ran  = $data['migrations'] ?? [];
        }

        $files = glob($migDir . '/*.sql');
        sort($files);
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) continue;

            if (!$this->config['dry_run']) {
                $this->runSQLTransaction(file_get_contents($file));
            }
            $ran[] = $name;
            $count++;
            $this->log('🔧 Migration চলেছে: ' . $name);
        }

        if ($count === 0) {
            $this->log('ℹ️ কোনো নতুন migration নেই।');
        }

        $this->saveVersion('running', $ran);
    }

    private function runSQLTransaction(string $sql): void
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
            foreach (explode(';', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') $pdo->exec($stmt);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \Exception('Migration SQL failed: ' . $e->getMessage());
        }
    }

    // ── Atomic Symlink Switch (Zero Downtime) ──────────────────────
    private function switchSymlink(): void
    {
        $currentLink = $this->config['current_link'];
        $releasePath = $this->releasePath;

        if ($this->config['dry_run']) {
            $this->log('🔵 [dry_run] symlink switch simulate।');
            return;
        }

        $this->exec("ln -sfn " . escapeshellarg($releasePath) . " " . escapeshellarg($currentLink));
        $this->log('🔀 Atomic switch → ' . basename($releasePath));
    }

    // ── Health Check ───────────────────────────────────────────────
    private function runHealthCheck(): void
    {
        $url = trim($this->config['health_check_url'] ?? '');
        if (empty($url) || $this->config['dry_run']) {
            $this->log('ℹ️ Health check skip।');
            return;
        }

        $this->log('🩺 Health check: ' . $url);
        $ctx      = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $ctx);

        if (trim((string)$response) !== 'OK') {
            throw new \Exception('Health check failed! Response: ' . substr((string)$response, 0, 100));
        }
        $this->log('✅ Health check পাস।');
    }

    // ── Rollback ───────────────────────────────────────────────────
    private function rollback(): void
    {
        $currentLink = $this->config['current_link'];
        $releasesDir = $this->config['releases_dir'];

        // পূর্ববর্তী release খোঁজো
        $releases = glob($releasesDir . '/*', GLOB_ONLYDIR);
        sort($releases);

        $prev = null;
        foreach (array_reverse($releases) as $r) {
            if ($r !== $this->releasePath) {
                $prev = $r;
                break;
            }
        }

        if ($prev && !$this->config['dry_run']) {
            $this->exec("ln -sfn " . escapeshellarg($prev) . " " . escapeshellarg($currentLink));
            $this->log('⏪ Rollback → ' . basename($prev));
        } else {
            $this->log('⚠️ Previous release পাওয়া যায়নি।');
        }

        // Failed release cleanup
        if ($this->releasePath && is_dir($this->releasePath) && !$this->config['dry_run']) {
            $this->exec("rm -rf " . escapeshellarg($this->releasePath));
            $this->log('🗑️ Failed release মুছা হয়েছে।');
        }

        // DB rollback
        $this->rollbackDatabase();
    }

    private function rollbackDatabase(): void
    {
        $backupDir = $this->config['backup_dir'];
        $sqlFiles  = glob($backupDir . '/db_*.sql');
        if (empty($sqlFiles)) {
            $this->log('⚠️ DB backup পাওয়া যায়নি।');
            return;
        }

        sort($sqlFiles);
        $latestSql = end($sqlFiles);
        $c         = $this->config;

        $host = escapeshellarg($c['db_host']);
        $user = escapeshellarg($c['db_user']);
        $pass = escapeshellarg($c['db_pass']);
        $db   = escapeshellarg($c['db_name']);
        $sql  = escapeshellarg($latestSql);

        if (!$this->config['dry_run']) {
            $this->exec("mysql -h{$host} -u{$user} -p{$pass} {$db} < {$sql} 2>&1");
        }
        $this->log('🗄️ Database rollback সম্পন্ন।');
    }

    // ── Clean Old Releases ─────────────────────────────────────────
    private function cleanOldReleases(): void
    {
        $releasesDir = $this->config['releases_dir'];
        $keep        = $this->config['keep_releases'];
        $dirs        = glob($releasesDir . '/*', GLOB_ONLYDIR);

        if (!$dirs || count($dirs) <= $keep) return;

        sort($dirs);
        $toDelete = array_slice($dirs, 0, count($dirs) - $keep);

        foreach ($toDelete as $dir) {
            if (!$this->config['dry_run']) {
                $this->exec("rm -rf " . escapeshellarg($dir));
            }
            $this->log('🗑️ পুরনো release মুছা: ' . basename($dir));
        }
    }

    // ── Version Save (JSON Structured) ────────────────────────────
    private function saveVersion(string $status = 'success', ?array $migrations = null): void
    {
        $vfile = $this->config['version_file'];
        $old   = file_exists($vfile) ? (json_decode(file_get_contents($vfile), true) ?? []) : [];

        $data = [
            'version'      => $this->newVersion ?: $this->oldVersion,
            'deployed_at'  => date('Y-m-d H:i:s'),
            'last_status'  => $status,
            'release_path' => basename($this->releasePath ?? ''),
            'last_commit'  => $this->commit['id']      ?? null,
            'last_author'  => $this->commit['author']  ?? null,
            'last_message' => $this->commit['message'] ?? null,
            'migrations'   => $migrations ?? ($old['migrations'] ?? []),
            'history'      => array_slice(array_merge($old['history'] ?? [], [[
                'version' => $this->newVersion ?: $this->oldVersion,
                'date'    => date('Y-m-d H:i:s'),
                'status'  => $status,
                'commit'  => $this->commit['id']      ?? null,
                'author'  => $this->commit['author']  ?? null,
                'message' => $this->commit['message'] ?? null,
                'release' => basename($this->releasePath ?? ''),
            ]]), -20),
        ];

        if (!$this->config['dry_run']) {
            $dir = dirname($vfile);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($vfile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // ── JSON Structured Log Write ──────────────────────────────────
    private function writeLog(): void
    {
        if ($this->config['dry_run']) return;

        $entry = [
            'date'    => date('Y-m-d H:i:s'),
            'version' => $this->newVersion ?: $this->oldVersion,
            'commit'  => $this->commit,
            'steps'   => $this->log,
        ];

        $logFile = $this->config['log_file'];
        $dir     = dirname($logFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // JSON Lines format — প্রতিটা deploy এক line
        file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }

    // ── Helpers ────────────────────────────────────────────────────
    private function exec(string $cmd): ?string
    {
        if ($this->config['dry_run']) {
            return '[dry_run] ' . $cmd;
        }
        $output = shell_exec($cmd . ' 2>&1');
        if ($output === null) {
            throw new \Exception("Command failed: $cmd");
        }
        return $output;
    }

    private function log(string $msg): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $msg;
    }
}
