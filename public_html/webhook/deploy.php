<?php
// এই ফাইলটি Cpanel Webhosting এর জন্য তৈরি করা হয়েছে।
$config = require __DIR__ . '/deploy_config.php';




// ═══════════════════════════════

// ১. Security Check

// ═══════════════════════════════

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$payload   = file_get_contents('php://input');

$expected  = 'sha256=' . hash_hmac('sha256', $payload, $config['secret']);




if (!hash_equals($expected, $signature)) {

    http_response_code(401);

    die('Unauthorized');
}




$data = json_decode($payload, true);

if (($data['ref'] ?? '') !== 'refs/heads/' . $config['branch']) {

    die('Branch mismatch, skipping.');
}




// ═══════════════════════════════

// ২. Deploy শুরু করো

// ═══════════════════════════════

$deployer = new Deployer($config);

$deployer->run();




// ═══════════════════════════════

// Deployer Class

// ═══════════════════════════════

class Deployer
{

    private $config;

    private $newVersion;

    private $oldVersion;

    private $backupPath;

    private $log = [];




    public function __construct($config)
    {

        $this->config = $config;
    }




    public function run()
    {

        try {

            $this->log('🚀 Deploy শুরু হচ্ছে...');




            $this->loadVersions();       // বর্তমান version জানো

            $this->createBackup();       // Backup নাও

            $this->gitPull();            // নতুন code আনো + Dependencies install

            $this->detectNewVersion();   // নতুন version কত?

            $this->runMigrations();      // DB migration করো

            $this->saveVersion();        // Version save করো

            $this->cleanOldBackups();    // পুরনো backup মুছো




            $this->log('✅ Deploy সফল! Version: ' . $this->newVersion);

            $this->writeLog();

            http_response_code(200);

            echo implode("\n", $this->log);
        } catch (Exception $e) {

            $this->log('❌ Error: ' . $e->getMessage());

            $this->log('⏪ Rollback শুরু হচ্ছে...');

            $this->rollback();

            $this->writeLog();

            http_response_code(500);

            echo implode("\n", $this->log);
        }
    }




    // ─────────────────────────────

    // Version Load

    // ─────────────────────────────

    private function loadVersions()
    {

        $vfile = $this->config['version_file'];

        if (file_exists($vfile)) {

            $data = json_decode(file_get_contents($vfile), true);

            $this->oldVersion = $data['version'] ?? 'v0.0.0';
        } else {

            $this->oldVersion = 'v0.0.0';
        }

        $this->log('📌 Current version: ' . $this->oldVersion);
    }




    // ─────────────────────────────

    // Backup

    // ─────────────────────────────

    private function createBackup()
    {

        $backupDir  = $this->config['backup_dir'];

        $folderName = $this->oldVersion . '_' . date('Y-m-d_H-i-s');

        $this->backupPath = $backupDir . '/' . $folderName;




        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);




        // Files backup

        $this->exec("cp -r {$this->config['project_dir']} {$this->backupPath}");

        $this->log('💾 File backup নেওয়া হয়েছে: ' . $folderName);




        // Database backup

