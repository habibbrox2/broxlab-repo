# Git Helper CLI

A friendly Node.js wrapper around common git workflows with short command aliases.

**📖 বহুস্থান সিঙ্ক গাইড:** দেখুন [SYNC-GUIDE.md](SYNC-GUIDE.md) — অফিস ও বাসায় একসাথে কাজ করার জন্য সম্পূর্ণ ধাপে ধাপে নির্দেশনা।

## Installation

```bash
npm install shelljs
```

Add to your `package.json`:

```json
"scripts": {
  "git": "node git-hr/git-helper.js",
  "sync": "node git-hr/sync-clone.js"
}
```

---

## 🚀 Quick Sync Setup

### Clone repository with auto-setup:
```bash
# From .env GITHUB_REPO_URL
npm run sync

# Or manual clone
npm run git clone

# Or with custom URL
npm run git clone https://github.com/user/repo.git
```

This will:
- Clone repository
- Install PHP & Node dependencies
- Create local `.env` file
- Print setup instructions

---

## Commands & Aliases

| Command | Alias | Description |
|---|---|---|
| `clone` | `cl` | Clone a repository |
| `pull` | — | Pull latest, stash/pop local changes |
| `push` | — | Stage → commit → push |
| `merge` | `mg` | Merge source into target branch |
| `branch-create` | `new` | Create & optionally push a new branch |
| `branch-delete` | `del` | Delete branch locally (+ remotely) |
| `branch-list` | `brnh` | List all branches |
| `checkout` | `co` | Switch branch (auto stash if dirty) |
| `status` | `st` | Pretty git status overview |
| `log` | `lg` | Recent commit log |
| `sync` | — | Rebase current branch onto another |
| `tag` | — | Create annotated tag |
| `undo` | `un` | Undo last commit (soft or hard) |
| `stash` | `sh` | Manage stashes |
| `clean` | `cl` | Remove untracked files |
| `diff` | `df` | Show diff vs branch or working tree |

---

## Usage Examples

### Clone repository
```bash
npm run git clone https://github.com/user/repo.git
npm run git cl https://github.com/user/repo.git myrepo
```

### Pull main branch safely
```bash
npm run git pull main
```

### Push feature branch with custom commit message
```bash
npm run git push feature1 "Implemented login feature"
```

### Merge feature1 into main
```bash
npm run git mg feature1 main
```

### Create new branch
```bash
npm run git new hotfix
```

### Create new branch from a specific base
```bash
npm run git new feature/auth --from develop
```

### Delete branch locally & remotely
```bash
npm run git del hotfix --remote
```

### List all branches
```bash
npm run git brnh
npm run git brnh --remote
```

### Switch branch
```bash
npm run git co main
```

### View status
```bash
npm run git st
```

### View recent commits
```bash
npm run git lg
npm run git lg --n 20
```

### Sync current branch with main
```bash
npm run git sync main
```

### Squash merge
```bash
npm run git mg feature1 main --squash
```

### Force push (safe)
```bash
npm run git push feature1 "Fix auth bug" --force
```

### Undo last commit (soft — keeps changes staged)
```bash
npm run git un
```

### Undo last commit (hard — discards changes)
```bash
npm run git un --hard
```

### Stash changes
```bash
npm run git sh save "WIP: login flow"
npm run git sh pop
npm run git sh list
npm run git sh drop
```

### Show diff
```bash
npm run git df
npm run git df main
```

### Create and push a tag
```bash
npm run git tag v1.0.0 --push "Release v1.0.0"
```

### Clean untracked files
```bash
npm run git cl
```

---

## Help

```bash
npm run git -- --help
```
