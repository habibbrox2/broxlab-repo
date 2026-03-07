#!/usr/bin/env node
"use strict";

/**
 * Branch Manager for Multi-Location Sync
 * 
 * এই স্ক্রিপ্ট .env এর WORK_LOCATION কনফিগ ব্যবহার করে
 * সঠিক branch এ কাজ করতে সাহায্য করে।
 * 
 * Usage:
 *   node branch-manager.js setup           # প্রথমবার setup
 *   node branch-manager.js switch home     # বাসায় যাওয়ার সময়
 *   node branch-manager.js switch office   # অফিসে যাওয়ার সময়
 *   node branch-manager.js status          # বর্তমান লোকেশন
 *   node branch-manager.js sync            # Safe sync (auto branch)
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
const error = (msg)       => console.error(`${c.red}✖${c.reset}  ${msg}`);
const bold  = (s)         => `${c.bold}${s}${c.reset}`;
const dim_  = (s)         => `${c.dim}${s}${c.reset}`;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function run(cmd, opts = {}) {
  const silent  = opts.silent  ?? false;
  const allowFail = opts.allowFail ?? false;

  if (!silent) log(dim_(`$ ${cmd}`));
  const result = shell.exec(cmd, { silent });

  if (result.code !== 0 && !allowFail) {
    error(`Command failed:\n  ${cmd}\n\n${result.stderr || result.stdout}`);
    process.exit(1);
  }
  return result;
}

function capture(cmd) {
  return shell.exec(cmd, { silent: true }).stdout.trim();
}

function header(title) {
  const line = "─".repeat(50);
  console.log(`\n${c.blue}${line}${c.reset}`);
  console.log(`  ${bold(title)}`);
  console.log(`${c.blue}${line}${c.reset}\n`);
}

/** Load .env file */
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

/** Update .env file */
function updateEnv(envPath, key, value) {
  let content = fs.readFileSync(envPath, "utf-8");
  const regex = new RegExp(`^${key}=.*$`, "m");
  
  if (regex.test(content)) {
    content = content.replace(regex, `${key}=${value}`);
  } else {
    content += `\n${key}=${value}`;
  }
  
  fs.writeFileSync(envPath, content);
}

// ─── Commands ──────────────────────────────────────────────────────────────────

function setup() {
  header("Setup Multi-Location Branches");

  const envPath = path.join(process.cwd(), ".env");
  const env = loadEnv(envPath);

  log("Setting up branches for multi-location sync...");
  
  // Create home branch
  log("\nCreating home branch...");
  run(`git checkout -b ${env.BRANCH_HOME} 2>/dev/null || git checkout ${env.BRANCH_HOME}`, { allowFail: true });
  ok(`${bold(env.BRANCH_HOME)} branch ready.`);

  // Create office branch
  log("Creating office branch...");
  run(`git checkout -b ${env.BRANCH_OFFICE} 2>/dev/null || git checkout ${env.BRANCH_OFFICE}`, { allowFail: true });
  ok(`${bold(env.BRANCH_OFFICE)} branch ready.`);

  log("Returning to current location...");
  run(`git checkout ${env.WORK_LOCATION}`);

  ok(`All branches setup complete!`);
  log(`Current location: ${bold(env.WORK_LOCATION)}`);
}

function switchLocation(location) {
  header(`Switch Location — ${location}`);

  const envPath = path.join(process.cwd(), ".env");
  const env = loadEnv(envPath);

  if (!["home", "office"].includes(location)) {
    error(`Invalid location: ${location}`);
    console.log("Valid options: home, office");
    process.exit(1);
  }

  const branchName = location === "home" ? env.BRANCH_HOME : env.BRANCH_OFFICE;

  log(`Switching to ${bold(location)} location...`);
  log(`Using branch: ${bold(branchName)}`);

  // Stash any uncommitted changes
  const dirty = capture("git status --porcelain") !== "";
  if (dirty) {
    warn("You have uncommitted changes — auto-stashing...");
    run("git stash push -m 'auto-stash before location switch'");
  }

  // Fetch latest
  log("Fetching latest changes...");
  run("git fetch origin");

  // Checkout branch
  log(`Checking out ${branchName}...`);
  run(`git checkout ${branchName}`, { allowFail: true });

  // Update .env
  updateEnv(envPath, "WORK_LOCATION", location);

  ok(`Switched to ${bold(location)} location!`);
  console.log(`\nNow working from: ${bold(location)}`);
  console.log(`Branch: ${bold(branchName)}`);
}

