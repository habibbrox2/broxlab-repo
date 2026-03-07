#!/usr/bin/env node
"use strict";

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
  white:  "\x1b[37m",
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

/** Ask user to confirm. Returns true/false. Requires a TTY. */
function confirm(question) {
  // If not a TTY (e.g. CI), treat as confirmed
  if (!process.stdin.isTTY) return true;

  const readline = require("readline");
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  return new Promise((resolve) => {
    rl.question(`${c.yellow}?${c.reset}  ${question} ${dim("[y/N]")} `, (ans) => {
      rl.close();
      resolve(ans.trim().toLowerCase() === "y");
    });
  });
}

/** Detect uncommitted changes. */
function hasUncommittedChanges() {
  return capture("git status --porcelain") !== "";
}

/** Return the name of the current branch. */
function currentBranch() {
  return capture("git rev-parse --abbrev-ref HEAD");
}

/** Get branch name from .env based on WORK_LOCATION (home/office). */
function getBranchFromEnv() {
  const fs = require("fs");
  const path = require("path");
  const envPath = path.join(process.cwd(), ".env");
  
  if (!fs.existsSync(envPath)) {
    return null;
  }
  
  const content = fs.readFileSync(envPath, "utf-8");
  const workLocation = getEnvValue(content, "WORK_LOCATION") || "home";
  
  if (workLocation === "home") {
    return getEnvValue(content, "BRANCH_HOME") || "home";
  } else if (workLocation === "office") {
    return getEnvValue(content, "BRANCH_OFFICE") || "office";
  }
  
  return null;
}

/** Extract a value from .env content by key. */
function getEnvValue(content, key) {
  const match = content.match(new RegExp(`${key}\\s*=\\s*(.+)`));
  return match ? match[1].trim() : null;
}

/** Check whether a local branch exists. */
function branchExists(branch) {
  return capture(`git branch --list ${branch}`) !== "";
}

/** Check whether a remote branch exists. */
function remoteBranchExists(branch, remote = "origin") {
  return capture(`git ls-remote --heads ${remote} ${branch}`) !== "";
}

/** Abort if there are unresolved merge conflicts. */
function assertNoConflicts() {
  const conflicted = capture("git ls-files -u");
  if (conflicted !== "") {
    fatal("Merge conflict detected! Resolve conflicts manually, then commit.");
  }
}

/** Pretty-print a section header. */
function header(title) {
  const line = "─".repeat(50);
  console.log(`\n${c.blue}${line}${c.reset}`);
  console.log(`  ${bold(title)}`);
  console.log(`${c.blue}${line}${c.reset}\n`);
}

// ─── Usage ────────────────────────────────────────────────────────────────────

