<#
.SYNOPSIS
Clones the repository, installs npm deps, and restores the database from latest.sql.

.PARAMETER RepoUrl
The Git repository URL to clone.

.PARAMETER Destination
Target directory to clone into. Defaults to current folder.

.EXAMPLE
.
  .\scripts\clone-and-restore.ps1 -RepoUrl "https://github.com/your/repo.git" -Destination "C:\projects\broxbhai"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$RepoUrl,

    [string]$Destination = "./broxbhai",

    [string]$Branch = "main"
)

function Run-Command {
    param(
        [string]$Command
    )

    Write-Host "> $Command" -ForegroundColor Cyan
    $proc = Start-Process -FilePath pwsh -ArgumentList "-NoProfile", "-Command", $Command -NoNewWindow -Wait -PassThru
    if ($proc.ExitCode -ne 0) {
        throw "Command failed with exit code $($proc.ExitCode): $Command"
    }
}

$destPath = Resolve-Path -LiteralPath $Destination -ErrorAction SilentlyContinue
if (-not $destPath) {
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    $destPath = Resolve-Path -LiteralPath $Destination
}

Write-Host "Cloning $RepoUrl into $destPath" -ForegroundColor Green
Run-Command "git clone --branch $Branch --depth 1 `"$RepoUrl`" `"$destPath`""

Write-Host "Installing npm dependencies..." -ForegroundColor Green
Run-Command "cd `"$destPath`"; npm install"

Write-Host "Restoring database from Database/full/latest.sql..." -ForegroundColor Green
Run-Command "cd `"$destPath`"; npm run restore-db -- --yes"

Write-Host "✅ Clone + restore complete." -ForegroundColor Green
    <#
    .SYNOPSIS
    Clones the repository, installs npm deps, and restores the database from latest.sql.

    .PARAMETER RepoUrl
    The Git repository URL to clone.

    .PARAMETER Destination
    Target directory to clone into. Defaults to current folder.

    .EXAMPLE
    .
    .\scripts\clone-and-restore.ps1 -RepoUrl "https://github.com/your/repo.git" -Destination "C:\projects\broxbhai"
    #>

    param(
        [Parameter(Mandatory=$true)]
        [string]$RepoUrl,

        [string]$Destination = "./broxbhai",

        [string]$Branch = "main"
    )

    function Run-Command {
        param(
            [string]$Command
        )

        Write-Host "> $Command" -ForegroundColor Cyan
        $proc = Start-Process -FilePath pwsh -ArgumentList "-NoProfile", "-Command", $Command -NoNewWindow -Wait -PassThru
        if ($proc.ExitCode -ne 0) {
            throw "Command failed with exit code $($proc.ExitCode): $Command"
        }
    }

    $destPath = Resolve-Path -LiteralPath $Destination -ErrorAction SilentlyContinue
    if (-not $destPath) {
        New-Item -ItemType Directory -Path $Destination -Force | Out-Null
        $destPath = Resolve-Path -LiteralPath $Destination
    }

    Write-Host "Cloning $RepoUrl into $destPath" -ForegroundColor Green
    Run-Command "git clone --branch $Branch --depth 1 `"$RepoUrl`" `"$destPath`""

    Write-Host "Installing npm dependencies..." -ForegroundColor Green
    Run-Command "cd `"$destPath`"; npm install"

    Write-Host "Restoring database from Database/full/latest.sql..." -ForegroundColor Green
    Run-Command "cd `"$destPath`"; npm run restore-db -- --yes"

    Write-Host "✅ Clone + restore complete." -ForegroundColor Green