        $this->backupDatabase($this->backupPath);
    }




    private function backupDatabase($backupPath)
    {

        $c    = $this->config;

        $file = $backupPath . '/db_backup.sql';

        $cmd  = "mysqldump -h{$c['db_host']} -u{$c['db_user']} -p{$c['db_pass']} {$c['db_name']} > {$file} 2>&1";

        $this->exec($cmd);

        $this->log('🗄️ Database backup নেওয়া হয়েছে।');
    }




    // ─────────────────────────────

    // Git Pull + Dependencies Install

    // ─────────────────────────────

    private function gitPull()
    {
        $dir    = escapeshellarg($this->config['project_dir']);
        $branch = escapeshellarg($this->config['branch']);

        // Save deploy_config.php before git reset (if exists)
        $configBackup = sys_get_temp_dir() . '/deploy_config_backup.php';
        $configFile = $dir . '/webhook/deploy_config.php';
        if (file_exists($configFile)) {
            copy($configFile, $configBackup);
            $this->log('💾 deploy_config.php backup নেওয়া হয়েছে');
        }

        // Use git fetch + reset --hard for clean deployment
        $output = $this->exec("cd {$dir} && git fetch origin && git reset --hard origin/{$branch}");
        $this->log('📥 Git reset --hard সম্পন্ন: ' . trim($output));

        // Restore deploy_config.php after git reset
        if (file_exists($configBackup)) {
            if (!is_dir($dir . '/webhook')) {
                @mkdir($dir . '/webhook', 0755, true);
            }
            copy($configBackup, $configFile);
            @unlink($configBackup);
            $this->log('💾 deploy_config.php restore করা হয়েছে');
        }

        // Install Composer dependencies
        $this->runComposerInstall($dir);

        // Install NPM dependencies (production only)
        $this->runNpmInstall($dir);
    }

    // ─────────────────────────────

    // Composer Install

    // ─────────────────────────────

    private function runComposerInstall($dir)
    {
        $composerFile = $this->config['project_dir'] . '/composer.json';
        if (!file_exists($composerFile)) {
            $this->log('ℹ️ composer.json পাওয়া যায়নি, skip।');
            return;
        }
        $this->log('📦 Composer install শুরু...');
        $output = $this->exec("cd {$dir} && composer install --no-dev --optimize-autoloader 2>&1");
        $this->log('✅ Composer install সম্পন্ন');
    }

    // ─────────────────────────────

    // NPM Install (Production)

    // ─────────────────────────────

    private function runNpmInstall($dir)
    {
        $npmFile = $this->config['project_dir'] . '/package.json';
        if (!file_exists($npmFile)) {
            $this->log('ℹ️ package.json পাওয়া যায়নি, skip।');
            return;
        }
        $this->log('📦 NPM install শুরু...');
        $output = $this->exec("cd {$dir} && npm install --production 2>&1");
        $this->log('✅ NPM install সম্পন্ন');
    }




    // ─────────────────────────────

    // নতুন Version detect করো

    // ─────────────────────────────

    private function detectNewVersion()
    {

        // Git tag থেকে version নাও

        $dir    = escapeshellarg($this->config['project_dir']);

        $tag    = trim($this->exec("cd {$dir} && git describe --tags --abbrev=0 2>/dev/null"));




        if ($tag) {

            $this->newVersion = $tag;
        } else {

            // Tag না থাকলে auto increment করো

            $this->newVersion = $this->incrementVersion($this->oldVersion);
        }

        $this->log('🏷️ নতুন version: ' . $this->newVersion);
    }




    private function incrementVersion($version)
    {

        // v1.0.5 → v1.0.6

        preg_match('/v?(\d+)\.(\d+)\.(\d+)/', $version, $m);

        if ($m) {

            return 'v' . $m[1] . '.' . $m[2] . '.' . ($m[3] + 1);
        }

        return 'v1.0.1';
    }




    // ─────────────────────────────

    // DB Migration

    // ─────────────────────────────

    private function runMigrations()
    {

        $migDir  = $this->config['migration_dir'];

        $vfile   = $this->config['version_file'];




        if (!is_dir($migDir)) {

            $this->log('ℹ️ Migration folder নেই, skip।');

            return;
        }




        // কোন migrations ইতিমধ্যে চলেছে?

        $ran = [];

        if (file_exists($vfile)) {

            $data = json_decode(file_get_contents($vfile), true);

            $ran  = $data['migrations'] ?? [];
        }




        // নতুন .sql files খোঁজো

        $files = glob($migDir . '/*.sql');

        sort($files);

        $count = 0;




        foreach ($files as $file) {

            $name = basename($file);

            if (in_array($name, $ran)) continue; // আগেই চলেছে




            $sql = file_get_contents($file);

            $this->runSQL($sql);

            $ran[] = $name;

            $count++;

            $this->log('🔧 Migration চলেছে: ' . $name);
        }




        if ($count === 0) $this->log('ℹ️ কোনো নতুন migration নেই।');




        // Ran migrations save করো

        $this->saveVersion($ran);
    }




    private function runSQL($sql)
    {

        $c   = $this->config;

        $pdo = new PDO(

            "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],

            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]

        );
        // Multiple statements চালাও

        foreach (explode(';', $sql) as $stmt) {

            $stmt = trim($stmt);

            if ($stmt) $pdo->exec($stmt);
        }
    }




    // ─────────────────────────────

    // Version Save

    // ─────────────────────────────

    private function saveVersion($migrations = null)
    {

        $vfile = $this->config['version_file'];

        $old   = file_exists($vfile) ? json_decode(file_get_contents($vfile), true) : [];




        $data = [

            'version'     => $this->newVersion ?? $this->oldVersion,

            'deployed_at' => date('Y-m-d H:i:s'),

            'backup'      => basename($this->backupPath ?? ''),

            'migrations'  => $migrations ?? ($old['migrations'] ?? []),

            'history'     => array_slice(array_merge($old['history'] ?? [], [[

                'version' => $this->newVersion ?? $this->oldVersion,

                'date'    => date('Y-m-d H:i:s'),

            ]]), -20), // শেষ ২০টা রাখো

        ];




        file_put_contents($vfile, json_encode($data, JSON_PRETTY_PRINT));
    }




    // ─────────────────────────────

    // Rollback

    // ─────────────────────────────

    private function rollback()
    {

        if (!$this->backupPath || !is_dir($this->backupPath)) {

            $this->log('⚠️ Backup পাওয়া যায়নি, rollback সম্ভব না।');

            return;
        }




        $projectDir = $this->config['project_dir'];




        // Files restore

        $this->exec("rm -rf {$projectDir} && cp -r {$this->backupPath} {$projectDir}");

        $this->log('📂 Files restore হয়েছে।');




        // Database restore

        $sqlFile = $this->backupPath . '/db_backup.sql';

        if (file_exists($sqlFile)) {

            $c   = $this->config;

            $cmd = "mysql -h{$c['db_host']} -u{$c['db_user']} -p{$c['db_pass']} {$c['db_name']} < {$sqlFile} 2>&1";

            $this->exec($cmd);

            $this->log('🗄️ Database restore হয়েছে।');
        }




        $this->log('✅ Rollback সম্পন্ন। Version: ' . $this->oldVersion);
    }




    // ─────────────────────────────

    // পুরনো Backup মুছো

    // ─────────────────────────────

    private function cleanOldBackups()
    {

        $backupDir = $this->config['backup_dir'];

        $keep      = $this->config['keep_backups'];

        $dirs      = glob($backupDir . '/*', GLOB_ONLYDIR);




        if (count($dirs) <= $keep) return;




        sort($dirs); // পুরনোটা আগে

        $toDelete = array_slice($dirs, 0, count($dirs) - $keep);




        foreach ($toDelete as $dir) {

            $this->exec("rm -rf " . escapeshellarg($dir));

            $this->log('🗑️ পুরনো backup মুছা হয়েছে: ' . basename($dir));
        }
    }




    // ─────────────────────────────

    // Helpers

    // ─────────────────────────────

    private function exec($cmd)
    {

        $output = shell_exec($cmd . ' 2>&1');

        if ($output === null) throw new Exception("Command failed: $cmd");

        return $output;
    }




    private function log($msg)
    {

        $this->log[] = '[' . date('H:i:s') . '] ' . $msg;
    }




    private function writeLog()
    {

        $entry = date('Y-m-d') . ' Deploy Log' . "\n"

            . str_repeat('─', 40) . "\n"

            . implode("\n", $this->log) . "\n\n";

        file_put_contents($this->config['log_file'], $entry, FILE_APPEND);
    }
}
