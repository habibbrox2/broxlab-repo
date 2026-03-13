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

if ($DryRun) {
    Write-Host "=== DRY RUN ===" -ForegroundColor Yellow
    Write-Host "Will set up Git bare repository on remote server"
    Write-Host "SSH Host: $sshHost"
    Write-Host "SSH User: $sshUser"
    Write-Host "SSH Port: $sshPort"
    exit 0
}

Write-Host "Setting up Git-based deployment on server..." -ForegroundColor Green

# Read the setup script
$setupScript = Join-Path $PSScriptRoot "setup_git_deploy.sh"
if (!(Test-Path $setupScript)) {
    throw "Setup script not found: $setupScript"
}

$scriptContent = Get-Content -Path $setupScript -Raw

# Build SSH args
$sshArgs = @()
if ($sshKey) { 
    $sshArgs += "-i"
    $sshArgs += $sshKey
}
$sshArgs += "-p"
$sshArgs += $sshPort
$sshArgs += "${sshUser}@${sshHost}"

Write-Host "Executing setup script on remote server..."
& ssh @sshArgs bash @"
$scriptContent
"@

if ($LASTEXITCODE -ne 0) {
    throw "Setup failed with exit code $LASTEXITCODE"
}

Write-Host "Server setup complete!" -ForegroundColor Green
