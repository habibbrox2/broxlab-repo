#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const readline = require("readline");
const shell = require("shelljs");

const c = {
  reset: "\x1b[0m",
  bold: "\x1b[1m",
  dim: "\x1b[2m",
  red: "\x1b[31m",
  green: "\x1b[32m",
  yellow: "\x1b[33m",
  blue: "\x1b[34m",
  cyan: "\x1b[36m",
  white: "\x1b[37m",
  magenta: "\x1b[35m",
};

const SECTION_TITLES = {
  quick: "Quick Actions",
  branch: "Branch + Location",
  advanced: "Advanced",
  utility: "Utility",
};

const SECTION_ORDER = ["quick", "branch", "advanced", "utility"];

const SECTION_DETAILS = {
  quick: "daily git status, pull, push, log, diff",
  branch: "branch actions and home/office location controls",
  advanced: "merge, rebase sync, tag, undo, stash, clean",
  utility: "clone and smart bootstrap helpers",
};

const NAV_BACK = "__nav_back__";
const NAV_MAIN = "__nav_main__";

// ── Console Log Capture System ──────────────────────────────────────────────
const consoleLogBuffer = [];
let isCapturingLogs = false;

function startLogCapture() {
  consoleLogBuffer.length = 0;
  isCapturingLogs = true;
}

function stopLogCapture() {
  isCapturingLogs = false;
}

function pushLog(level, text) {
  if (isCapturingLogs) {
    consoleLogBuffer.push({ level, text, time: new Date().toLocaleTimeString("en-GB") });
  }
}

function printCollapsibleLogs(rl) {
  if (consoleLogBuffer.length === 0) return;
  return new Promise((resolve) => {
    const width = 78;
    const bar = colorize(c.dim, `+${makeRule("-", width - 2)}+`);
    const header = colorize(c.dim, `| ${"Console Log".padEnd(width - 4, " ")} | ${colorize(c.cyan, `[${consoleLogBuffer.length} entries]`)} `);
    const hint = colorize(c.dim, `  Press ${colorize(c.yellow, "L")} to expand logs, any other key to skip`);

    console.log("");
    console.log(bar);
    console.log(header);
    console.log(bar);
    console.log(hint);

    if (!process.stdin.isTTY) {
      resolve();
      return;
    }

    const stdin = process.stdin;
    let expanded = false;

    const onKey = (chunk) => {
      const key = chunk.toString("utf8").toLowerCase();
      stdin.removeListener("data", onKey);
      if (stdin.isTTY) stdin.setRawMode(false);
      stdin.pause();

      if (key === "l" && !expanded) {
        expanded = true;
        console.log("");
        console.log(colorize(c.dim, `+${makeRule("=", width - 2)}+`));
        console.log(colorize(c.dim, `| ${"▼  Console Log (full output)".padEnd(width - 4, " ")} |`));
        console.log(colorize(c.dim, `+${makeRule("-", width - 2)}+`));
        for (const entry of consoleLogBuffer) {
          const levelColor =
            entry.level === "error" ? c.red :
            entry.level === "warn"  ? c.yellow :
            entry.level === "ok"    ? c.green :
                                      c.dim;
          const prefix = colorize(levelColor, `[${entry.time}]`);
          const cleanText = entry.text.replace(/\x1b\[[0-9;]*m/g, "");
          console.log(`  ${prefix} ${colorize(c.dim, cleanText)}`);
        }
        console.log(colorize(c.dim, `+${makeRule("=", width - 2)}+`));
      }
      resolve();
    };

    stdin.resume();
    stdin.setEncoding("utf8");
    stdin.setRawMode(true);
    stdin.on("data", onKey);
  });
}

function colorize(color, text) {
  return `${color}${text}${c.reset}`;
}

function bold(text) {
  return colorize(c.bold, text);
}

function dim(text) {
  return colorize(c.dim, text);
}

function plain(text) {
  return String(text).replace(/\x1b\[[0-9;]*m/g, "");
}

function makeRule(char = "-", width = 78) {
  return char.repeat(width);
}

function printSpacer() {
  console.log("");
}

function printPanel(title, lines = [], accent = c.blue) {
  const width = 78;
  const top = `+${makeRule("=", width - 2)}+`;
  const divider = `+${makeRule("-", width - 2)}+`;
  const safeTitle = plain(title).slice(0, width - 4).padEnd(width - 4, " ");

  console.log(colorize(accent, top));
  console.log(colorize(accent, `| ${safeTitle} |`));
  console.log(colorize(accent, divider));

  for (const line of lines) {
    console.log(`| ${line}`);
  }

  console.log(colorize(accent, top));
}

function printListBlock(title, rows, accent = c.cyan) {
  console.log(colorize(accent, title));
  for (const row of rows) {
    console.log(`  ${row}`);
  }
  console.log("");
}

function printPromptPanel(title, message, accent = c.green) {
  printPanel(title, [message], accent);
}

function log(message) {
  pushLog("info", `[INFO] ${plain(message)}`);
  console.log(`${colorize(c.cyan, "[INFO]")} ${message}`);
}

function ok(message) {
  pushLog("ok", `[DONE] ${plain(message)}`);
  console.log(`${colorize(c.green, "[DONE]")} ${message}`);
}

function warn(message) {
  pushLog("warn", `[WARN] ${plain(message)}`);
  console.warn(`${colorize(c.yellow, "[WARN]")} ${message}`);
}

function fatal(message, code = 1) {
  pushLog("error", `[FAIL] ${plain(message)}`);
  console.error(`${colorize(c.red, "[FAIL]")} ${message}`);
  process.exit(code);
}

function quote(value) {
  return JSON.stringify(String(value));
}

class CommandError extends Error {
  constructor(message, command, result) {
    super(message);
    this.name = "CommandError";
    this.command = command;
    this.result = result;
  }
}

function execCommand(command, options = {}) {
  const silent = options.silent ?? false;
  const allowFail = options.allowFail ?? false;

  if (!silent) {
    log(dim(`$ ${command}`));
  }

  const result = shell.exec(command, { silent });

  // Capture stdout/stderr into log buffer
  if (result.stdout && result.stdout.trim()) {
    for (const line of result.stdout.trim().split("\n")) {
      pushLog("info", line);
    }
  }
  if (result.stderr && result.stderr.trim()) {
    for (const line of result.stderr.trim().split("\n")) {
      pushLog(result.code !== 0 ? "error" : "warn", line);
    }
  }

  if (result.code !== 0 && !allowFail) {
    throw new CommandError(`Command failed: ${command}`, command, result);
  }
  return result;
}

function capture(command) {
  const result = shell.exec(command, { silent: true });
  return {
    code: result.code,
    stdout: (result.stdout || "").trim(),
    stderr: (result.stderr || "").trim(),
  };
}

function getCurrentBranchSafe() {
  const result = capture("git rev-parse --abbrev-ref HEAD");
  if (result.code !== 0 || !result.stdout) {
    return "Unknown branch";
  }
  return result.stdout;
}

function getCurrentLocationSafe() {
  const env = loadEnv(getEnvPath());
  return env.WORK_LOCATION || "unknown";
}

function getEnvPath() {
  return path.join(process.cwd(), ".env");
}

function loadEnv(envPath) {
  if (!fs.existsSync(envPath)) {
    return {};
  }

  const env = {};
  const content = fs.readFileSync(envPath, "utf8");
  for (const rawLine of content.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith("#")) {
      continue;
    }
    const eqIndex = line.indexOf("=");
    if (eqIndex === -1) {
      continue;
    }
    const key = line.slice(0, eqIndex).trim();
    const value = line.slice(eqIndex + 1).trim();
    env[key] = value;
  }
  return env;
}

function updateEnvValue(envPath, key, value) {
  const lines = fs.existsSync(envPath)
    ? fs.readFileSync(envPath, "utf8").split(/\r?\n/)
    : [];
  let updated = false;
  const nextLines = lines.map((line) => {
    if (!line.trim().startsWith(`${key}=`)) {
      return line;
    }
    updated = true;
    return `${key}=${value}`;
  });

  if (!updated) {
    nextLines.push(`${key}=${value}`);
  }

  const output = nextLines
    .filter((line, index, array) => !(index === array.length - 1 && line === ""))
    .join("\n");

  fs.writeFileSync(envPath, `${output}\n`, "utf8");
}

function getLocationConfig() {
  const env = loadEnv(getEnvPath());
  return {
    workLocation: env.WORK_LOCATION || "home",
    branchHome: env.BRANCH_HOME || "home",
    branchOffice: env.BRANCH_OFFICE || "office",
    repoUrl: env.GITHUB_REPO_URL || "",
  };
}

function resolveBranchFromEnv() {
  const config = getLocationConfig();
  return config.workLocation === "office" ? config.branchOffice : config.branchHome;
}

function ensureGitAvailable() {
  if (!shell.which("git")) {
    fatal("git is not installed or not in PATH.");
  }
}

function ensureGitRepo() {
  const result = capture("git rev-parse --is-inside-work-tree");
  if (result.code !== 0 || result.stdout !== "true") {
    throw new Error("Current directory is not a git repository.");
  }
}

function hasUncommittedChanges() {
  ensureGitRepo();
  return capture("git status --porcelain").stdout !== "";
}

function branchExists(branch) {
  const local = capture(`git show-ref --verify --quiet refs/heads/${branch}`);
  if (local.code === 0) {
    return true;
  }
  const remote = capture(`git show-ref --verify --quiet refs/remotes/origin/${branch}`);
  return remote.code === 0;
}

function remoteBranchExists(branch) {
  const result = capture(`git ls-remote --heads origin ${quote(branch)}`);
  return result.code === 0 && result.stdout !== "";
}

function getPushTarget(positionals) {
  const envBranch = resolveBranchFromEnv();
  if (positionals.length === 0) {
    return { branch: envBranch, commitMessage: generateCommitMessage() };
  }

  if (positionals.length === 1) {
    const first = positionals[0];
    if (branchExists(first)) {
      return { branch: first, commitMessage: generateCommitMessage() };
    }
    return { branch: envBranch, commitMessage: first };
  }

  return {
    branch: positionals[0],
    commitMessage: positionals[1],
  };
}

function generateCommitMessage() {
  const staged = capture("git diff --cached --stat").stdout;
  const working = capture("git diff --stat").stdout;
  const stat = staged || working;
  const match = stat.match(/(\d+) file/);
  const fileCount = match ? match[1] : "?";
  const timestamp = new Date().toISOString().slice(0, 16).replace("T", " ");
  return `chore: auto commit (${fileCount} file(s) changed) [${timestamp}]`;
}

function printHeader(title) {
  printSpacer();
  printPanel(title, [], c.blue);
}

function printHelp() {
  console.log(`
${bold("Git Terminal")} - single entrypoint for git helper, location manager, and smart clone

${bold("USAGE")}
  node git/git-terminal.cjs
  node git/git-terminal.cjs <action> [options]
  node git/git-terminal.cjs location <setup|switch|status|sync> [args]
  node git/git-terminal.cjs smart-clone [repo-url] [dir]

${bold("DIRECT ACTIONS")}
  clone (cl)
  pull
  push
  merge (mg)
  branch-create (new)
  branch-delete (del)
  branch-list (brnh)
  checkout (co)
  status (st)
  log (lg)
  sync
  tag
  undo (un)
  stash (sh)
  clean (cn)
  diff (df)
  smart-clone

${bold("LOCATION ACTIONS")}
  location setup
  location switch <home|office>
  location status
  location sync

${bold("INTERACTIVE MENU")}
  Run without arguments to open the menu.
  Menu selection accepts number or command name.
  Confirmation uses Enter to run, Esc to cancel.
`);
}

function parseArgs(argv) {
  const positional = [];
  const flags = {};

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (!arg.startsWith("--")) {
      positional.push(arg);
      continue;
    }

    const key = arg.slice(2);
    const next = argv[i + 1];
    if (next && !next.startsWith("--")) {
      flags[key] = next;
      i += 1;
    } else {
      flags[key] = true;
    }
  }

  return { positional, flags };
}