function usage() {
  console.log(`
${bold("Git Helper")} — a friendly wrapper around common git workflows

${bold("USAGE")}
  node git-helper.js <action> [options]

${bold("ACTIONS")}  ${dim("(alias)")}
  ${c.cyan}clone${c.reset}     ${dim("cl")}      <url> [dir]                    Clone a repository
  ${c.cyan}pull${c.reset}             <branch>                       Pull latest, stash/pop local changes
  ${c.cyan}push${c.reset}             <branch> [msg] [--force]       Stage → commit → push
  ${c.cyan}merge${c.reset}   ${dim("mg")}      <source> <target> [--squash]   Merge source into target
  ${c.cyan}branch-create${c.reset} ${dim("new")}     <branch> [--from <base>]      Create & optionally push branch
  ${c.cyan}branch-delete${c.reset} ${dim("del")}     <branch> [--remote]           Delete branch locally (+ remotely)
  ${c.cyan}branch-list${c.reset}   ${dim("brnh")}    [--remote]                    List all branches
  ${c.cyan}checkout${c.reset}  ${dim("co")}      <branch>                       Switch branch (stash if dirty)
  ${c.cyan}status${c.reset}    ${dim("st")}                                    Pretty git status overview
  ${c.cyan}log${c.reset}       ${dim("lg")}      [--n <count>]                  Recent commit log (default 10)
  ${c.cyan}sync${c.reset}              <branch>                       Rebase current branch onto <branch>
  ${c.cyan}tag${c.reset}               <n> [--push] [msg]          Create annotated tag (optionally push)
  ${c.cyan}undo${c.reset}      ${dim("un")}      [--hard]                       Undo last commit (soft or hard)
  ${c.cyan}stash${c.reset}     ${dim("sh")}      <save|pop|list|drop> [name]    Manage stashes
  ${c.cyan}clean${c.reset}     ${dim("cn")}                                     Remove untracked files (with prompt)
  ${c.cyan}diff${c.reset}      ${dim("df")}      [branch]                       Show diff vs branch or working tree

${bold("EXAMPLES")}
  node git-helper.js clone https://github.com/user/repo.git
  node git-helper.js push main "fix: correct typo"
  node git-helper.js mg feature/login main --squash
  node git-helper.js new feature/signup --from develop
  node git-helper.js del old-branch --remote
  node git-helper.js brnh --remote
  node git-helper.js co main
  node git-helper.js st
  node git-helper.js lg --n 20
`);
}

// ─── Arg parser ───────────────────────────────────────────────────────────────

function parseArgs(argv) {
  const positional = [];
  const flags = {};

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg.startsWith("--")) {
      const key = arg.slice(2);
      const next = argv[i + 1];
      if (next && !next.startsWith("--")) {
        flags[key] = next;
        i++;
      } else {
        flags[key] = true;
      }
    } else {
      positional.push(arg);
    }
  }

  return { positional, flags };
}

// ─── Actions ──────────────────────────────────────────────────────────────────

async function actionClone({ positional, flags }) {
  let url = positional[0];
  const dir = positional[1];

  // If no URL provided, try to read from .env
  if (!url) {
    const fs = require("fs");
    const path = require("path");
    const envPath = path.join(process.cwd(), ".env");
    
    if (fs.existsSync(envPath)) {
      log("Reading repository URL from .env…");
      const content = fs.readFileSync(envPath, "utf-8");
      const match = content.match(/GITHUB_REPO_URL\s*=\s*(.+)/);
      url = match ? match[1].trim() : null;
    }

    if (!url) {
      fatal(`.env file not found or GITHUB_REPO_URL not configured.\nUsage: node git-helper.js clone <url> [dir]`);
    }
  }

  header(`Clone — ${url}`);

  const cmd = dir ? `git clone ${url} ${dir}` : `git clone ${url}`;
  log(`Cloning repository…`);
  run(cmd);

  const clonedDir = dir || url.split("/").pop().replace(".git", "");
  ok(`Repository cloned successfully to ${bold(clonedDir)}.`);
  log(`\nRun ${dim(`node git-hr/sync-clone.js`)} for complete setup (dependencies, env, etc.)`);
}

async function actionPull({ positional, flags }) {
  let branch = positional[0];
  
  if (!branch) {
    branch = getBranchFromEnv();
    if (!branch) {
      fatal("Branch name not provided and .env WORK_LOCATION not configured.");
    }
    log(`Using branch from WORK_LOCATION: ${bold(branch)}`);
  }
  
  header(`Pull — ${branch}`);

  const stashed = hasUncommittedChanges();
  if (stashed) {
    log("Stashing local changes…");
    run("git stash push -m 'pre-pull auto-stash'");
  }

  log(`Fetching origin…`);
  run("git fetch origin");

  log(`Pulling ${branch} from origin…`);
  run(`git pull origin ${branch}`);

  if (stashed) {
    log("Re-applying stashed changes…");
    const pop = run("git stash pop", { allowFail: true });
    if (pop.code !== 0) {
      warn("Stash pop caused conflicts — resolve manually, then run: git stash drop");
    }
  }

  ok(`Pulled ${bold(branch)} successfully.`);
}

