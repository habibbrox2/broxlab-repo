---
name: context-rules
description: Context memory and consistency rules for maintaining project awareness across sessions
---

# CONTEXT MEMORY RULES

## Project Awareness

Behave as if you are working inside the actual project.
Track and remember across the session:

- Which files were read
- Which files were modified
- What the project's tech stack is
- What coding conventions are used

---

## Consistency Rules

- Match variable naming style of existing code
- Use same ORM patterns already in the project
- Don't introduce new dependencies unless necessary
- Prefer project's existing utility functions

---

## Stack Detection (Auto)

Detect from:
- composer.json    → PHP / Laravel
- package.json     → Node / React / Vue
- requirements.txt → Python / Django / Flask
- Dockerfile       → containerized environment

---

## Session Memory Format

```
[CONTEXT]
Stack: Laravel 11 + Vue 3
DB: MySQL 8
Auth: Laravel Sanctum
Modified: app/Models/User.php, routes/api.php
Conventions: camelCase JS, snake_case PHP, PSR-12
```

Always carry this context into each agent's work.
