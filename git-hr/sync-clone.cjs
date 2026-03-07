#!/usr/bin/env node
"use strict";

/**
 * Smart Clone Script for BroxBhai with Opposite Branch Detection
 * 
 * This script:
 * 1. Reads GitHub repo URL from .env file
 * 2. Detects WORK_LOCATION (home/office)
 * 3. Clones the OPPOSITE branch (so you get the other location's code)
 * 4. Installs dependencies
 * 5. Sets up local development environment
 * 
 * Example:
 *   - You're at OFFICE (WORK_LOCATION=office) → clones HOME branch
 *   - You're at HOME (WORK_LOCATION=home) → clones OFFICE branch
 * 
 * Usage:
 *   node sync-clone.cjs                    (auto-detects branch from .env)
 *   node sync-clone.cjs <repo-url>         (uses provided URL)
 *   node sync-clone.cjs <repo-url> <path>  (clones to specific path)
 */

const fs = require("fs");
const path = require("path");
const shell = require("shelljs");

// ─── ANSI Colors ─────────────────────────────────────────────────────────────
const c = {
  reset:  "\x1b[0m",
  bold:   "\x1b[1m",
  dim:    "\x1b[2m",
  red:    "\x1b[31m",
  green:  "\x1b[32m",
  yellow: "\x1b[33m",
  blue:   "\x1b[34m",
  cyan:   "\x1b[36m",
};

const log   = (msg)       => console.log(`${c.cyan}ℹ${c.reset}  ${msg}`);
const ok    = (msg)       => console.log(`${c.green}✔${c.reset}  ${msg}`);
const warn  = (msg)       => console.warn(`${c.yellow}⚠${c.reset}  ${msg}`);
const fatal = (msg, code = 1) => { console.error(`${c.red}✖${c.reset}  ${msg}`); process.exit(code); };
const bold  = (s)         => `${c.bold}${s}${c.reset}`;
const dim   = (s)         => `${c.dim}${s}${c.reset}`;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Run a shell command; exit on failure unless {allowFail:true} */
function run(cmd, opts = {}) {
  const silent  = opts.silent  ?? false;
  const allowFail = opts.allowFail ?? false;

  if (!silent) log(dim(`$ ${cmd}`));
  const result = shell.exec(cmd, { silent });

  if (result.code !== 0 && !allowFail) {
    fatal(`Command failed:\n  ${cmd}\n\n${result.stderr || result.stdout}`);
  }
  return result;
}

/** Return stdout of a command (always silent). */
function capture(cmd) {
  return shell.exec(cmd, { silent: true }).stdout.trim();
}

/** Pretty-print a section header. */
function header(title) {
  const line = "─".repeat(50);
  console.log(`\n${c.blue}${line}${c.reset}`);
  console.log(`  ${bold(title)}`);
  console.log(`${c.blue}${line}${c.reset}\n`);
}

/** Load environment variables from .env file */
function loadEnv(envPath) {
  if (!fs.existsSync(envPath)) {
    return {};
  }

  const env = {};
  const content = fs.readFileSync(envPath, "utf-8");
  
  content.split("\n").forEach((line) => {
    line = line.trim();
    if (line && !line.startsWith("#")) {
      const [key, ...values] = line.split("=");
      env[key.trim()] = values.join("=").trim();
    }
  });

  return env;
}

