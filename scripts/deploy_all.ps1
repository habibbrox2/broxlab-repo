Param(
    [switch]$WithVendor,
    [switch]$DryRun,
    [switch]$SkipDb,
    [string]$DumpFile
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

function Run-Step([string]$label, [string]$file, [string[]]$args) {
    Write-Host "==> $label"
    & powershell -ExecutionPolicy Bypass -File $file @args
}

if (-not $SkipDb) {
    $backupArgs = @()
    if ($DryRun) {
        Write-Host "Dry run enabled: DB backup will still run to validate dump."
    }
    Run-Step "DB backup" (Join-Path $repoRoot "scripts\db_backup.ps1") $backupArgs
}

$deployArgs = @()
if ($WithVendor) { $deployArgs += "--with-vendor" }
if ($DryRun) { $deployArgs += "--dry-run" }
Run-Step "Deploy" (Join-Path $repoRoot "scripts\deploy.ps1") $deployArgs

if (-not $SkipDb) {
    $transferArgs = @()
    if ($DumpFile) { $transferArgs += "-DumpFile"; $transferArgs += $DumpFile }
    if ($DryRun) { $transferArgs += "-DryRun" }
    Run-Step "DB transfer" (Join-Path $repoRoot "scripts\db_transfer.ps1") $transferArgs
}

Write-Host "All steps completed."