async function actionPush({ positional, flags }) {
  let branch = positional[0];
  
  if (!branch) {
    branch = getBranchFromEnv();
    if (!branch) {
      fatal("Branch name not provided and .env WORK_LOCATION not configured.");
    }
    log(`Using branch from WORK_LOCATION: ${bold(branch)}`);
  }
  
  const commitMsg = positional[1] ?? generateCommitMessage();
  const force     = flags.force ?? false;
  header(`Push — ${branch}`);

  if (!hasUncommittedChanges()) {
    warn("Nothing to commit — working tree is clean.");
  } else {
    log("Staging all changes…");
    run("git add .");

    // Show a quick diff summary
    const stat = capture("git diff --cached --stat");
    console.log(`\n${c.dim}${stat}${c.reset}\n`);

    log(`Committing with message: ${bold(commitMsg)}`);
    run(`git commit -m ${JSON.stringify(commitMsg)}`);
  }

  const forceFlag = force ? " --force-with-lease" : "";
  if (force) warn("Force-push requested — using --force-with-lease.");

  log(`Pushing to origin/${branch}…`);
  run(`git push origin ${branch}${forceFlag}`);
  ok(`Pushed to ${bold("origin/" + branch)}.`);
}

async function actionMerge({ positional, flags }) {
  const source = positional[0] ?? fatal("Source branch required for merge.");
  const target = positional[1] ?? fatal("Target branch required for merge.");
  const squash = flags.squash ?? false;
  header(`Merge — ${source} → ${target}`);

  const before = currentBranch();

  log(`Switching to ${target}…`);
  run(`git checkout ${target}`);

  log(`Pulling latest ${target}…`);
  run(`git pull origin ${target}`);

  const squashFlag = squash ? " --squash" : "";
  log(`Merging ${source} into ${target}${squash ? " (squash)" : ""}…`);
  run(`git merge ${source}${squashFlag}`);

  assertNoConflicts();

  if (squash) {
    log("Committing squashed merge…");
    run(`git commit -m "chore: squash merge ${source} into ${target}"`);
  }

  ok(`Merged ${bold(source)} into ${bold(target)} successfully.`);

  if (before !== target) {
    log(`Returning to previous branch (${before})…`);
    run(`git checkout ${before}`);
  }
}

async function actionBranchCreate({ positional, flags }) {
  const branch = positional[0] ?? fatal("Branch name required.");
  const base   = flags.from ?? currentBranch();
  const push   = flags.push ?? false;
  header(`Create branch — ${branch}`);

  if (branchExists(branch)) fatal(`Branch ${bold(branch)} already exists locally.`);

  log(`Basing off ${bold(base)}…`);
  run(`git checkout ${base}`);
  run(`git pull origin ${base}`, { allowFail: true });

  log(`Creating branch ${bold(branch)}…`);
  run(`git checkout -b ${branch}`);

  if (push) {
    log(`Pushing ${branch} to origin…`);
    run(`git push -u origin ${branch}`);
    ok(`Branch ${bold(branch)} created and pushed to origin.`);
  } else {
    ok(`Branch ${bold(branch)} created locally.`);
    log(`To push: ${dim(`node git-hr/git-helper.js branch-create ${branch} --push`)}`);
  }
}

async function actionBranchList({ flags }) {
  const remote = flags.remote ?? false;
  header(remote ? "Remote Branches" : "Local Branches");
  if (remote) {
    run("git branch -r");
  } else {
    run("git branch -v");
  }
}


