---
name: broxlab-workspace
description: BroxLab workspace agent instructions for PHP, Node.js, frontend, and DevOps development
---

# BroxLab Workspace Instructions

This is the main workspace configuration for coding agents working on the BroxLab project.

## Quick Start

**Read first:**
1. `AGENTS.md` - Agent guardrails and project structure
2. `README.md` - Project overview
3. `SECURITY.md` - Security guidelines
4. `.ai/` folder - Agent-specific and system instructions

## Project Structure

- **Entry**: `public_html/index.php`
- **Routes**: `app/Routes/Router.php`
- **Controllers**: `app/Controllers/`
- **Models**: `app/Models/`
- **Helpers**: `app/Helpers/`
- **Middleware**: `app/Middleware/`
- **Views**: `app/Views/` (Twig templates)
- **Node Service**: `src/`
- **Build Tools**: `build/`
- **Frontend**: `public_html/assets/`
- **Generated Assets**: `public_html/assets/**/dist/`
- **System Prompts**: `system/prompts/`

## Core Rules

✅ **Do:**
- Use prepared statements and explicit SQL columns
- Validate all user input
- Keep CSRF tokens on mutating actions
- Reuse existing helpers/models first
- Run `npm run validate` before committing
- Rebuild assets after source changes

❌ **Don't:**
- Commit secrets or credentials
- Edit generated `dist/` files directly
- Break working functionality
- Skip security checks

## Validation Commands

```bash
# PHP syntax check
php -l path/to/file.php

# Linting and type checking
npm run lint
npm run type-check

# Full validation gate
npm run validate

# Tests and assets
npm run test:run
npm run check:assets

# Build and start
npm run build
npm run build:prod
npm start
```

## Agent Selection

Choose the right agent for your task:

- **core-agent**: Main coordinator for complex tasks
- **backend-agent**: PHP, APIs, databases, authentication
- **frontend-agent**: HTML, CSS, JavaScript, UI/UX
- **devops-agent**: Deployment, CI/CD, infrastructure
- **reviewer-agent**: Code review and quality assurance

## Additional Resources

- Use prompts in `.ai/prompts/` for structured task templates
- Check `.ai/system/` for workflow rules and context
- Refer to SECURITY.md for authentication and data protection
