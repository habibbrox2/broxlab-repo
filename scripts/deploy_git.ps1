Param(
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
$sshKey = $envMap["DEPLOY_SSH_KEY"]
$remotePath = Require-Value $envMap "DEPLOY_REMOTE_PATH"

if ($DryRun) {
    Write-Host "=== DRY RUN ===" -ForegroundColor Yellow
    Write-Host "SSH Host: $sshHost"
    Write-Host "SSH User: $sshUser"
    Write-Host "SSH Port: $sshPort"
    Write-Host "SSH Key: $(if ($sshKey) { $sshKey } else { 'Using password auth' })"
    Write-Host "Remote Path: $remotePath"
    Write-Host ""
    Write-Host "Will execute: git -C $remotePath pull origin"
    exit 0
}

Write-Host "Deploying via Git pull..." -ForegroundColor Green

# Build SSH command
$sshArgs = @()
if ($sshKey) { 
    $sshArgs += "-i"
    $sshArgs += $sshKey
}
$sshArgs += "-p"
$sshArgs += $sshPort
$sshArgs += "${sshUser}@${sshHost}"
$remoteCmd = "cd '$remotePath' && git pull origin && echo 'Deploy completed successfully'"

Write-Host "Connecting to $sshHost..."
& ssh @sshArgs $remoteCmd

if ($LASTEXITCODE -ne 0) {
    throw "SSH command failed with exit code $LASTEXITCODE"
}

Write-Host "Deploy completed!" -ForegroundColor Green
