# Git Push/Pull Safe Guide (Terminal)

## One-time setup
```
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
git remote -v
```

## Daily workflow (safe)
1. Check status:
```
git status --short
```
2. Update local branch:
```
git pull --rebase
```
3. Review changes:
```
git diff
```
4. Stage:
```
git add -A
```
5. Commit:
```
git commit -m "Short clear message"
```
6. Push:
```
git push
```

## If you already have local changes before pull
```
git status --short
git stash -u
git pull --rebase
git stash pop
```

## If pull shows conflict
1. See files:
```
git status
```
2. Open conflicted files and fix.
3. Stage:
```
git add <file>
```
4. Continue rebase:
```
git rebase --continue
```
5. Push:
```
git push
```

## If you want to avoid rebase
```
git pull --ff-only
```

## Quick safety checks
```
git status --short
git log --oneline -5
git remote -v
```

## Home/Office safe sync workflow
এই ধাপগুলো বাসা/অফিস দুই জায়গা থেকেই একই প্রজেক্টে নিরাপদে কাজ করতে সাহায্য করবে।

### First time (each machine)
```
git clone <repo-url>
git remote -v
```

### Start of day (always sync before work)
```
git fetch origin
git status --short
git pull --rebase
```

### During work (commit often)
```
git add -A
git commit -m "Meaningful message"
```

### End of day (push)
```
git push origin <branch-name>
```

### If you have local changes but need latest first
```
git stash -u
git fetch origin
git pull --rebase
git stash pop
```

### If you want to avoid rebase
```
git pull --ff-only
```

### If conflict happens
```
git status
git add <fixed-file>
git rebase --continue
git push
```
এখানে অফিস → বাসা ফ্লোটা ঠিক এমন হবে (সংক্ষেপে):

**অফিসে কাজ শেষ করে পুশ**
```
git status --short
git add -A
git commit -m "Work done"
git push origin <branch-name>
```

**বাসায় গিয়ে কাজ শুরু (সিঙ্ক আগে)**
```
git fetch origin
git pull --rebase
```

তারপর স্বাভাবিকভাবে কাজ করুন, শেষে আবার একইভাবে commit + push।

যদি অফিসে পুশ করার আগে local change থাকে এবং আপনি আগে সিঙ্ক করতে চান:
```
git stash -u
git pull --rebase
git stash pop
```

এটাই safest workflow।