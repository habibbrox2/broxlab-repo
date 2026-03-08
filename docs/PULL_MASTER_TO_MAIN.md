# Pull `master` into `main`

If your repository uses `main` locally but the remote uses `master` (or you want to sync the two), follow these steps.

## 1) Merge remote `master` into local `main` (safe)

```bash
# Ensure you are on main
git checkout main

# Fetch latest remote updates
git fetch origin

# Merge remote master into main
git merge origin/master

# Push the updated main branch to remote
git push --set-upstream origin main
```

## 2) Overwrite local `main` with remote `master` (force)

> **Warning:** This will discard local changes on `main`.

```bash
git fetch origin

git checkout main
git reset --hard origin/master

git push --set-upstream origin main --force
```

## 3) Why this is useful
- Use when the remote's primary branch is `master` but you work on `main` locally.
- Keeps your local workflow on `main` while staying in sync with remote `master`.
