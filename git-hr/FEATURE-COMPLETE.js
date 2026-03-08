#!/usr/bin/env node
/**
 * ✨ COMPLETE FEATURE UPDATE - Auto Branch & Opposite Clone
 * 
 * Features Implemented:
 * 1. Auto-Branch Detection for push/pull
 * 2. Opposite Branch Clone for sync
 * 
 * Version: 1.1
 * Date: 2024-03-08
 */

console.log(`
╔════════════════════════════════════════════════════════════════════════════╗
║                                                                            ║
║              ✨ GIT HELPER: COMPLETE FEATURE UPDATE ✨                     ║
║                                                                            ║
║              Auto-Branch Detection + Opposite Branch Clone                ║
║                                                                            ║
╚════════════════════════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 FEATURE #1: AUTO-BRANCH DETECTION FOR PUSH/PULL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

What: .env এর WORK_LOCATION অনুযায়ী automatically branch নির্বাচন

Before:
  npm run git push main "commit"                 ← branch manually দিতে হত
  npm run git pull main

After:
  npm run git push "commit"                      ← branch auto-detect!
  npm run git pull

Configuration (.env):
  WORK_LOCATION=home      (বা office)
  BRANCH_HOME=home        (home branch)
  BRANCH_OFFICE=office    (office branch)

How it works:
  • WORK_LOCATION=home  → npm run git push → home branch ব্যবহার
  • WORK_LOCATION=office → npm run git push → office branch ব্যবহার

Backward Compatible:
  ✓ npm run git push main "msg" → এখনও কাজ করে (explicit override)


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔄 FEATURE #2: OPPOSITE BRANCH CLONE (SMART SYNC)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

What: npm run sync করলে OPPOSITE location এর branch clone হয়

Use Case:
  • You're at OFFICE → CLONE HOME branch (home code পান)
  • You're at HOME → CLONE OFFICE branch (office code পান)

Command:
  npm run sync

What happens:
  1. Reads .env → WORK_LOCATION
  2. Determines opposite branch (office → home, home → office)
  3. Clones opposite branch with --branch flag
  4. Auto-installs dependencies (composer, npm)
  5. Setup .env from template

Example:
  $ WORK_LOCATION=office in .env
  $ npm run sync
  ✅ Clones HOME branch (home location's code)
  ✅ Ready to work!


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 COMPLETE WORKFLOW EXAMPLE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Day 1 - Morning at OFFICE:
  $ WORK_LOCATION=office
  $ npm run git push "Office morning work"        # auto push to office branch
  $ npm run git pull                              # auto pull from office branch

Day 1 - Evening (Home):
  $ WORK_LOCATION=home
  $ npm run branch switch home                    # switch location + branch
  $ npm run git pull                              # auto pull latest office work
  $ npm run git push "Home evening work"          # auto push to home branch

Day 2 - Morning (Back to Office):
  $ WORK_LOCATION=office
  $ npm run branch switch office                  # switch to office
  $ npm run git pull                              # auto pull latest home work
  $ npm run git push "Office morning follow-up"   # auto push to office branch


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 QUICK COMMANDS REFERENCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

First Time Setup:
  npm run branch setup                  # Create home/office branches
  npm run sync                          # Clone opposite branch

Daily Usage - Push/Pull:
  npm run git push "message"            # auto-detect branch
  npm run git pull                      # auto-detect branch
  npm run git st                        # status

Location Switching:
  npm run branch switch office          # switch to office location
  npm run branch switch home            # switch to home location
  npm run branch status                 # show current location

Advanced (still works):
  npm run git push main "message"       # explicit branch override
  npm run git clone <url>               # manual clone
  git clone --branch home <url>         # full git commands also work


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📁 FILES MODIFIED/CREATED
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Modified:
  ✓ git-hr/git-helper.cjs
    - Added getBranchFromEnv() function
    - Updated actionPush() for auto-branch
    - Updated actionPull() for auto-branch

  ✓ git-hr/sync-clone.cjs  
    - Added opposite branch detection logic
    - Auto-determine branch based on WORK_LOCATION
    - Clone uses --branch flag for smart sync

  ✓ package.json
    - npm scripts updated to use .cjs extensions

Renamed (CommonJS compatibility):
  ✓ git-helper.js → git-helper.cjs
  ✓ sync-clone.js → sync-clone.cjs
  ✓ branch-manager.js → branch-manager.cjs

New Documentation:
  ✓ git-hr/AUTO-BRANCH.md
  ✓ git-hr/SETUP-CHECK.md
  ✓ git-hr/OPPOSITE-BRANCH-CLONE.md
  ✓ git-hr/FEATURE-AUTO-BRANCH.js (this announcement)


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📚 DOCUMENTATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Start Here:
  1. git-hr/AUTO-BRANCH.md              ← Auto-detect for push/pull
  2. git-hr/OPPOSITE-BRANCH-CLONE.md    ← Opposite clone for sync

Detailed Guides:
  3. git-hr/BRANCH-GUIDE.md             ← Full branch system
  4. git-hr/SETUP-CHECK.md              ← Setup verification
  5. git-hr/GIT-HELPER.md               ← All commands reference
  6. git-hr/SYNC-GUIDE.md               ← Complete sync workflow


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✨ KEY BENEFITS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✓ Less typing            - no need to remember/type branch names
✓ Less mistakes          - can't push to wrong branch accidentally  
✓ Smarter syncing        - automatically clone opposite location's code
✓ Cleaner commands       - just "npm run git push 'msg'"
✓ Location-aware         - respects WORK_LOCATION from .env
✓ Safe branching         - home/office code stays separated
✓ Easy switching         - npm run branch switch handles everything
✓ Backward compatible    - old commands still work (explicit override)


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎯 NEXT STEPS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Read Documentation:
   → git-hr/AUTO-BRANCH.md
   → git-hr/OPPOSITE-BRANCH-CLONE.md

2. Verify Setup:
   → Open .env, check WORK_LOCATION, BRANCH_HOME, BRANCH_OFFICE

3. First Time Setup:
   → npm run branch setup           (create home/office branches)
   
4. Test the Features:
   → npm run git push "test message"   (auto-detect branch)
   → npm run git pull                  (auto-detect branch)
   → npm run sync                      (clone opposite branch)

5. Daily Usage:
   → npm run git push "message"        (push current work)
   → npm run git pull                  (pull latest)
   → npm run branch switch [location]  (change location)


═══════════════════════════════════════════════════════════════════════════════
✅ All Features Ready!  কোড করুন খুশিতে! 🎉
═══════════════════════════════════════════════════════════════════════════════
`);
