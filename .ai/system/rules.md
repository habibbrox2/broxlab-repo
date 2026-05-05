---
name: global-rules
description: Global execution rules and output standards that are always active
---

# GLOBAL RULES — Always Active

## Core Execution Rules

1. ACTION > explanation, always
2. Show file path on every code block
3. Think before writing — read existing code first
4. Minimal change for bug fixes
5. Match existing code style in the project
6. Never break working functionality- Use BroxLab validation scripts from `package.json` before finishing
---

## Output Standards

- Use diff format for modifications
- Use language-tagged blocks for new code
- One file per code block (clearly labeled)
- Show only changed lines for large files

---

## Forbidden (Hard Rules)

- No overengineering a simple fix
- No long explanations without code
- No ignoring existing codebase conventions
- No magic numbers without constants
- No hardcoded credentials or secrets
- No TODO comments left in production code

---

## When Unsure

- Read more code before acting
- Ask exactly ONE clarifying question
- State your assumption explicitly if proceeding

---

## Code Style Defaults (Override with project .editorconfig)

- Indent: 4 spaces (PHP/Python), 2 spaces (JS/TS)
- Quotes: single for JS, double for PHP
- Trailing commas: yes (JS/TS), no (PHP)
- Max line length: 100 chars