async function actionClone(input) {
  let repoUrl = input.positional[0];
  const dir = input.positional[1];

  if (!repoUrl) {
    const config = getLocationConfig();
    repoUrl = config.repoUrl;
  }

  if (!repoUrl) {
    throw new Error("Repository URL is required or set GITHUB_REPO_URL in .env.");
  }

  const command = dir
    ? `git clone ${quote(repoUrl)} ${quote(dir)}`
    : `git clone ${quote(repoUrl)}`;

  printHeader(`Clone - ${repoUrl}`);
  execCommand(command);
  ok("Repository cloned successfully.");
}

async function actionPull(input) {
  ensureGitRepo();
  const branch = input.positional[0] || resolveBranchFromEnv();
  printHeader(`Pull - ${branch}`);

  const stashed = hasUncommittedChanges();
  if (stashed) {
    log("Local changes detected. Creating auto stash.");
    execCommand("git stash push -m 'pre-pull auto-stash'");
  }

  execCommand("git fetch origin");
  execCommand(`git pull origin ${quote(branch)}`);

  if (stashed) {
    const popResult = execCommand("git stash pop", { allowFail: true });
    if (popResult.code !== 0) {
      warn("Stash pop needs manual conflict resolution.");
    }
  }

  ok(`Pulled latest changes from ${branch}.`);
}

async function actionPush(input) {
  ensureGitRepo();
  const target = getPushTarget(input.positional);
  const branch = target.branch;
  const commitMessage = target.commitMessage || generateCommitMessage();
  const force = Boolean(input.flags.force);

  printHeader(`Push - ${branch}`);

  if (!hasUncommittedChanges()) {
    warn("Working tree is clean. Nothing new to commit.");
  } else {
    execCommand("git add .");
    const stat = capture("git diff --cached --stat").stdout;
    if (stat) {
      console.log(`\n${dim(stat)}\n`);
    }
    execCommand(`git commit -m ${quote(commitMessage)}`);
  }

  const forceFlag = force ? " --force-with-lease" : "";
  if (force) {
    warn("Force mode enabled: --force-with-lease");
  }
  execCommand(`git push origin ${quote(branch)}${forceFlag}`);
  ok(`Pushed to origin/${branch}.`);
}

