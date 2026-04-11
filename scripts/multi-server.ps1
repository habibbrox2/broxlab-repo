# Multi-Server Manager for PowerShell
# Run multiple Node.js server instances simultaneously

param(
    [switch]$Stop,
    [switch]$Status
)

function Start-MultiServer {
    Write-Host "🚀 Starting multiple Node.js server instances..." -ForegroundColor Green
    Write-Host ""

    # Check if Node.js is available
    try {
        $null = node --version
    } catch {
        Write-Host "❌ Node.js is not installed or not in PATH" -ForegroundColor Red
        exit 1
    }

    # Start servers in background jobs
    Write-Host "🔄 Starting servers..." -ForegroundColor Yellow

    # Unified Server 1
    $job1 = Start-Job -ScriptBlock {
        Set-Location $using:PWD
        $env:PORT = "3002"
        & node ./node_modules/tsx/dist/cli.mjs src/index.ts
    } -Name "UnifiedServer-3002"

    # Unified Server 2
    $job2 = Start-Job -ScriptBlock {
        Set-Location $using:PWD
        $env:PORT = "3003"
        & node ./node_modules/tsx/dist/cli.mjs src/index.ts
    } -Name "UnifiedServer-3003"

    # AI Assistant 1
    $job3 = Start-Job -ScriptBlock {
        Set-Location $using:PWD
        $env:PORT = "3001"
        $env:AI_ASSISTANT_PORT = "3001"
        & node ./node_modules/tsx/dist/cli.mjs src/index.ts
    } -Name "AIAssistant-3001"

    # AI Assistant 2
    $job4 = Start-Job -ScriptBlock {
        Set-Location $using:PWD
        $env:PORT = "3004"
        $env:AI_ASSISTANT_PORT = "3004"
        & node ./node_modules/tsx/dist/cli.mjs src/index.ts
    } -Name "AIAssistant-3004"

    Write-Host ""
    Write-Host "✅ All servers started in background jobs!" -ForegroundColor Green
    Write-Host ""
    Write-Host "📋 Server URLs:" -ForegroundColor Cyan
    Write-Host "  • Unified Server 1: http://localhost:3002"
    Write-Host "  • Unified Server 2: http://localhost:3003"
    Write-Host "  • AI Assistant 1:  http://localhost:3001"
    Write-Host "  • AI Assistant 2:  http://localhost:3004"
    Write-Host ""
    Write-Host "💡 Use Get-Job to see running jobs" -ForegroundColor Yellow
    Write-Host "💡 Use Stop-Job to stop jobs" -ForegroundColor Yellow
    Write-Host "💡 Use Remove-Job to clean up completed jobs" -ForegroundColor Yellow
    Write-Host ""

    # Return job objects for management
    return @($job1, $job2, $job3, $job4)
}

function Stop-MultiServer {
    Write-Host "🛑 Stopping all server jobs..." -ForegroundColor Yellow

    $jobs = Get-Job | Where-Object { $_.Name -like "*Server*" -or $_.Name -like "*Assistant*" }
    if ($jobs.Count -eq 0) {
        Write-Host "ℹ️  No server jobs found running" -ForegroundColor Blue
        return
    }

    $jobs | Stop-Job
    $jobs | Remove-Job

    Write-Host "✅ All server jobs stopped and cleaned up" -ForegroundColor Green
}

function Show-Status {
    Write-Host "📊 Server Status:" -ForegroundColor Cyan

    $jobs = Get-Job | Where-Object { $_.Name -like "*Server*" -or $_.Name -like "*Assistant*" }

    if ($jobs.Count -eq 0) {
        Write-Host "  ℹ️  No server jobs found" -ForegroundColor Blue
        return
    }

    foreach ($job in $jobs) {
        $status = switch ($job.State) {
            "Running" { "🟢 Running" }
            "Completed" { "✅ Completed" }
            "Failed" { "❌ Failed" }
            "Stopped" { "🛑 Stopped" }
            default { "⚪ " + $job.State }
        }
        Write-Host "  • $($job.Name): $status" -ForegroundColor White
    }
}

# Main execution
if ($Stop) {
    Stop-MultiServer
} elseif ($Status) {
    Show-Status
} else {
    $jobs = Start-MultiServer

    # Keep the script running and show periodic status
    Write-Host "💡 Press Ctrl+C to exit (servers will keep running in background)" -ForegroundColor Yellow
    Write-Host ""

    try {
        while ($true) {
            Start-Sleep -Seconds 10
            Show-Status
            Write-Host ""
        }
    } catch {
        Write-Host ""
        Write-Host "👋 Exiting... (servers are still running in background)" -ForegroundColor Yellow
    }
}
