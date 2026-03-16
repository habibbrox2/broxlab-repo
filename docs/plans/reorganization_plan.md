# Project Reorganization Plan

## Overview
This plan outlines the reorganization of the BroxBhai project files according to the intended structure defined in `docs/PROJECT_CONTEXT.md`.

## Current Structure Issues Identified

### 1. Misplaced Files in app/Views/
According to PROJECT_CONTEXT.md, the Views directory should only contain:
- `public/` - Public-facing pages
- `admin/` - Admin panel
- `auth/` - Authentication pages
- `_macros/` - Reusable Twig macros

**Issues Found:**
- `app/Views/error.twig` - Should be in `public/` (duplicate exists)
- `app/Views/faq.twig` - Should be in `public/` (duplicate exists)
- `app/Views/maintenance.twig` - Should be in `public/` (duplicate exists)
- `app/Views/layout.twig` - Base layout, can stay in Views root
- Additional directories not in intended structure: `comments/`, `mobiles/`, `pages/`, `partials/`, `pdf/`, `posts/`, `services/`, `user/`

### 2. Misplaced Files in Root Directory
- `deploy.sh` - Should be in `scripts/`
- `DEPLOYMENT_GUIDE.md` - Should be in `docs/`
- `GENERAL_AGENT_INSTRUCTIONS.md` - Should be in `docs/`
- `KILO_CODE.md` - Should be in `docs/`
- `plans/` directory - Should be in `docs/` or removed

## Reorganization Actions

### Action 1: Clean up app/Views/ duplicates
- [ ] Remove `app/Views/error.twig` (already exists in public/)
- [ ] Remove `app/Views/faq.twig` (already exists in public/)
- [ ] Remove `app/Views/maintenance.twig` (already exists in public/)

### Action 2: Move misplaced root files
- [ ] Move `deploy.sh` to `scripts/deploy.sh`
- [ ] Move `DEPLOYMENT_GUIDE.md` to `docs/DEPLOYMENT_GUIDE.md`
- [ ] Move `GENERAL_AGENT_INSTRUCTIONS.md` to `docs/GENERAL_AGENT_INSTRUCTIONS.md`
- [ ] Move `KILO_CODE.md` to `docs/KILO_CODE.md`
- [ ] Move `plans/` contents to `docs/plans/` or merge with existing docs

### Action 3: Keep as-is (already correct)
- `app/Controllers/` - Correct
- `app/Models/` - Correct
- `app/Helpers/` - Correct
- `app/Middleware/` - Correct
- `app/Modules/` - Correct
- `app/FeatureFlags/` - Correct
- `app/Routes/` - Correct
- `app/Telegram/` - Correct
- `Config/` - Correct
- `public_html/` - Correct
- `scripts/` - Correct
- `storage/` - Correct
- `system/` - Correct
- `docs/` - Correct
- `.github/` - Correct
- `.cursorrules`, `.windsurfrules` - Editor configs, can stay
- `AGENTS.md`, `CLAUDE.md`, `CURSOR.md`, `WINDSURF.md` - Agent instructions, can stay in root
- `README.md` - Can stay in root
- `version.json` - Can stay in root

## Diagram

```mermaid
graph TD
    A[Current Root] -->|Move| B[Target Location]
    
    A deploy.sh --> B scripts/deploy.sh
    A DEPLOYMENT_GUIDE.md --> B docs/DEPLOYMENT_GUIDE.md
    A GENERAL_AGENT_INSTRUCTIONS.md --> B docs/GENERAL_AGENT_INSTRUCTIONS.md
    A KILO_CODE.md --> B docs/KILO_CODE.md
    A plans/ --> B docs/plans/
    
    C[app/Views/] -->|Remove duplicates| D[Already in public/]
    
    C error.twig --> D public/error.twig
    C faq.twig --> D public/faq.twig
    C maintenance.twig --> D public/maintenance.twig
```

## Summary
- **Files to remove:** 3 (duplicates in app/Views/)
- **Files to move:** 5 (from root to scripts/docs)
- **Files to keep as-is:** Most of the project is already correctly organized

This reorganization will align the project with the intended structure in PROJECT_CONTEXT.md and remove duplicate/misplaced files.