async function actionMerge(input) {
  ensureGitRepo();
  const source = input.positional[0];
  const target = input.positional[1];
  const squash = Boolean(input.flags.squash);

  if (!source || !target) {
    throw new Error("Usage: merge <source> <target> [--squash]");
  }

  const previousBranch = getCurrentBranchSafe();
  printHeader(`Merge - ${source} -> ${target}`);

  execCommand(`git checkout ${quote(target)}`);
  execCommand(`git pull origin ${quote(target)}`);
  execCommand(`git merge ${quote(source)}${squash ? " --squash" : ""}`);

  const conflicts = capture("git ls-files -u").stdout;
  if (conflicts) {
    throw new Error("Merge conflict detected. Resolve conflicts manually.");
  }

  if (squash) {
    execCommand(`git commit -m ${quote(`chore: squash merge ${source} into ${target}`)}`);
  }

  if (previousBranch !== target) {
    execCommand(`git checkout ${quote(previousBranch)}`);
  }

  ok(`Merged ${source} into ${target}.`);
}

async function actionBranchCreate(input) {
  ensureGitRepo();
  const branch = input.positional[0];
  const base = input.flags.from || getCurrentBranchSafe();
  const shouldPush = Boolean(input.flags.push);

  if (!branch) {
    throw new Error("Usage: branch-create <branch> [--from <base>] [--push]");
  }

  printHeader(`Branch Create - ${branch}`);
  execCommand(`git checkout ${quote(base)}`);
  execCommand(`git pull origin ${quote(base)}`, { allowFail: true });
  execCommand(`git checkout -b ${quote(branch)}`);

  if (shouldPush) {
    execCommand(`git push -u origin ${quote(branch)}`);
  }

  ok(shouldPush ? `Branch ${branch} created and pushed.` : `Branch ${branch} created.`);
}

async function actionBranchDelete(input) {
  ensureGitRepo();
  const branch = input.positional[0];
  const deleteRemote = Boolean(input.flags.remote);

  if (!branch) {
    throw new Error("Usage: branch-delete <branch> [--remote]");
  }

  if (branch === getCurrentBranchSafe()) {
    throw new Error("Cannot delete the currently checked out branch.");
  }

  printHeader(`Branch Delete - ${branch}`);

  if (branchExists(branch)) {
    execCommand(`git branch -D ${quote(branch)}`);
  } else {
    warn(`Local branch ${branch} not found.`);
  }

  if (deleteRemote) {
    if (!remoteBranchExists(branch)) {
      warn(`Remote branch origin/${branch} not found.`);
    } else {
      execCommand(`git push origin --delete ${quote(branch)}`);
    }
  }

  ok(`Branch delete flow completed for ${branch}.`);
}

async function actionBranchList(input) {
  ensureGitRepo();
  printHeader(Boolean(input.flags.remote) ? "Remote Branches" : "Local Branches");
  execCommand(Boolean(input.flags.remote) ? "git branch -r" : "git branch -v");
}

async function actionCheckout(input) {
  ensureGitRepo();
  const branch = input.positional[0];
  if (!branch) {
    throw new Error("Usage: checkout <branch>");
  }

  printHeader(`Checkout - ${branch}`);
  const dirty = hasUncommittedChanges();
  if (dirty) {
    warn("Uncommitted changes found. Auto stash in progress.");
    execCommand("git stash push -m 'auto-stash before checkout'");
  }

  execCommand(`git checkout ${quote(branch)}`);
  if (dirty) {
    const popResult = execCommand("git stash pop", { allowFail: true });
    if (popResult.code !== 0) {
      warn("Stash pop needs manual conflict resolution.");
    }
  }
  ok(`Now on branch ${branch}.`);
}

async function actionStatus() {
  ensureGitRepo();
  const branch = getCurrentBranchSafe();
  const lines = [
    `${bold("Branch")}   ${branch}`,
    `${bold("Location")} ${getCurrentLocationSafe()}`,
  ];

  const remoteResult = capture("git remote");
  if (remoteResult.stdout.includes("origin")) {
    const ahead = capture(`git rev-list --count origin/${quote(branch)}..HEAD`);
    const behind = capture(`git rev-list --count HEAD..origin/${quote(branch)}`);
    lines.push(`${bold("Ahead")}    ${ahead.code === 0 ? ahead.stdout || "0" : "?"}`);
    lines.push(`${bold("Behind")}   ${behind.code === 0 ? behind.stdout || "0" : "?"}`);
  }
  printPanel("Repository Status", lines, c.blue);

  const status = capture("git status --short").stdout;
  if (!status) {
    ok("Working tree is clean.");
  } else {
    printPanel("Working Tree Changes", status.split("\n"), c.yellow);
  }

  const stashList = capture("git stash list").stdout;
  if (stashList) {
    printPanel("Stashes", stashList.split("\n"), c.cyan);
  }
}

async function actionLog(input) {
  ensureGitRepo();
  const count = parseInt(input.flags.n || "10", 10);
  printHeader(`Recent Commits (${count})`);
  execCommand(`git log --oneline --graph -n ${count}`);
}

async function actionSync(input) {
  ensureGitRepo();
  const base = input.positional[0];
  if (!base) {
    throw new Error("Usage: sync <base-branch>");
  }

  const branch = getCurrentBranchSafe();
  printHeader(`Sync - ${branch} onto ${base}`);
  execCommand(`git fetch origin ${quote(base)}`);
  execCommand(`git rebase origin/${quote(base)}`);
  ok(`${branch} synced with ${base}.`);
}

async function actionTag(input) {
  ensureGitRepo();
  const name = input.positional[0];
  const pushTag = Boolean(input.flags.push);
  const message = input.positional[1] || name;

  if (!name) {
    throw new Error("Usage: tag <name> [message] [--push]");
  }

  printHeader(`Tag - ${name}`);
  execCommand(`git tag -a ${quote(name)} -m ${quote(message)}`);
  if (pushTag) {
    execCommand(`git push origin ${quote(name)}`);
  }
  ok(pushTag ? `Tag ${name} created and pushed.` : `Tag ${name} created.`);
}

async function actionUndo(input) {
  ensureGitRepo();
  const hard = Boolean(input.flags.hard);
  printHeader(`Undo Last Commit${hard ? " - HARD" : ""}`);
  execCommand(hard ? "git reset --hard HEAD~1" : "git reset --soft HEAD~1");
  ok("Last commit undone.");
}

async function actionStash(input) {
  ensureGitRepo();
  const sub = input.positional[0];
  const name = input.positional[1];

  if (!sub) {
    throw new Error("Usage: stash <save|pop|list|drop> [name]");
  }

  printHeader(`Stash - ${sub}`);
  switch (sub) {
    case "save":
      execCommand(`git stash push -m ${quote(name || "manual stash")}`);
      break;
    case "pop":
      execCommand("git stash pop");
      break;
    case "list":
      console.log(capture("git stash list").stdout || "No stashes.");
      break;
    case "drop":
      execCommand(name ? `git stash drop ${quote(name)}` : "git stash drop");
      break;
    default:
      throw new Error("Unknown stash action. Use save, pop, list, or drop.");
  }

  ok(`Stash command '${sub}' completed.`);
}

async function actionClean() {
  ensureGitRepo();
  printHeader("Clean Untracked Files");
  const preview = capture("git clean -nd").stdout;
  if (!preview) {
    ok("Nothing to clean.");
    return;
  }

  console.log(preview);
  execCommand("git clean -fd");
  ok("Untracked files removed.");
}

async function actionDiff(input) {
  ensureGitRepo();
  printHeader(input.positional[0] ? `Diff vs ${input.positional[0]}` : "Working Tree Diff");
  execCommand(input.positional[0] ? `git diff ${quote(input.positional[0])}` : "git diff");
}

