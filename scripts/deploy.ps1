Param(
    [switch]$WithVendor,
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-RepoRoot {
    return (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
}

function Read-DotEnv([string]$path) {
    $map = @{}
    if (!(Test-Path $path)) { return $map }
    foreach ($line in Get-Content $path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#")) { continue }
        $idx = $trimmed.IndexOf("=")
        if ($idx -lt 1) { continue }
        $key = $trimmed.Substring(0, $idx).Trim()
        $val = $trimmed.Substring($idx + 1).Trim()
        if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
            $val = $val.Substring(1, $val.Length - 2)
        }
        $map[$key] = $val
    }
    return $map
}

function Require-Value($map, [string]$key) {
    if (!$map.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($map[$key])) {
        throw "Missing required .env value: $key"
    }
    return $map[$key]
}

$repoRoot = Get-RepoRoot
$envPath = Join-Path $repoRoot ".env"
$envMap = Read-DotEnv $envPath

$sshHost = Require-Value $envMap "DEPLOY_SSH_HOST"
$sshUser = Require-Value $envMap "DEPLOY_SSH_USER"
$sshPort = ($envMap["DEPLOY_SSH_PORT"] ?? "22")
$sshKey = $envMap["DEPLOY_SSH_KEY"]
$remotePath = Require-Value $envMap "DEPLOY_REMOTE_PATH"

$deployDir = Join-Path $repoRoot ".deploy"
$stageDir = Join-Path $deployDir "stage"
$zipPath = Join-Path $deployDir "release.zip"

if (!(Test-Path $deployDir)) { New-Item -ItemType Directory -Path $deployDir | Out-Null }

$excludeDirs = @(
    ".git",
    ".deploy",
    "node_modules",
    "uploads",
    "public_html\uploads",
    "public_html\assets\uploads",
    "storage",
    "Database"
)
if (-not $WithVendor) { $excludeDirs += "vendor" }

$excludeFiles = @(
    ".env",
    "*.log",
    "*.sql",
    "*.zip"
)

$robocopyArgs = @(
    $repoRoot,
    $stageDir,
    "/MIR",
    "/XJ",
    "/R:2",
    "/W:1",
    "/NFL",
    "/NDL",
    "/NJH",
    "/NJS",
    "/NP"
)
if ($DryRun) { $robocopyArgs += "/L" }
if ($excludeDirs.Count -gt 0) { $robocopyArgs += "/XD"; $robocopyArgs += $excludeDirs }
if ($excludeFiles.Count -gt 0) { $robocopyArgs += "/XF"; $robocopyArgs += $excludeFiles }

Write-Host "Staging files..."
& robocopy @robocopyArgs | Out-Null
$rc = $LASTEXITCODE
if ($rc -ge 8) { throw "Robocopy failed with exit code $rc" }

if ($DryRun) {
    Write-Host "Dry run completed. No archive/upload performed."
    exit 0
}

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Write-Host "Creating archive..."
Compress-Archive -Path (Join-Path $stageDir "*") -DestinationPath $zipPath -Force

$remoteZip = "$remotePath/.deploy/release.zip"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$remoteNew = "${remotePath}__new"
$remoteOld = "${remotePath}__old_$timestamp"

$scpArgs = @()
if ($sshKey) { 
    # Write SSH key to temporary file
    $keyFile = [System.IO.Path]::GetTempFileName()
    Set-Content -Path $keyFile -Value $sshKey -NoNewline
    $scpArgs += "-i"; $scpArgs += $keyFile
}
if ($sshPort) { $scpArgs += "-P"; $scpArgs += $sshPort }
$scpArgs += $zipPath
$scpArgs += "$sshUser@$sshHost:$remoteZip"

Write-Host "Uploading archive..."
& scp @scpArgs

# Clean up temp key file
if ($sshKey) { Remove-Item $keyFile -Force -ErrorAction SilentlyContinue }

$sshArgs = @()
if ($sshKey) { 
    # Write SSH key to temporary file
    $keyFile = [System.IO.Path]::GetTempFileName()
    Set-Content -Path $keyFile -Value $sshKey -NoNewline
    $sshArgs += "-i"; $sshArgs += $keyFile
}
if ($sshPort) { $sshArgs += "-p"; $sshArgs += $sshPort }
$sshArgs += "$sshUser@$sshHost"

$remoteCmd = @"
set -e
mkdir -p "$remotePath/.deploy"
rm -rf "$remoteNew"
mkdir -p "$remoteNew"
unzip -oq "$remoteZip" -d "$remoteNew"
if [ -d "$remotePath" ]; then
  mv "$remotePath" "$remoteOld"
fi
mv "$remoteNew" "$remotePath"
"@

Write-Host "Deploying on server..."
& ssh @sshArgs $remoteCmd

Write-Host "Deploy completed."
