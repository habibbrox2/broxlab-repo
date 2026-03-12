Param(
    [string]$DumpFile,
    [switch]$AllowDrop,
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

function Escape-BashSingle([string]$value) {
    return $value -replace "'", "'\"'\"'"
}

$repoRoot = Get-RepoRoot
$envPath = Join-Path $repoRoot ".env"
$envMap = Read-DotEnv $envPath

$sshHost = Require-Value $envMap "DEPLOY_SSH_HOST"
$sshUser = Require-Value $envMap "DEPLOY_SSH_USER"
$sshPort = ($envMap["DEPLOY_SSH_PORT"] ?? "22")
$sshKey = $envMap["DEPLOY_SSH_KEY"]
$remotePath = Require-Value $envMap "DEPLOY_REMOTE_PATH"

$remoteDbHost = Require-Value $envMap "DEPLOY_REMOTE_DB_HOST"
$remoteDbPort = ($envMap["DEPLOY_REMOTE_DB_PORT"] ?? "3306")
$remoteDbName = Require-Value $envMap "DEPLOY_REMOTE_DB_NAME"
$remoteDbUser = Require-Value $envMap "DEPLOY_REMOTE_DB_USER"
$remoteDbPass = ($envMap["DEPLOY_REMOTE_DB_PASS"] ?? "")

if (-not $DumpFile) {
    $backupDirRaw = ($envMap["DB_BACKUP_DIR"] ?? "storage/backups")
    $backupDir = if ([System.IO.Path]::IsPathRooted($backupDirRaw)) { $backupDirRaw } else { Join-Path $repoRoot $backupDirRaw }
    if (!(Test-Path $backupDir)) { throw "Backup directory not found: $backupDir" }
    $latest = Get-ChildItem $backupDir -Filter "*.sql" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (!$latest) { throw "No dump files found in $backupDir" }
    $DumpFile = $latest.FullName
}

if (!(Test-Path $DumpFile)) { throw "Dump file not found: $DumpFile" }

if (-not $AllowDrop) {
    $content = Get-Content $DumpFile -Raw
    if ($content -match "(?i)\bDROP\s+TABLE\b" -or $content -match "(?i)\bDROP\s+DATABASE\b") {
        throw "Dump contains DROP statements. Re-run with -AllowDrop to proceed."
    }
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$remoteDump = "$remotePath/.deploy/db_$timestamp.sql"

$scpArgs = @()
if ($sshKey) { 
    # Write SSH key to temporary file
    $keyFile = [System.IO.Path]::GetTempFileName()
    Set-Content -Path $keyFile -Value $sshKey -NoNewline
    $scpArgs += "-i"; $scpArgs += $keyFile
}
if ($sshPort) { $scpArgs += "-P"; $scpArgs += $sshPort }
$scpArgs += $DumpFile
$scpArgs += "$sshUser@$sshHost:$remoteDump"

Write-Host "Uploading dump..."
if (-not $DryRun) { 
    & scp @scpArgs 
    # Clean up temp key file
    if ($sshKey) { Remove-Item $keyFile -Force -ErrorAction SilentlyContinue }
} else { Write-Host "DRY RUN: scp $DumpFile $sshUser@$sshHost:$remoteDump" }

$sshArgs = @()
if ($sshKey) { 
    # Write SSH key to temporary file
    $keyFile = [System.IO.Path]::GetTempFileName()
    Set-Content -Path $keyFile -Value $sshKey -NoNewline
    $sshArgs += "-i"; $sshArgs += $keyFile
}
if ($sshPort) { $sshArgs += "-p"; $sshArgs += $sshPort }
$sshArgs += "$sshUser@$sshHost"

$escPass = Escape-BashSingle $remoteDbPass
$escHost = Escape-BashSingle $remoteDbHost
$escUser = Escape-BashSingle $remoteDbUser
$escDb = Escape-BashSingle $remoteDbName
$escDump = Escape-BashSingle $remoteDump

$remoteCmd = @"
    set -e
    if [ ! -f '$escDump' ]; then
    echo 'Dump file not found on server.'
    exit 1
    fi
    MYSQL_PWD='$escPass' mysql -h '$escHost' -P '$remoteDbPort' -u '$escUser' '$escDb' < '$escDump'
    "@

Write-Host "Importing on server..."
if (-not $DryRun) { & ssh @sshArgs $remoteCmd } else { Write-Host "DRY RUN: ssh $sshUser@$sshHost <mysql import>" }

Write-Host "DB transfer completed."