async function actionLocationSetup() {
  ensureGitRepo();
  const envPath = getEnvPath();
  const config = getLocationConfig();
  printHeader("Location Setup");

  execCommand(`git checkout -b ${quote(config.branchHome)}`, { allowFail: true });
  execCommand(`git checkout ${quote(config.branchHome)}`, { allowFail: true });
  execCommand(`git checkout -b ${quote(config.branchOffice)}`, { allowFail: true });
  execCommand(`git checkout ${quote(config.branchOffice)}`, { allowFail: true });
  execCommand(`git checkout ${quote(resolveBranchFromEnv())}`, { allowFail: true });

  updateEnvValue(envPath, "WORK_LOCATION", config.workLocation);
  updateEnvValue(envPath, "BRANCH_HOME", config.branchHome);
  updateEnvValue(envPath, "BRANCH_OFFICE", config.branchOffice);
  ok("Location branches are ready.");
}

async function actionLocationSwitch(input) {
  ensureGitRepo();
  const location = input.positional[0];
  if (!["home", "office"].includes(location)) {
    throw new Error("Usage: location switch <home|office>");
  }

  const envPath = getEnvPath();
  const config = getLocationConfig();
  const branch = location === "home" ? config.branchHome : config.branchOffice;

  printHeader(`Location Switch - ${location}`);

  if (hasUncommittedChanges()) {
    warn("Uncommitted changes found. Auto stash in progress.");
    execCommand("git stash push -m 'auto-stash before location switch'");
  }

  execCommand("git fetch origin");
  execCommand(`git checkout ${quote(branch)}`);
  updateEnvValue(envPath, "WORK_LOCATION", location);
  ok(`Switched to ${location} using branch ${branch}.`);
}

async function actionLocationStatus() {
  ensureGitRepo();
  const config = getLocationConfig();
  printPanel(
    "Location Status",
    [
      `${bold("Current location")} ${config.workLocation}`,
      `${bold("Current branch")}   ${getCurrentBranchSafe()}`,
      `${bold("Home branch")}      ${config.branchHome}`,
      `${bold("Office branch")}    ${config.branchOffice}`,
    ],
    c.blue
  );
}

async function actionLocationSync() {
  ensureGitRepo();
  const branch = resolveBranchFromEnv();
  printHeader(`Location Sync - ${branch}`);
  if (getCurrentBranchSafe() !== branch) {
    execCommand(`git checkout ${quote(branch)}`);
  }
  execCommand(`git pull origin ${quote(branch)}`, { allowFail: true });
  ok(`Location sync completed for ${branch}.`);
}

async function actionSmartClone(input) {
  let repoUrl = input.positional[0];
  const clonePath = input.positional[1];
  const config = getLocationConfig();

  if (!repoUrl) {
    repoUrl = config.repoUrl;
  }

  if (!repoUrl) {
    throw new Error("Repository URL is required or set GITHUB_REPO_URL in .env.");
  }

  const branchToClone =
    config.workLocation === "office" ? config.branchHome : config.branchOffice;
  const dirName = clonePath || path.basename(repoUrl).replace(/\.git$/, "");

  printHeader("Smart Clone");
  console.log(`Current location : ${bold(config.workLocation)}`);
  console.log(`Clone branch     : ${bold(branchToClone)}`);
  console.log(`Directory        : ${bold(dirName)}\n`);

  if (fs.existsSync(dirName)) {
    throw new Error(`Directory already exists: ${dirName}`);
  }

  execCommand(
    `git clone --branch ${quote(branchToClone)} ${quote(repoUrl)} ${quote(dirName)}`
  );

  const previousDir = process.cwd();
  process.chdir(dirName);

  try {
    if (fs.existsSync("composer.json")) {
      execCommand("composer install", { allowFail: true });
    }
    if (fs.existsSync("package.json")) {
      execCommand("npm install");
    }
    if (!fs.existsSync(".env") && fs.existsSync(".env.example")) {
      shell.cp(".env.example", ".env");
      ok(".env created from .env.example.");
    }
  } finally {
    process.chdir(previousDir);
  }

  ok(`Smart clone completed into ${dirName}.`);
}

const DIRECT_ACTIONS = {
  clone: actionClone,
  cl: actionClone,
  pull: actionPull,
  push: actionPush,
  merge: actionMerge,
  mg: actionMerge,
  "branch-create": actionBranchCreate,
  new: actionBranchCreate,
  "branch-delete": actionBranchDelete,
  del: actionBranchDelete,
  "branch-list": actionBranchList,
  brnh: actionBranchList,
  checkout: actionCheckout,
  co: actionCheckout,
  status: actionStatus,
  st: actionStatus,
  log: actionLog,
  lg: actionLog,
  sync: actionSync,
  tag: actionTag,
  undo: actionUndo,
  un: actionUndo,
  stash: actionStash,
  sh: actionStash,
  clean: actionClean,
  cn: actionClean,
  diff: actionDiff,
  df: actionDiff,
  "smart-clone": actionSmartClone,
};

async function runDirect(argv) {
  const raw = argv.slice(2);
  if (raw.length === 0) {
    await runInteractiveMenu();
    return;
  }

  if (raw[0] === "--help" || raw[0] === "-h") {
    printHelp();
    return;
  }

  if (raw[0] === "location") {
    const sub = raw[1];
    if (!sub) {
      throw new Error("Usage: location <setup|switch|status|sync> [args]");
    }
    const parsed = parseArgs(raw.slice(2));
    switch (sub) {
      case "setup":
        await actionLocationSetup(parsed);
        return;
      case "switch":
        await actionLocationSwitch(parsed);
        return;
      case "status":
        await actionLocationStatus(parsed);
        return;
      case "sync":
        await actionLocationSync(parsed);
        return;
      default:
        throw new Error(`Unknown location action: ${sub}`);
    }
  }

  const action = DIRECT_ACTIONS[raw[0].toLowerCase()];
  if (!action) {
    throw new Error(`Unknown action: ${raw[0]}`);
  }

  const parsed = parseArgs(raw.slice(1));
  await action(parsed);
}

function createInterface() {
  return readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });
}

function promptLine(rl, message) {
  return new Promise((resolve) => {
    rl.question(message, (answer) => resolve(answer.trim()));
  });
}

function isNavigationToken(value) {
  return value === NAV_BACK || value === NAV_MAIN;
}

function parseNavigationInput(value) {
  const normalized = normalizeText(String(value || ""));
  if (normalized === "back" || normalized === "b") {
    return NAV_BACK;
  }
  if (normalized === "main" || normalized === "menu" || normalized === "main menu" || normalized === "m") {
    return NAV_MAIN;
  }
  return null;
}

function appendPromptHints(message) {
  return `${message} ${dim("(type 'back' or 'main')")}`;
}

function promptMenuSelection(rl, message, immediateKeys = []) {
  if (!process.stdin.isTTY) {
    return promptLine(rl, message);
  }

  return new Promise((resolve) => {
    const stdin = process.stdin;
    let buffer = "";
    process.stdout.write(message);

    const onData = (chunk) => {
      const key = chunk.toString("utf8");

      if (key === "\u0003") {
        cleanup();
        process.exit(130);
      }

      if ((key === "\r" || key === "\n")) {
        process.stdout.write("\n");
        cleanup();
        resolve(buffer.trim());
        return;
      }

      if (key === "\u0008" || key === "\u007f") {
        if (buffer.length > 0) {
          buffer = buffer.slice(0, -1);
          process.stdout.write("\b \b");
        }
        return;
      }

      if (buffer.length === 0 && immediateKeys.includes(key)) {
        process.stdout.write(`${key}\n`);
        cleanup();
        resolve(key);
        return;
      }

      if (key >= " " && key !== "\u001b") {
        buffer += key;
        process.stdout.write(key);
      }
    };

    const cleanup = () => {
      stdin.removeListener("data", onData);
      if (stdin.isTTY) {
        stdin.setRawMode(false);
      }
      stdin.pause();
    };

    stdin.resume();
    stdin.setEncoding("utf8");
    stdin.setRawMode(true);
    stdin.on("data", onData);
  });
}

