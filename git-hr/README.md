# Git HR - Helper Repository Tools

📂 **Centralized Git management tools for BroxBhai project**

## 📁 Files in this directory

- **git-helper.js** — Main Git CLI tool with all commands
- **sync-clone.js** — Smart clone with auto-setup
- **branch-manager.js** — Branch switching for multi-location work ✨ NEW
- **GIT-HELPER.md** — Complete command reference
- **SYNC-GUIDE.md** — Detailed multi-location sync guide (Bengali)
- **BRANCH-GUIDE.md** — Branch system for office/home (Bengali) ✨ NEW
- **QUICK-START.md** — Quick start guide (Bengali)
- **README.md** — This file

---

## 🚀 Quick Usage

From project root:

```bash
# View all git commands
npm run git -- --help

# Clone a repository
npm run git clone https://github.com/user/repo.git

# Sync everything including dependencies
npm run sync

# Branch management (NEW!)
npm run branch setup              # First time setup
npm run branch switch home        # When going home
npm run branch switch office      # When going to office
npm run branch status             # View current location
npm run branch sync               # Safe pull/push

# Common daily commands
npm run git push main "commit message"
npm run git pull main
npm run git st           # status
npm run git lg           # logs
```

---

## 📖 Documentation

- **Quick Start:** Read [QUICK-START.md](QUICK-START.md)
- **Complete Guide:** Read [SYNC-GUIDE.md](SYNC-GUIDE.md) (বাংলা)
- **All Git Commands:** Read [GIT-HELPER.md](GIT-HELPER.md)
- **Branch System:** Read [BRANCH-GUIDE.md](BRANCH-GUIDE.md) (বাংলা) ✨ NEW
- **Files List:** Read [FILES-LIST.md](FILES-LIST.md)

---

## 💡 Why use this?

✅ Simplified git workflows  
✅ Multi-location synchronization (office ↔️ home) with branches  
✅ Automated clone with dependency installation  
✅ Auto commit message generation  
✅ Color-coded output for clarity  
✅ Stash/pop management  
✅ Branch management helpers  
✅ Safe branch switching with automatic environment detection  ✨ NEW

---

## 🔧 Installation

Already installed via `npm install shelljs` in project root.

---

## 📝 Scripts in package.json

```json
"scripts": {
  "git": "node git-hr/git-helper.js",
  "sync": "node git-hr/sync-clone.js",
  "branch": "node git-hr/branch-manager.js"
}
```

---

## 🎯 Typical Workflow

### **Setup (First time)**
```bash
npm run branch setup
```

### **At Office**
```bash
npm run branch switch office
npm run git push main "work in progress"
```

### **At Home**
```bash
npm run branch switch home
npm run branch sync
npm run git push main "home work"
```

### **Next Day at Office**
```bash
npm run branch switch office
npm run branch sync
npm run git push main "continue office work"
```

---

**Built for BroxBhai — Work from anywhere, stay synced everywhere.** ✅