function statusCmd() {
  header("Location Status");

  const envPath = path.join(process.cwd(), ".env");
  const env = loadEnv(envPath);

  const currentBranch = capture("git rev-parse --abbrev-ref HEAD");
  const workLocation = env.WORK_LOCATION || "unknown";

  console.log(`  Current Location    : ${bold(workLocation)}`);
  console.log(`  Current Branch      : ${bold(currentBranch)}`);
  console.log(`  Home Branch         : ${env.BRANCH_HOME || "home"}`);
  console.log(`  Office Branch       : ${env.BRANCH_OFFICE || "office"}`);
  console.log();

  if (currentBranch === env.BRANCH_HOME) {
    ok("You're on HOME branch");
  } else if (currentBranch === env.BRANCH_OFFICE) {
    ok("You're on OFFICE branch");
  } else {
    warn(`You're on ${bold(currentBranch)} (custom branch)`);
  }
}

function sync() {
  header("Safe Sync (Auto Branch)");

  const envPath = path.join(process.cwd(), ".env");
  const env = loadEnv(envPath);

  const workLocation = env.WORK_LOCATION || "home";
  const branchName = workLocation === "home" ? env.BRANCH_HOME : env.BRANCH_OFFICE;

  log(`Syncing for location: ${bold(workLocation)}`);
  log(`Using branch: ${bold(branchName)}`);

  // Make sure on correct branch
  const currentBranch = capture("git rev-parse --abbrev-ref HEAD");
  if (currentBranch !== branchName) {
    log(`Switching to ${branchName}...`);
    run(`git checkout ${branchName}`);
  }

  // Pull
  log(`Pulling latest from origin/${branchName}...`);
  run(`git pull origin ${branchName}`, { allowFail: true });

  ok("Sync complete!");
}

function usage() {
  console.log(`
${bold("Branch Manager")} — Multi-Location Branch Sync

${bold("USAGE")}
  node git-hr/branch-manager.js <command> [location]

${bold("COMMANDS")}
  ${c.cyan}setup${c.reset}              প্রথমবার branches তৈরি করুন
  ${c.cyan}switch${c.reset} <location>   Location পরিবর্তন করুন (home/office)
  ${c.cyan}status${c.reset}              বর্তমান লোকেশন দেখুন
  ${c.cyan}sync${c.reset}               Safe sync (স্বয়ংক্রিয় branch)

${bold("EXAMPLES")}
  node git-hr/branch-manager.js setup
  node git-hr/branch-manager.js switch home
  node git-hr/branch-manager.js switch office
  node git-hr/branch-manager.js status
  node git-hr/branch-manager.js sync
`);
}

// ─── Main ─────────────────────────────────────────────────────────────────────

function main() {
  if (!shell.which("git")) {
    error("git is not installed or not in PATH.");
    process.exit(1);
  }

  const args = process.argv.slice(2);

  if (args.length === 0 || args[0] === "--help" || args[0] === "-h") {
    usage();
    process.exit(0);
  }

  const command = args[0];
  const param = args[1];

  try {
    switch (command) {
      case "setup":
        setup();
        break;
      case "switch":
        if (!param) {
          error("Location required: home or office");
          process.exit(1);
        }
        switchLocation(param);
        break;
      case "status":
        statusCmd();
        break;
      case "sync":
        sync();
        break;
      default:
        error(`Unknown command: ${command}`);
        console.log("Run with --help for usage");
        process.exit(1);
    }
  } catch (err) {
    error(`Error: ${err.message}`);
    process.exit(1);
  }
}

main();