async function waitForEnter(rl, message) {
  if (!process.stdin.isTTY) {
    return;
  }
  await promptLine(rl, message);
}

function waitForConfirmKey() {
  if (!process.stdin.isTTY) {
    return Promise.resolve("confirm");
  }

  return new Promise((resolve) => {
    const stdin = process.stdin;
    const onData = (buffer) => {
      const key = buffer.toString("utf8");
      if (key === "\r" || key === "\n") {
        cleanup();
        resolve("confirm");
        return;
      }
      if (key === "\u001b") {
        cleanup();
        resolve("back");
        return;
      }
      if (key === "m" || key === "M") {
        cleanup();
        resolve("main");
        return;
      }
      if (key === "\u0003") {
        cleanup();
        process.exit(130);
      }
    };

    const cleanup = () => {
      stdin.removeListener("data", onData);
      if (stdin.isTTY) {
        stdin.setRawMode(false);
      }
      stdin.pause();
    };

    stdin.resume();
    stdin.setEncoding("utf8");
    stdin.setRawMode(true);
    stdin.on("data", onData);
  });
}

function clearScreen() {
  if (process.stdout.isTTY) {
    process.stdout.write("\x1Bc");
  }
}

function renderShellHeader(subtitle) {
  clearScreen();
  const currentBranch = getCurrentBranchSafe();
  const currentLocation = getCurrentLocationSafe();
  printPanel(
    "BroxBhai Git Terminal",
    [
      `${bold("Current Branch")}  ${currentBranch}`,
      `${bold("Work Location")}   ${currentLocation}`,
      `${bold("Mode")}            interactive menu + direct commands`,
      `${bold("Step")}            ${subtitle}`,
    ],
    c.magenta
  );
  printSpacer();
}

function renderCategoryMenu(errorMessage) {
  renderShellHeader("1/2 category selection");

  if (errorMessage) {
    printPanel("Input Error", [errorMessage], c.red);
    printSpacer();
  }

  const categoryRows = SECTION_ORDER.map((section, index) => {
    return `${colorize(c.cyan, `[${index + 1}]`)} ${SECTION_TITLES[section]} ${dim("- " + SECTION_DETAILS[section])}`;
  });

  printListBlock("Choose Category", categoryRows, c.white);

  printListBlock("Exit", [`${colorize(c.yellow, "[ 0]")} Exit ${dim("close the terminal menu")}`], c.yellow);
  printPromptPanel(
    "Input",
    "Select category by number or name. Single-digit numbers run instantly.",
    c.green
  );
}

function renderSectionMenu(section, errorMessage) {
  renderShellHeader(`2/2 action selection -> ${SECTION_TITLES[section]}`);

  if (errorMessage) {
    printPanel("Input Error", [errorMessage], c.red);
    printSpacer();
  }

  const sectionOptions = MENU_OPTIONS.filter((item) => item.section === section);
  const rows = sectionOptions.map((option, index) => {
    return `${colorize(c.cyan, `[${index + 1}]`)} ${option.label.padEnd(20, " ")} ${dim(option.commandName)}${option.description ? ` ${dim("- " + option.description)}` : ""}`;
  });

  printListBlock(SECTION_TITLES[section], rows, c.white);
  printListBlock(
    "Navigation",
    [
      `${colorize(c.yellow, "[ 0]")} Back ${dim("return to category list")}`,
      `${colorize(c.yellow, "[ m]")} Main Menu ${dim("return to category list")}`,
      `${colorize(c.yellow, "[ x]")} Exit ${dim("close the terminal menu")}`,
    ],
    c.yellow
  );
  printPromptPanel(
    "Input",
    "Select action by number or name. Single-digit numbers run instantly.",
    c.green
  );
}

function normalizeText(value) {
  return value.trim().toLowerCase();
}

function findCategoryOption(input) {
  const normalized = normalizeText(input);
  if (!normalized) {
    return { error: "Empty selection." };
  }

  if (normalized === "0" || normalized === "exit" || normalized === "quit" || normalized === "x") {
    return { exit: true };
  }

  const sectionByNumber = SECTION_ORDER[Number(normalized) - 1];
  if (sectionByNumber) {
    return { section: sectionByNumber };
  }

  const exact = SECTION_ORDER.filter((section) => {
    return (
      normalizeText(section) === normalized ||
      normalizeText(SECTION_TITLES[section]) === normalized
    );
  });

  if (exact.length === 1) {
    return { section: exact[0] };
  }

  const partial = SECTION_ORDER.filter((section) =>
    normalizeText(`${section} ${SECTION_TITLES[section]} ${SECTION_DETAILS[section]}`).includes(normalized)
  );

  if (partial.length === 1) {
    return { section: partial[0] };
  }

  if (partial.length > 1 || exact.length > 1) {
    return {
      error: `Ambiguous category. Choices: ${SECTION_ORDER.join(", ")}`,
    };
  }

  return { error: `Unknown category: ${input}` };
}

function findSectionOption(section, input) {
  const normalized = normalizeText(input);
  const sectionOptions = MENU_OPTIONS.filter((item) => item.section === section);
  if (!normalized) {
    return { error: "Empty selection." };
  }

  if (normalized === "0" || normalized === "back") {
    return { back: true };
  }

  if (normalized === "m" || normalized === "main" || normalized === "menu") {
    return { main: true };
  }

  if (normalized === "x" || normalized === "exit" || normalized === "quit") {
    return { exit: true };
  }

  const byNumber = sectionOptions[Number(normalized) - 1];
  if (byNumber) {
    return { option: byNumber };
  }

  const exact = sectionOptions.filter((item) =>
    item.tokens.some((token) => normalizeText(token) === normalized) ||
    normalizeText(item.label) === normalized ||
    normalizeText(item.commandName) === normalized
  );
  if (exact.length === 1) {
    return { option: exact[0] };
  }
  if (exact.length > 1) {
    return {
      error: `Ambiguous input. Choices: ${exact.map((item) => item.commandName).join(", ")}`,
    };
  }

  const partial = sectionOptions.filter((item) =>
    item.tokens.some((token) => normalizeText(token).includes(normalized))
  );

  if (partial.length === 1) {
    return { option: partial[0] };
  }
  if (partial.length > 1) {
    return {
      error: `Ambiguous input. Choices: ${partial.map((item) => item.commandName).join(", ")}`,
    };
  }

  return { error: `Unknown option: ${input}` };
}

async function askValue(rl, label, options = {}) {
  const defaultValue = options.defaultValue ?? "";
  const optional = options.optional ?? false;

  while (true) {
    const suffix = defaultValue ? ` [${defaultValue}]` : "";
    const raw = await promptLine(rl, `${appendPromptHints(label)}${suffix}: `);
    const nav = parseNavigationInput(raw);
    if (nav) {
      return nav;
    }
    if (!raw && defaultValue) {
      return defaultValue;
    }
    if (!raw && optional) {
      return "";
    }
    if (raw) {
      return raw;
    }
    console.log(colorize(c.red, "Value required."));
  }
}