// ─── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  if (!shell.which("git")) fatal("git is not installed or not in PATH.");

  header("BroxBhai Smart Clone Setup (Opposite Branch)");

  // Get repository URL
  let repoUrl = process.argv[2];
  const clonePath = process.argv[3];
  let branchToClone = null;
  let currentLocation = null;

  if (!repoUrl) {
    log("Reading configuration from .env…");
    const envPath = path.join(process.cwd(), ".env");
    const env = loadEnv(envPath);
    
    repoUrl = env.GITHUB_REPO_URL;
    
    if (!repoUrl) {
      fatal(`.env file not found or GITHUB_REPO_URL not set.\nCreate .env file with: GITHUB_REPO_URL=<your-repo-url>`);
    }

    // ✨ Smart Branch Detection: Clone opposite branch for current location
    currentLocation = env.WORK_LOCATION || "home";
    const branchHome = env.BRANCH_HOME || "home";
    const branchOffice = env.BRANCH_OFFICE || "office";

    if (currentLocation === "office") {
      branchToClone = branchHome;  // office location → clone home branch
      log(`📍 Current location: ${bold("OFFICE")} → Cloning ${bold(branchHome)} branch`);
      log(`   (You'll get the HOME location's code)`);
    } else if (currentLocation === "home") {
      branchToClone = branchOffice;  // home location → clone office branch
      log(`📍 Current location: ${bold("HOME")} → Cloning ${bold(branchOffice)} branch`);
      log(`   (You'll get the OFFICE location's code)`);
    }
  }

  // Extract project directory name from URL (for display only)
  const dirName = clonePath || repoUrl.split("/").pop().replace(".git", "");
  
  log(`📦 Repository: ${bold(repoUrl)}`);
  log(`📂 Directory: ${bold("current directory (.)")}`);
  if (branchToClone) {
    log(`🌿 Branch: ${bold(branchToClone)}`);
  }

  // ⚠️  Remove existing tracked files before cloning
  header("Cleaning Existing Tracked Files");
  log("Getting list of tracked files…");
  const trackedFiles = capture("git ls-files").split("\n").filter(f => f.trim());
  
  if (trackedFiles.length > 0) {
    warn(`Found ${trackedFiles.length} tracked files/folders to remove:`);
    trackedFiles.slice(0, 10).forEach(file => console.log(`  ${dim(file)}`));
    if (trackedFiles.length > 10) console.log(`  ${dim(`... and ${trackedFiles.length - 10} more`)}`);
    
    // Remove tracked files/folders
    log("Removing tracked files…");
    trackedFiles.forEach(file => {
      if (fs.existsSync(file)) {
        try {
          const stat = fs.statSync(file);
          if (stat.isDirectory()) {
            shell.rm("-rf", file);
          } else {
            fs.unlinkSync(file);
          }
        } catch (err) {
          warn(`Could not remove ${file}: ${err.message}`);
        }
      }
    });
    ok(`Removed ${trackedFiles.length} tracked files/folders.`);
  } else {
    log("No tracked files found to remove.");
  }

  // Clone repository directly into current directory
  log(`\n🔄 Cloning repository into current directory…`);
  const cloneCmd = branchToClone 
    ? `git clone --branch ${branchToClone} ${repoUrl} .`
    : `git clone ${repoUrl} .`;
  run(cloneCmd);
  ok(`Repository cloned successfully into current directory.`);

  // Stay in current directory (no chdir needed)

  // Install PHP dependencies (if composer.json exists)
  if (fs.existsSync("composer.json")) {
    log(`\nInstalling PHP dependencies…`);
    run("composer install", { allowFail: true });
    ok(`PHP dependencies installed.`);
  }

  // Install Node dependencies (if package.json exists)
  if (fs.existsSync("package.json")) {
    log(`\nInstalling Node dependencies…`);
    run("npm install");
    ok(`Node dependencies installed.`);
  }

  // Setup local .env if needed
  if (!fs.existsSync(".env") && fs.existsSync(".env.example")) {
    log(`\nSetting up local .env configuration…`);
    shell.cp(".env.example", ".env");
    ok(`.env file created from template.`);
    warn(`Please update .env with your local database credentials!`);
  }

  // Print next steps
  header("Setup Complete!");
  
  console.log(`${bold("✨ What just happened?")}\n`);
  if (currentLocation) {
    if (currentLocation === "office") {
      console.log(`  You were at: ${bold("OFFICE")}`);
      console.log(`  Cloned branch: ${bold("HOME")} (home location's code)`);
    } else {
      console.log(`  You were at: ${bold("HOME")}`);
      console.log(`  Cloned branch: ${bold("OFFICE")} (office location's code)`);
    }
    console.log();
  }
  
  console.log(`${bold("Next steps:")}\n`);
  console.log(`  1. Update .env with your local database credentials:`);
  console.log(`     ${dim(`- DB_HOST`)}, ${dim(`DB_USER`)}, ${dim(`DB_PASS`)}`);
  console.log();
  console.log(`  2. Import database (if needed):`);
  console.log(`     ${dim(`mysql -u username -p database_name < Database/schema.sql`)}`);
  console.log();
  console.log(`  3. Build frontend assets:`);
  console.log(`     ${dim(`npm run build`)}`);
  console.log();
  console.log(`  4. Start development:`);
  console.log(`     ${dim(`npm run dev`)}`);
  console.log();
  console.log(`  5. Start local server:`);
  console.log(`     ${dim(`php -S localhost:8000 -t public_html`)}`);
  console.log();
  ok(`Ready to start development!`);
}

main().catch(err => fatal(`Error: ${err.message}`));
