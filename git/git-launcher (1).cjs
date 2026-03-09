#!/usr/bin/env node
"use strict";

const path = require("path");
const { spawn } = require("child_process");

const targetScript = path.join(__dirname, "git-terminal.cjs");
const windowRunner = path.join(__dirname, "git-window.cmd");
const forwardedArgs = process.argv.slice(2);

function runDirect() {
  const child = spawn(process.execPath, [targetScript, ...forwardedArgs], {
    cwd: process.cwd(),
    stdio: "inherit",
  });

  child.on("exit", (code) => {
    process.exit(code ?? 0);
  });

  child.on("error", (error) => {
    console.error(error.message);
    process.exit(1);
  });
}

function openNewWindow() {
  if (process.platform !== "win32") {
    runDirect();
    return;
  }

  const child = spawn(
    "cmd.exe",
    ["/c", "start", "\"\"", windowRunner, process.cwd(), targetScript],
    {
      cwd: process.cwd(),
      detached: true,
      stdio: "ignore",
      windowsHide: false,
    }
  );

  child.unref();
}

if (forwardedArgs.length > 0) {
  runDirect();
} else {
  openNewWindow();
}