async function askYesNo(rl, label, defaultYes = false) {
  const defaultText = defaultYes ? "Y/n" : "y/N";
  while (true) {
    const raw = await promptLine(rl, `${appendPromptHints(label)} [${defaultText}]: `);
    const nav = parseNavigationInput(raw);
    if (nav) {
      return nav;
    }
    const answer = normalizeText(raw);
    if (!answer) {
      return defaultYes;
    }
    if (["y", "yes"].includes(answer)) {
      return true;
    }
    if (["n", "no"].includes(answer)) {
      return false;
    }
    console.log(colorize(c.red, "Enter y or n."));
  }
}

function summarizeInteractiveInput(option, parsed) {
  const summary = [];
  if (option.commandName === "push") {
    const target = getPushTarget(parsed.positional);
    summary.push(`Branch: ${target.branch}`);
    summary.push(`Message: ${target.commitMessage}`);
    if (parsed.flags.force) {
      summary.push("Force: yes");
    }
  } else if (option.commandName === "location switch") {
    summary.push(`Location: ${parsed.positional[0]}`);
  } else if (option.commandName === "branch-delete") {
    summary.push(`Branch: ${parsed.positional[0]}`);
    summary.push(`Remote delete: ${parsed.flags.remote ? "yes" : "no"}`);
  } else if (option.commandName === "branch-list") {
    summary.push(`Remote list: ${parsed.flags.remote ? "yes" : "no"}`);
  } else if (option.commandName === "branch-create") {
    summary.push(`Branch: ${parsed.positional[0]}`);
    if (parsed.flags.from) {
      summary.push(`Base: ${parsed.flags.from}`);
    }
    summary.push(`Push now: ${parsed.flags.push ? "yes" : "no"}`);
  } else if (option.commandName === "merge") {
    summary.push(`Source: ${parsed.positional[0]}`);
    summary.push(`Target: ${parsed.positional[1]}`);
    summary.push(`Squash: ${parsed.flags.squash ? "yes" : "no"}`);
  } else if (option.commandName === "tag") {
    summary.push(`Tag: ${parsed.positional[0]}`);
    summary.push(`Message: ${parsed.positional[1] || parsed.positional[0]}`);
    summary.push(`Push tag: ${parsed.flags.push ? "yes" : "no"}`);
  } else if (option.commandName === "undo") {
    summary.push(`Hard reset: ${parsed.flags.hard ? "yes" : "no"}`);
  } else if (option.commandName === "stash") {
    summary.push(`Action: ${parsed.positional[0]}`);
    if (parsed.positional[1]) {
      summary.push(`Name: ${parsed.positional[1]}`);
    }
  } else if (option.commandName === "smart-clone") {
    summary.push(`Repo URL: ${parsed.positional[0] || getLocationConfig().repoUrl || "(from .env)"}`);
    if (parsed.positional[1]) {
      summary.push(`Directory: ${parsed.positional[1]}`);
    }
  } else if (parsed.positional.length || Object.keys(parsed.flags).length) {
    if (parsed.positional.length) {
      summary.push(`Args: ${parsed.positional.join(", ")}`);
    }
    const flagKeys = Object.keys(parsed.flags);
    if (flagKeys.length) {
      summary.push(`Flags: ${flagKeys.join(", ")}`);
    }
  } else {
    summary.push("No extra input");
  }
  return summary;
}

async function collectInputForOption(rl, option) {
  switch (option.commandName) {
    case "status":
    case "location status":
    case "location setup":
    case "location sync":
    case "clean":
      return { positional: [], flags: {} };
    case "pull": {
      const branch = await askValue(rl, "Branch (blank = auto from .env)", { optional: true });
      if (isNavigationToken(branch)) {
        return branch;
      }
      return { positional: branch ? [branch] : [], flags: {} };
    }
    case "push": {
      const branch = await askValue(rl, "Branch (blank = auto from .env)", { optional: true });
      if (isNavigationToken(branch)) {
        return branch;
      }
      const message = await askValue(rl, "Commit message (blank = auto message)", { optional: true });
      if (isNavigationToken(message)) {
        return message;
      }
      const positional = [];
      if (branch) {
        positional.push(branch);
      }
      if (message) {
        positional.push(message);
      }
      return { positional, flags: {} };
    }
    case "merge": {
      const source = await askValue(rl, "Source branch");
      if (isNavigationToken(source)) {
        return source;
      }
      const target = await askValue(rl, "Target branch");
      if (isNavigationToken(target)) {
        return target;
      }
      const squash = await askYesNo(rl, "Squash merge?", false);
      if (isNavigationToken(squash)) {
        return squash;
      }
      return { positional: [source, target], flags: squash ? { squash: true } : {} };
    }
    case "branch-create": {
      const branch = await askValue(rl, "New branch name");
      if (isNavigationToken(branch)) {
        return branch;
      }
      const from = await askValue(rl, "Base branch", {
        defaultValue: getCurrentBranchSafe(),
      });
      if (isNavigationToken(from)) {
        return from;
      }
      const pushNow = await askYesNo(rl, "Push branch to origin?", false);
      if (isNavigationToken(pushNow)) {
        return pushNow;
      }
      return {
        positional: [branch],
        flags: {
          ...(from ? { from } : {}),
          ...(pushNow ? { push: true } : {}),
        },
      };
    }
    case "branch-delete": {
      const branch = await askValue(rl, "Branch to delete");
      if (isNavigationToken(branch)) {
        return branch;
      }
      const remote = await askYesNo(rl, "Delete remote branch too?", false);
      if (isNavigationToken(remote)) {
        return remote;
      }
      return {
        positional: [branch],
        flags: remote ? { remote: true } : {},
      };
    }
    case "branch-list": {
      const remote = await askYesNo(rl, "List remote branches?", false);
      if (isNavigationToken(remote)) {
        return remote;
      }
      return {
        positional: [],
        flags: remote ? { remote: true } : {},
      };
    }
    case "checkout": {
      const branch = await askValue(rl, "Branch to checkout");
      if (isNavigationToken(branch)) {
        return branch;
      }
      return { positional: [branch], flags: {} };
    }
    case "log": {
      const count = await askValue(rl, "Number of commits", { defaultValue: "10" });
      if (isNavigationToken(count)) {
        return count;
      }
      return { positional: [], flags: { n: count } };
    }
    case "sync": {
      const base = await askValue(rl, "Base branch");
      if (isNavigationToken(base)) {
        return base;
      }
      return { positional: [base], flags: {} };
    }
    case "tag": {
      const name = await askValue(rl, "Tag name");
      if (isNavigationToken(name)) {
        return name;
      }
      const message = await askValue(rl, "Tag message", { defaultValue: name });
      if (isNavigationToken(message)) {
        return message;
      }
      const pushTag = await askYesNo(rl, "Push tag now?", false);
      if (isNavigationToken(pushTag)) {
        return pushTag;
      }
      return {
        positional: [name, message],
        flags: pushTag ? { push: true } : {},
      };
    }
    case "undo": {
      const hard = await askYesNo(rl, "Hard reset?", false);
      if (isNavigationToken(hard)) {
        return hard;
      }
      return { positional: [], flags: hard ? { hard: true } : {} };
    }
    case "stash": {
      const sub = await askValue(rl, "Stash action (save/pop/list/drop)");
      if (isNavigationToken(sub)) {
        return sub;
      }
      const needsName = ["save", "drop"].includes(normalizeText(sub));
      const name = needsName
        ? await askValue(rl, "Name", { optional: true })
        : "";
      if (isNavigationToken(name)) {
        return name;
      }
      return { positional: name ? [sub, name] : [sub], flags: {} };
    }
    case "diff": {
      const branch = await askValue(rl, "Compare branch (blank = working tree)", { optional: true });
      if (isNavigationToken(branch)) {
        return branch;
      }
      return { positional: branch ? [branch] : [], flags: {} };
    }
    case "clone": {
      const repoUrl = await askValue(rl, "Repository URL", {
        defaultValue: getLocationConfig().repoUrl,
      });
      if (isNavigationToken(repoUrl)) {
        return repoUrl;
      }
      const dir = await askValue(rl, "Target directory", { optional: true });
      if (isNavigationToken(dir)) {
        return dir;
      }
      return { positional: dir ? [repoUrl, dir] : [repoUrl], flags: {} };
    }
    case "smart-clone": {
      const repoUrl = await askValue(rl, "Repository URL (blank = from .env)", {
        defaultValue: getLocationConfig().repoUrl,
        optional: true,
      });
      if (isNavigationToken(repoUrl)) {
        return repoUrl;
      }
      const dir = await askValue(rl, "Target directory", { optional: true });
      if (isNavigationToken(dir)) {
        return dir;
      }
      const positional = [];
      if (repoUrl) {
        positional.push(repoUrl);
      }
      if (dir) {
        positional.push(dir);
      }
      return { positional, flags: {} };
    }
    case "location switch": {
      const location = await askValue(rl, "Location (home/office)");
      if (isNavigationToken(location)) {
        return location;
      }
      return { positional: [location], flags: {} };
    }
    default:
      return { positional: [], flags: {} };
  }
}

