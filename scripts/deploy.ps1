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
if ($envMap.ContainsKey("DEPLOY_SSH_PORT") -and $envMap["DEPLOY_SSH_PORT"] -ne "") {
    $sshPort = $envMap["DEPLOY_SSH_PORT"]
}
else {
    $sshPort = "22"
}
$sshKeyPath = $envMap["DEPLOY_SSH_KEY_PATH"]
$sshKey = $envMap["DEPLOY_SSH_KEY"]
$remotePath = Require-Value $envMap "DEPLOY_REMOTE_PATH"
$keepOld = 5
if ($envMap.ContainsKey("DEPLOY_KEEP_OLD_RELEASES") -and $envMap["DEPLOY_KEEP_OLD_RELEASES"] -ne "") {
    $keepOld = [Math]::Max(1, [Math]::Min(50, [int]$envMap["DEPLOY_KEEP_OLD_RELEASES"]))
}

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

# Create temp staging directory in case files are locked
$tempStage = Join-Path $env:TEMP "deploy_stage_$(Get-Random)"
Copy-Item -Path (Join-Path $stageDir "*") -Destination $tempStage -Recurse -Force -ErrorAction SilentlyContinue

# Create archive from temp location
$tempZip = Join-Path $env:TEMP "release_$(Get-Random).zip"
Compress-Archive -Path "$tempStage\*" -DestinationPath $tempZip -Force

# Move to final location
Move-Item -Path $tempZip -Destination $zipPath -Force

# Cleanup temp
Remove-Item -Path $tempStage -Recurse -Force -ErrorAction SilentlyContinue

$remoteZip = "$remotePath/.deploy/release.zip"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$remoteNew = "${remotePath}__new"
$remoteOld = "${remotePath}__old_$timestamp"

$keyFileToUse = $null
if ($sshKeyPath) {
    if (!(Test-Path -LiteralPath $sshKeyPath)) {
        throw "DEPLOY_SSH_KEY_PATH file not found: $sshKeyPath"
    }
    $keyFileToUse = $sshKeyPath
} elseif ($sshKey) {
    # Write SSH key contents to temporary file (legacy)
    $keyFileToUse = [System.IO.Path]::GetTempFileName()
    Set-Content -Path $keyFileToUse -Value $sshKey -NoNewline
    $acl = Get-Acl $keyFileToUse
    $acl.SetAccessRuleProtection($true, $false)
    $currentUser = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($currentUser, "FullControl", "Allow")
    $acl.SetAccessRule($rule)
    Set-Acl -Path $keyFileToUse -AclObject $acl
}

$scpArgs = @()
if ($keyFileToUse) { $scpArgs += "-i"; $scpArgs += $keyFileToUse }
if ($sshPort) { $scpArgs += "-P"; $scpArgs += $sshPort }
$scpArgs += $zipPath
$scpDest = "${sshUser}@${sshHost}:${remoteZip}"
$scpArgs += $scpDest

Write-Host "Uploading archive..."
& scp @scpArgs

$sshArgs = @()
if ($keyFileToUse) { $sshArgs += "-i"; $sshArgs += $keyFileToUse }
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

# Ensure required runtime directories exist (no secrets are deployed)
mkdir -p "$remotePath/storage/logs" "$remotePath/storage/cache" "$remotePath/storage/tmp" || true
chmod -R 775 "$remotePath/storage" 2>/dev/null || true

# Retention: keep only last $keepOld previous releases
i=0
for d in \$(ls -1dt "${remotePath}__old_"* 2>/dev/null); do
  i=\$((i+1))
  if [ "\$i" -le "$keepOld" ]; then
    continue
  fi
  rm -rf "\$d"
done
"@

Write-Host "Deploying on server..."
& ssh @sshArgs $remoteCmd

Write-Host "Deploy completed."

if ($sshKey -and $keyFileToUse -and ($keyFileToUse -ne $sshKeyPath)) {
    Remove-Item $keyFileToUse -Force -ErrorAction SilentlyContinue
}
