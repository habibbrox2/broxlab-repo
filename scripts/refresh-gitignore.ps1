# Refresh Git tracking based on .gitignore
# Usage:
#   .\scripts\refresh-gitignore.ps1
#   .\scripts\refresh-gitignore.ps1 -DryRun

param(
    [switch]$DryRun
)

# Ensure we're in the repo root (has .gitignore)
if (-not (Test-Path -Path .gitignore)) {
    Write-Error "Cannot find .gitignore in the current directory. Run this from the repo root."
    exit 1
}

Write-Host "Using .gitignore from: $(Get-Location)\ .gitignore"

# Compute tracked files that match .gitignore
# (-c = cached/tracked, -o = others/untracked)
$ignored = git ls-files -i -c --exclude-from=.gitignore 2>$null | Sort-Object -Unique

if (-not $ignored) {
    Write-Host "No tracked files match .gitignore. Nothing to do."
    exit 0
}

Write-Host "The following tracked files match .gitignore and will be removed from tracking (kept locally):" -ForegroundColor Yellow
$ignored | ForEach-Object { Write-Host "  $_" }

if ($DryRun) {
    Write-Host "Dry run mode - no changes made. Run again without -DryRun to apply."
    exit 0
}

# Remove from index (keep files locally)
git rm --cached -r -- $ignored

# Commit and push
$commitMsg = "Stop tracking files listed in .gitignore"

git commit -m $commitMsg
if ($LASTEXITCODE -ne 0) {
    Write-Error "Commit failed. Fix any issues and run again."
    exit $LASTEXITCODE
}

git push

Write-Host "Done. Tracked ignored files removed, committed, and pushed." -ForegroundColor Green
