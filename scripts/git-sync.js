import { execSync } from 'child_process';
import { existsSync, rmSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const root = resolve(__dirname, '..');

const argv = process.argv.slice(2);
let initGit = false;
// Default remote (set to your repo if not overridden via GIT_REMOTE or --remote)
let remoteUrl = process.env.GIT_REMOTE || 'https://github.com/habibbrox2/broxlab.git';
let branch = process.env.GIT_BRANCH || 'master';
let forceRemote = false;

for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg === '--init') {
        initGit = true;
    } else if (arg === '--remote' && argv[i + 1]) {
        remoteUrl = argv[++i];
    } else if (arg === '--branch' && argv[i + 1]) {
        branch = argv[++i];
    } else if (arg === '--force-remote') {
        forceRemote = true;
    } else if (arg === '--help' || arg === '-h') {
        console.log(`Usage: node scripts/git-sync.js [--init] [--remote <url>] [--branch <name>] [--force-remote]`);
        process.exit(0);
    } else {
        console.warn(`Warning: Unknown argument '${arg}'`);
    }
}

function run(command, opts = {}) {
    console.log(`\n> ${command}`);
    execSync(command, { stdio: 'inherit', cwd: root, ...opts });
}

function runCapture(command) {
    return execSync(command, { cwd: root, encoding: 'utf8' }).trim();
}

function cleanFolder(relPath) {
    const abs = resolve(root, relPath);
    if (existsSync(abs)) {
        console.log(`Removing ${relPath}`);
        rmSync(abs, { recursive: true, force: true });
    }
}

function isGitRepo() {
    try {
        execSync('git rev-parse --is-inside-work-tree', {
            cwd: root,
            stdio: ['ignore', 'ignore', 'ignore']
        });
        return true;
    } catch {
        return false;
    }
}

function getGitConfig(key) {
    try {
        return runCapture(`git config --get ${key}`);
    } catch {
        return '';
    }
}

function ensureGitIdentity() {
    const name = getGitConfig('user.name');
    const email = getGitConfig('user.email');

    if (!name || !email) {
        console.log('⚠️ Git user identity not configured; setting local defaults for this repo.');
        if (!name) run('git config user.name "Auto Sync"');
        if (!email) run('git config user.email "auto-sync@localhost"');
    }
}

try {
    // 1) Clean build artifacts
    run('npm run clean:build');

    // 2) Clear caches
    cleanFolder('storage/cache');
    cleanFolder('public_html/uploads/tmp');

    // 3) Generate DB backup (creates timestamped archive + latest.sql)
    run('php scripts/db-backup.php --output full/latest.sql --keep-archives');

    // 4) Ensure git repository (optionally initialize)
    const inRepo = isGitRepo();
    if (!inRepo) {
        if (initGit) {
            console.log('⚠️ Not a git repository; initializing...');
            run('git init');
            run(`git checkout -b ${branch}`);
        } else {
            console.log('⚠️ Not a git repository; skipping git add/commit/push steps.');
            process.exit(0);
        }
    }

    // 5) Optionally set remote
    if (remoteUrl) {
        let existingRemote = null;
        try {
            existingRemote = runCapture('git remote get-url origin');
        } catch {
            existingRemote = null;
        }

        if (!existingRemote) {
            run(`git remote add origin ${remoteUrl}`);
        } else if (forceRemote) {
            run(`git remote set-url origin ${remoteUrl}`);
        } else {
            console.log(`✅ Remote already set to ${existingRemote} (use --force-remote to override)`);
        }
    }

    // 5) Stage changes
    run('git add -A');

    // 6) Commit if needed
    const status = runCapture('git status --porcelain');
    if (!status) {
        console.log('✅ No changes to commit. Exiting.');
        process.exit(0);
    }

    ensureGitIdentity();

    const timestamp = new Date().toISOString().replace('T', ' ').slice(0, 19);
    run(`git commit -m "auto: backup + sync ${timestamp}"`);

    // 7) Push (attempt to keep remote in sync)
    try {
        run('git push');
    } catch (pushErr) {
        console.warn('⚠️ Push failed, trying to rebase onto remote and push again...');
        try {
            run(`git pull --rebase origin ${branch}`);
            run('git push');
        } catch (rebaseErr) {
            console.error('❌ Push still failed after rebase:');
            console.error(rebaseErr.message);
            process.exit(1);
        }
    }

    console.log('\n✅ Sync complete.');
} catch (err) {
    console.error('\n❌ Error during sync:', err.message);
    process.exit(1);
}

