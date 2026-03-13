Param(
    [switch]$AllowDrop,
    [string]$OutFile
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

$dbHost = Require-Value $envMap "DB_HOST"
$dbPort = ($envMap["DB_PORT"] ?? "3306")
$dbName = Require-Value $envMap "DB_NAME"
$dbUser = Require-Value $envMap "DB_USER"
$dbPass = ($envMap["DB_PASS"] ?? "")
$dbCharset = ($envMap["DB_CHARSET"] ?? "utf8mb4")

$backupDirRaw = ($envMap["DB_BACKUP_DIR"] ?? "storage/backups")
$backupDir = if ([System.IO.Path]::IsPathRooted($backupDirRaw)) { $backupDirRaw } else { Join-Path $repoRoot $backupDirRaw }
if (!(Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }

if (-not $OutFile) {
    $stamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $OutFile = Join-Path $backupDir ("db_$stamp.sql")
}

$args = @(
    "--host=$dbHost",
    "--port=$dbPort",
    "--user=$dbUser",
    "--default-character-set=$dbCharset",
    "--single-transaction",
    "--quick",
    "--skip-lock-tables",
    "--routines",
    "--triggers"
)
if ($AllowDrop) { $args += "--add-drop-table" }
if ($dbPass -ne "") { $args += "--password=$dbPass" }

Write-Host "Backing up database to $OutFile ..."
& mysqldump @args $dbName | Out-File -FilePath $OutFile -Encoding utf8

Write-Host "Backup completed."