async function confirmSelection(option, parsed) {
  clearScreen();
  const lines = [
    `${bold("Selected")} ${option.label}`,
    `${bold("Command")}  ${option.commandName}`,
    `${bold("Branch")}   ${getCurrentBranchSafe()}`,
    ...summarizeInteractiveInput(option, parsed).map((line) => `- ${line}`),
  ];
  if (option.destructive) {
    lines.push(colorize(c.yellow, "Warning: this action can modify or remove data."));
  }
  lines.push("");
  lines.push("Navigation:");
  lines.push("- Enter = run action");
  lines.push("- Esc = back");
  lines.push("- M = main menu");
  printPanel("Confirm Action", lines, c.cyan);
  console.log(dim("Enter = run, Esc = back, M = main menu"));
  return waitForConfirmKey();
}

async function promptRecoveryChoice(rl, error, forceAvailable) {
  while (true) {
    const lines = [colorize(c.red, error.message)];
    if (error.command) {
      lines.push(`${bold("Command")} ${error.command}`);
    }
    if (error.result) {
      const detail = (error.result.stderr || error.result.stdout || "").trim();
      if (detail) {
        lines.push("");
        lines.push(detail);
      }
    }
    lines.push("");
    lines.push("1. Retry         (run same command again)");
    lines.push("2. Change Input  (re-enter values)");
    lines.push(`3. Force Push    ${forceAvailable ? "" : dim("(not available for this action)")}`);
    lines.push("4. Back          (return to section menu)");
    lines.push("5. Main Menu     (return to category menu)");
    printPanel("Command Failed", lines, c.red);
    const answer = normalizeText(
      await promptMenuSelection(rl, "Choice: ", ["1", "2", "3", "4", "5"])
    );

    if (answer === "1" || answer === "retry") {
      return "retry";
    }
    if (answer === "2" || answer === "try again" || answer === "try-again") {
      return "try-again";
    }
    if (answer === "3" || answer === "force") {
      if (!forceAvailable) {
        console.log(colorize(c.red, "Force mode is not available for this action."));
        continue;
      }
      return "force";
    }
    if (answer === "4" || answer === "back" || answer === "cancel") {
      return "back";
    }
    if (answer === "5" || answer === "main" || answer === "menu") {
      return "main";
    }

    console.log(colorize(c.red, "Invalid choice."));
  }
}

function makeForceInput(option, parsed) {
  switch (option.commandName) {
    case "push":
      return {
        positional: parsed.positional.slice(),
        flags: { ...parsed.flags, force: true },
      };
    default:
      return null;
  }
}

function supportsForce(option) {
  return option.commandName === "push";
}

async function promptPostActionChoice(rl) {
  const width = 78;
  console.log("");
  console.log(colorize(c.green, `+${makeRule("=", width - 2)}+`));
  console.log(colorize(c.green, `| ${"✓  Action Completed Successfully".padEnd(width - 4, " ")} |`));
  console.log(colorize(c.green, `+${makeRule("-", width - 2)}+`));
  console.log(colorize(c.green, `| ${"What would you like to do next?".padEnd(width - 4, " ")} |`));
  console.log(colorize(c.green, `+${makeRule("=", width - 2)}+`));
  console.log("");
  console.log(`  ${colorize(c.cyan, "[1]")} ${bold("Repeat")}    ${dim("run the same action again")}`);
  console.log(`  ${colorize(c.cyan, "[2]")} ${bold("Back")}      ${dim("return to category/section menu")}`);
  console.log(`  ${colorize(c.cyan, "[3]")} ${bold("Main Menu")} ${dim("go to top-level category menu")}`);
  console.log("");

  while (true) {
    const answer = normalizeText(
      await promptMenuSelection(rl, colorize(c.green, "Choice: "), ["1", "2", "3"])
    );
    if (answer === "1" || answer === "repeat" || answer === "retry") {
      return "repeat";
    }
    if (answer === "2" || answer === "back") {
      return "back";
    }
    if (answer === "3" || answer === "main" || answer === "menu" || answer === "m") {
      return "main";
    }
    console.log(colorize(c.red, "Enter 1, 2, or 3."));
  }
}