async function actionBranchDelete({ positional, flags }) {
  const branch = positional[0] ?? fatal("Branch name required.");
  const remote = flags.remote ?? false;
  header(`Delete branch — ${branch}`);

  if (branch === currentBranch()) fatal("Cannot delete the currently checked-out branch.");

  if (!branchExists(branch)) {
    warn(`Local branch ${bold(branch)} doesn't exist — skipping local delete.`);
  } else {
    run(`git branch -D ${branch}`);
    ok(`Deleted local branch ${bold(branch)}.`);
  }

  if (remote) {
    if (!remoteBranchExists(branch)) {
      warn(`Remote branch origin/${branch} not found — skipping remote delete.`);
    } else {
      run(`git push origin --delete ${branch}`);
      ok(`Deleted remote branch ${bold("origin/" + branch)}.`);
    }
  }
}

async function actionCheckout({ positional, flags }) {
  const branch = positional[0] ?? fatal("Branch name required.");
  header(`Checkout — ${branch}`);

  const dirty = hasUncommittedChanges();
  if (dirty) {
    warn("You have uncommitted changes — auto-stashing…");
    run("git stash push -m 'auto-stash before checkout'");
  }

  run(`git checkout ${branch}`);
  ok(`Switched to ${bold(branch)}.`);

  if (dirty) {
    log("Re-applying stashed changes…");
    run("git stash pop", { allowFail: true });
  }
}

async function actionStatus() {
  header("Repository Status");

  const branch   = currentBranch();
  const remoteOk = capture("git remote").includes("origin");

  console.log(`  Branch : ${bold(branch)}`);
  if (remoteOk) {
    const ahead  = capture(`git rev-list --count origin/${branch}..HEAD 2>/dev/null || echo 0`);
    const behind = capture(`git rev-list --count HEAD..origin/${branch} 2>/dev/null || echo 0`);
    console.log(`  Ahead  : ${ahead} commit(s)`);
    console.log(`  Behind : ${behind} commit(s)`);
  }
  console.log();

  const status = capture("git status --short");
  if (status === "") {
    ok("Working tree is clean.");
  } else {
    console.log(status.split("\n").map((l) => {
      const code = l.slice(0, 2);
      const file = l.slice(3);
      const colored =
        code.includes("M") ? `${c.yellow}${code}${c.reset}` :
        code.includes("A") ? `${c.green}${code}${c.reset}`  :
        code.includes("D") ? `${c.red}${code}${c.reset}`    :
        code.includes("?") ? `${c.dim}${code}${c.reset}`    : code;
      return `  ${colored} ${file}`;
    }).join("\n"));
  }

  console.log();
  const stashList = capture("git stash list");
  if (stashList) {
    console.log(`${c.dim}Stashes:\n${stashList}${c.reset}\n`);
  }
}

async function actionLog({ flags }) {
  const n = parseInt(flags.n ?? "10", 10);
  header(`Recent Commits (last ${n})`);

  const format = "%C(yellow)%h%Creset %C(cyan)%ar%Creset %C(bold white)%s%Creset %C(dim white)<%an>%Creset";
  run(`git log --oneline --graph --format="${format}" -n ${n}`);
}

async function actionSync({ positional }) {
  const base   = positional[0] ?? fatal("Base branch required (e.g. main).");
  const branch = currentBranch();
  header(`Sync — rebase ${branch} onto ${base}`);

  log(`Fetching latest ${base}…`);
  run(`git fetch origin ${base}`);

  log(`Rebasing ${bold(branch)} onto origin/${base}…`);
  const result = run(`git rebase origin/${base}`, { allowFail: true });
  if (result.code !== 0) {
    fatal("Rebase conflict! Fix conflicts, then run:\n  git rebase --continue\n  (or: git rebase --abort)");
  }

  ok(`${bold(branch)} is now in sync with ${bold(base)}.`);
}

async function actionTag({ positional, flags }) {
  const name = positional[0] ?? fatal("Tag name required.");
  const msg  = positional[1] ?? name;
  const push = flags.push ?? false;
  header(`Tag — ${name}`);

  run(`git tag -a ${name} -m ${JSON.stringify(msg)}`);
  ok(`Created annotated tag ${bold(name)}.`);

  if (push) {
    run(`git push origin ${name}`);
    ok(`Pushed tag ${bold(name)} to origin.`);
  }
}

