@echo off
REM Multi-Server Manager for Windows
REM Run multiple Node.js server instances simultaneously

echo 🚀 Starting multiple Node.js server instances...
echo.

REM Check if Node.js is available
node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Node.js is not installed or not in PATH
    pause
    exit /b 1
)

REM Start servers in parallel
echo 🔄 Starting Unified Server on port 3002...
start "Unified Server 3002" cmd /c "set PORT=3002 && node ./node_modules/tsx/dist/cli.mjs src/index.ts"

echo 🔄 Starting Unified Server on port 3003...
start "Unified Server 3003" cmd /c "set PORT=3003 && node ./node_modules/tsx/dist/cli.mjs src/index.ts"

echo 🔄 Starting AI Assistant on port 3001...
start "AI Assistant 3001" cmd /c "set PORT=3001 && set AI_ASSISTANT_PORT=3001 && node ./node_modules/tsx/dist/cli.mjs src/index.ts"

echo 🔄 Starting AI Assistant on port 3004...
start "AI Assistant 3004" cmd /c "set PORT=3004 && set AI_ASSISTANT_PORT=3004 && node ./node_modules/tsx/dist/cli.mjs src/index.ts"

echo.
echo ✅ All servers starting in background windows!
echo.
echo 📋 Server URLs:
echo   • Unified Server 1: http://localhost:3002
echo   • Unified Server 2: http://localhost:3003
echo   • AI Assistant 1:  http://localhost:3001
echo   • AI Assistant 2:  http://localhost:3004
echo.
echo 💡 Close the command windows to stop individual servers
echo 💡 Press Ctrl+C here to exit (servers will keep running)
echo.

REM Keep the script running
pause >nul
