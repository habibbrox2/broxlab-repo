@echo off
REM Same Port Multi-Server Setup for Windows
REM একই পোর্টে একাধিক Node.js সার্ভার চালানোর স্বয়ংক্রিয় সেটাপ

echo 🚀 একই পোর্টে মাল্টি-সার্ভার সেটাপ শুরু হচ্ছে...
echo.

REM Check if Node.js is available
node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Node.js ইনস্টল করা নেই
    pause
    exit /b 1
)

REM Check if npm is available
npm --version >nul 2>&1
if errorlevel 1 (
    echo ❌ npm ইনস্টল করা নেই
    pause
    exit /b 1
)

REM Install dependencies
echo 🔧 Dependencies ইনস্টল হচ্ছে...
npm install http-proxy-middleware http-proxy load-balancers
if errorlevel 1 (
    echo ❌ Dependencies ইনস্টল করতে ব্যর্থ
    pause
    exit /b 1
)

echo ✅ Dependencies ইনস্টল সম্পন্ন
echo.

REM Start servers
echo ▶️  সার্ভারগুলো চালু হচ্ছে...

REM Start Unified Server on port 3001
echo 🔄 Unified Server চালু হচ্ছে (Port: 3001)...
start "Unified Server" cmd /c "set PORT=3001 && node ./node_modules/tsx/dist/cli.mjs src/index.ts"

REM Start AI Assistant on port 3002
echo 🔄 AI Assistant চালু হচ্ছে (Port: 3002)...
start "AI Assistant" cmd /c "set PORT=3002 && set AI_ASSISTANT_PORT=3002 && node ./node_modules/tsx/dist/cli.mjs src/index.ts"

REM Wait for servers to start
timeout /t 3 /nobreak >nul

REM Start Express Reverse Proxy on port 3000
echo 🔄 Express Reverse Proxy চালু হচ্ছে (Port: 3000)...
start "Reverse Proxy" cmd /c "set PORT=3000 && node src/reverse-proxy.js"

echo.
echo ✅ সব সার্ভার চালু হয়েছে!
echo.
echo 📋 অ্যাক্সেস URLs:
echo   🌐 Main Server: http://localhost:3000
echo   🔧 API Routes: http://localhost:3000/api/*
echo   🤖 AI Routes:  http://localhost:3000/ai/*
echo   ❤️  Health:    http://localhost:3000/health
echo.
echo 💡 প্রতিটি command window আলাদা রাখুন
echo 💡 সার্ভার বন্ধ করতে window গুলো close করুন
echo.
echo 📖 আরও জানতে: docs/guides/same-port-servers.md
echo.

pause