async function actionUndo({ flags }) {
  const hard = flags.hard ?? false;
  header(`Undo last commit${hard ? " (HARD)" : " (soft)"}`);

  if (hard) {
    warn("Hard undo will permanently discard changes!");
    const ok2 = await confirm("Are you sure?");
    if (!ok2) { log("Aborted."); return; }
    run("git reset --hard HEAD~1");
  } else {
    run("git reset --soft HEAD~1");
  }

  ok("Last commit undone.");
}

async function actionStash({ positional }) {
  const sub  = positional[0] ?? fatal("Sub-command required: save | pop | list | drop");
  const name = positional[1];

  switch (sub) {
    case "save":
      run(`git stash push -m ${JSON.stringify(name ?? "manual stash")}`);
      ok("Changes stashed.");
      break;
    case "pop":
      run("git stash pop");
      ok("Stash applied and removed.");
      break;
    case "list":
      console.log(capture("git stash list") || "No stashes.");
      break;
    case "drop":
      run(name ? `git stash drop ${name}` : "git stash drop");
      ok("Stash dropped.");
      break;
    default:
      fatal(`Unknown stash sub-command: ${sub}`);
  }
}

async function actionClean() {
  header("Clean untracked files");
  const preview = capture("git clean -nd");
  if (!preview) { ok("Nothing to clean."); return; }

  console.log(`\nWould remove:\n${c.dim}${preview}${c.reset}\n`);
  const yes = await confirm("Proceed?");
  if (!yes) { log("Aborted."); return; }

  run("git clean -fd");
  ok("Untracked files removed.");
}

async function actionDiff({ positional }) {
  const target = positional[0];
  if (target) {
    run(`git diff ${target}`);
  } else {
    run("git diff");
  }
}

// ─── Auto commit message ──────────────────────────────────────────────────────

function generateCommitMessage() {
  const stat = capture("git diff --cached --stat");
  const files = (stat.match(/(\d+) file/) ?? [])[1] ?? "?";
  const ts = new Date().toISOString().slice(0, 16).replace("T", " ");
  return `chore: auto commit (${files} file(s) changed) [${ts}]`;
}

// ─── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  if (!shell.which("git")) fatal("git is not installed or not in PATH.");

  const raw = process.argv.slice(2);
  if (raw.length === 0 || raw[0] === "--help" || raw[0] === "-h") {
    usage();
    process.exit(0);
  }

  const action = raw[0].toLowerCase();
  const parsed = parseArgs(raw.slice(1));

  const actions = {
    // Full commands
    clone:           actionClone,
    pull:            actionPull,
    push:            actionPush,
    merge:           actionMerge,
    "branch-create": actionBranchCreate,
    "branch-delete": actionBranchDelete,
    "branch-list":   actionBranchList,
    checkout:        actionCheckout,
    status:          actionStatus,
    log:             actionLog,
    sync:            actionSync,
    tag:             actionTag,
    undo:            actionUndo,
    stash:           actionStash,
    clean:           actionClean,
    diff:            actionDiff,
    // Short aliases
    cl:              actionClone,
    mg:              actionMerge,
    new:             actionBranchCreate,
    del:             actionBranchDelete,
    brnh:            actionBranchList,
    co:              actionCheckout,
    st:              actionStatus,
    lg:              actionLog,
    un:              actionUndo,
    sh:              actionStash,
    cn:              actionClean,
    df:              actionDiff,
  };

  const fn = actions[action];
  if (!fn) fatal(`Unknown action: ${bold(action)}\nRun with --help to see available commands.`);

  try {
    await fn(parsed);
  } catch (err) {
    fatal(`Unexpected error: ${err.message}`);
  }
}

main();
