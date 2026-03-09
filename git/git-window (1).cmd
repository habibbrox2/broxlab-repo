@echo off
setlocal
title BroxBhai Git Terminal
cd /d "%~1"
node "%~2"
echo.
echo Git terminal session ended. Press any key to close...
pause >nul