async function runInteractiveAction(rl, option) {
  let parsed = await collectInputForOption(rl, option);
  if (parsed === NAV_BACK) {
    return { navigation: "back" };
  }
  if (parsed === NAV_MAIN) {
    return { navigation: "main" };
  }

  while (true) {
    const confirmed = await confirmSelection(option, parsed);
    if (confirmed === "back") {
      return { navigation: "back" };
    }
    if (confirmed === "main") {
      return { navigation: "main" };
    }

    try {
      startLogCapture();
      await option.executor(parsed);
      stopLogCapture();
      await printCollapsibleLogs(rl);
      console.log("");
      ok("Action completed.");
      const postChoice = await promptPostActionChoice(rl);
      if (postChoice === "repeat") {
        continue;
      }
      if (postChoice === "main") {
        return { navigation: "main" };
      }
      return { navigation: "back" };
    } catch (error) {
      stopLogCapture();
      await printCollapsibleLogs(rl);
      const choice = await promptRecoveryChoice(rl, error, supportsForce(option));
      if (choice === "retry") {
        continue;
      }
      if (choice === "try-again") {
        parsed = await collectInputForOption(rl, option);
        if (parsed === NAV_BACK) {
          return { navigation: "back" };
        }
        if (parsed === NAV_MAIN) {
          return { navigation: "main" };
        }
        continue;
      }
      if (choice === "force") {
        const forcedInput = makeForceInput(option, parsed);
        if (!forcedInput) {
          continue;
        }
        try {
          startLogCapture();
          await option.executor(forcedInput);
          stopLogCapture();
          await printCollapsibleLogs(rl);
          console.log("");
          ok("Action completed with force mode.");
          const postChoice = await promptPostActionChoice(rl);
          if (postChoice === "repeat") {
            parsed = forcedInput;
            continue;
          }
          if (postChoice === "main") {
            return { navigation: "main" };
          }
          return { navigation: "back" };
        } catch (forcedError) {
          stopLogCapture();
          await printCollapsibleLogs(rl);
          const secondChoice = await promptRecoveryChoice(rl, forcedError, false);
          if (secondChoice === "retry") {
            parsed = forcedInput;
            continue;
          }
          if (secondChoice === "try-again") {
            parsed = await collectInputForOption(rl, option);
            if (parsed === NAV_BACK) {
              return { navigation: "back" };
            }
            if (parsed === NAV_MAIN) {
              return { navigation: "main" };
            }
            continue;
          }
          if (secondChoice === "main") {
            return { navigation: "main" };
          }
          return { navigation: "back" };
        }
      }
      if (choice === "main") {
        return { navigation: "main" };
      }
      return { navigation: "back" };
    }
  }
}

async function runInteractiveMenu() {
  const rl = createInterface();
  let errorMessage = "";
  let currentSection = null;

  try {
    while (true) {
      if (!currentSection) {
        renderCategoryMenu(errorMessage);
        const selection = await promptMenuSelection(rl, "Select category: ", ["1", "2", "3", "4", "0", "x"]);
        const result = findCategoryOption(selection);

        if (result.exit) {
          clearScreen();
          ok("Git terminal closed.");
          return;
        }

        if (result.error) {
          errorMessage = result.error;
          continue;
        }

        errorMessage = "";
        currentSection = result.section;
        continue;
      }

      renderSectionMenu(currentSection, errorMessage);
      const sectionOptions = MENU_OPTIONS.filter((item) => item.section === currentSection);
      const immediateKeys = sectionOptions.map((_, index) => String(index + 1)).concat(["0", "m", "x"]);
      const selection = await promptMenuSelection(rl, "Select action: ", immediateKeys);
      const result = findSectionOption(currentSection, selection);

      if (result.exit) {
        clearScreen();
        ok("Git terminal closed.");
        return;
      }

      if (result.main) {
        errorMessage = "";
        currentSection = null;
        continue;
      }

      if (result.back) {
        errorMessage = "";
        currentSection = null;
        continue;
      }

      if (result.error) {
        errorMessage = result.error;
        continue;
      }

      errorMessage = "";
      const actionResult = await runInteractiveAction(rl, result.option);
      if (actionResult && actionResult.navigation === "main") {
        currentSection = null;
      }
      // navigation === "back" stays in currentSection (section menu re-renders)
      // navigation === "back" just loops back, showing section menu again
    }
  } finally {
    rl.close();
  }
}

const MENU_OPTIONS = [
  { number: 1, section: "quick", label: "Status", commandName: "status", tokens: ["status", "st"], description: "repo health, ahead/behind, changes", destructive: false, executor: actionStatus },
  { number: 2, section: "quick", label: "Pull", commandName: "pull", tokens: ["pull"], description: "fetch and update current work branch", destructive: false, executor: actionPull },
  { number: 3, section: "quick", label: "Push", commandName: "push", tokens: ["push"], description: "stage, commit, and push changes", destructive: true, executor: actionPush },
  { number: 4, section: "quick", label: "Log", commandName: "log", tokens: ["log", "lg"], description: "recent commit timeline", destructive: false, executor: actionLog },
  { number: 5, section: "quick", label: "Diff", commandName: "diff", tokens: ["diff", "df"], description: "show working tree or branch diff", destructive: false, executor: actionDiff },
  { number: 6, section: "branch", label: "Checkout", commandName: "checkout", tokens: ["checkout", "co"], description: "switch to another branch", destructive: false, executor: actionCheckout },
  { number: 7, section: "branch", label: "Branch List", commandName: "branch-list", tokens: ["branch-list", "brnh", "list branch"], description: "list local or remote branches", destructive: false, executor: actionBranchList },
  { number: 8, section: "branch", label: "Branch Create", commandName: "branch-create", tokens: ["branch-create", "new", "create branch"], description: "create a new branch from a base", destructive: false, executor: actionBranchCreate },
  { number: 9, section: "branch", label: "Branch Delete", commandName: "branch-delete", tokens: ["branch-delete", "del", "delete branch"], description: "remove local or remote branch", destructive: true, executor: actionBranchDelete },
  { number: 10, section: "branch", label: "Location Status", commandName: "location status", tokens: ["location status", "status location", "lstatus"], description: "show office/home branch mapping", destructive: false, executor: actionLocationStatus },
  { number: 11, section: "branch", label: "Location Switch", commandName: "location switch", tokens: ["location switch", "switch", "lswitch"], description: "switch work location and branch", destructive: true, executor: actionLocationSwitch },
  { number: 12, section: "branch", label: "Location Setup", commandName: "location setup", tokens: ["location setup", "setup", "lsetup"], description: "prepare home and office branches", destructive: true, executor: actionLocationSetup },
  { number: 13, section: "branch", label: "Location Sync", commandName: "location sync", tokens: ["location sync", "lsync"], description: "sync the active location branch", destructive: false, executor: actionLocationSync },
  { number: 14, section: "advanced", label: "Merge", commandName: "merge", tokens: ["merge", "mg"], description: "merge source branch into target", destructive: true, executor: actionMerge },
  { number: 15, section: "advanced", label: "Sync Rebase", commandName: "sync", tokens: ["sync", "rebase sync"], description: "rebase current branch onto base", destructive: true, executor: actionSync },
  { number: 16, section: "advanced", label: "Tag", commandName: "tag", tokens: ["tag"], description: "create and optionally push tag", destructive: true, executor: actionTag },
  { number: 17, section: "advanced", label: "Undo Last Commit", commandName: "undo", tokens: ["undo", "un"], description: "soft or hard reset the latest commit", destructive: true, executor: actionUndo },
  { number: 18, section: "advanced", label: "Stash", commandName: "stash", tokens: ["stash", "sh"], description: "save, list, pop, or drop stash", destructive: true, executor: actionStash },
  { number: 19, section: "advanced", label: "Clean Untracked", commandName: "clean", tokens: ["clean", "cn"], description: "remove untracked files", destructive: true, executor: actionClean },
  { number: 20, section: "utility", label: "Clone", commandName: "clone", tokens: ["clone", "cl"], description: "clone a repository into a folder", destructive: false, executor: actionClone },
  { number: 21, section: "utility", label: "Smart Clone", commandName: "smart-clone", tokens: ["smart-clone", "smart clone"], description: "clone opposite location branch and bootstrap", destructive: false, executor: actionSmartClone },
];

async function main() {
  ensureGitAvailable();

  try {
    await runDirect(process.argv);
  } catch (error) {
    if (error instanceof CommandError) {
      const detail = (error.result.stderr || error.result.stdout || "").trim();
      fatal(`${error.message}${detail ? `\n\n${detail}` : ""}`);
    }
    fatal(error.message || String(error));
  }
}

main();
