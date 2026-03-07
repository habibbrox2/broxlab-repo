# BroxBhai Workspace Instructions

This document gives Copilot/assistant agents a quick orientation to the BroxBhai codebase so they can start coding and answering questions immediately. It is not meant for humans.

---

## High-Level Architecture

- **Backend**: PHP MVC using custom router and Twig views located under `app/`.
  - Controllers in `app/Controllers/*Controller.php`
  - Models under `app/Models`
  - Views (Twig templates) under `app/Views`
  - Public entry point is `public_html/index.php` with assets in `public_html/assets`.
  - RBAC endpoint lives under `/api/rbac/check-permission/...` and is used by client-side scripts.
  - SQL schema files under `Database/` for reference.

- **Frontend**: JavaScript assets compiled by esbuild.
  - Firebase-based assistants (`admin-assistant.js`, `public-assistant.js`) in `public_html/assets/firebase/v2` with `dist` subfolder for built output.
  - App-specific scripts compiled from `public_html/assets/js` via another esbuild target.
  - Styles using Tailwind; config in `build/tailwind.config.js`.
  - Build tools and scripts under `build/` directory.

- **AI Integration**: Uses Firebase GoogleAI backend (`@genkit-ai/googleai`) for Gemini model calls. Assistants communicate via JSON actions and support multi-step flows.

## Build & Development

- Node/npm is used for building frontend assets. `package.json` defines scripts:
  - `npm run dev` – runs watchers for firebase assets, app JS, and tailwind concurrently.
  - `npm run build` – clean + sequential build of firebase assets, app JS, and tailwind (production minification with `build:prod`).
  - `npm run build:firebase:v2` (and variants) – compile the Firebase assistant scripts and place output in `public_html/assets/firebase/v2/dist`.
  - Always **edit the source files** in `public_html/assets/firebase/v2/` (not `dist/`) and rebuild.

- PHP code does not require build but may use composer (already installed vendor/). Use local PHP server or existing environment for testing.

- To start a simple static server: `npm run serve` (Python http.server) serving `public_html`.

## Key Directories and Files

```
app/           # MVC app code
public_html/   # web root; assets, entrypoints, generated dist
  assets/firebase/v2   # chat assistant scripts
    public-assistant.js
    admin-assistant.js
    dist/             # built bundles
  assets/js            # other frontend code
  assets/css           # tailwind input/output
build/         # build configs and utilities
Database/      # SQL schema dumps
vendor/        # PHP dependencies
```

## Naming & Conventions

- JavaScript uses `camelCase` functions and `async/await` style.
- Permissions are strings like `'post.create'`, stored in `ACTION_PERMISSIONS` map in assistants.
- Chat assistant actions are JSON blocks wrapped in triple backticks inside messages.
- Controllers follow `[Resource]Controller.php` naming; `insert_*`, `update_*` views and routes.

## Common Workflows

- **Add a new assistant action**: extend `ACTION_PERMISSIONS`, add handling in `executeAction()`, and add server-side route or API endpoint. Use redirect-to-form pattern for create/edit operations.
- **Implement interactive flows**: use `pendingMobileFlow` pattern as example; update `handleUserMessage()` and maybe `SYSTEM_INSTRUCTION`.
- **Prefill forms on validation error**: controllers merge `$_GET` with `session('old')` for mobile/posts/pages/services.
- **Permissions**: use `hasPermission()` helper client-side or call `/api/rbac/check-permission`; super‑admin bypass.

## Agent-Specific Notes

- When editing frontend JS, always rebuild with the appropriate npm script and mention it in responses to users.
- Avoid modifying files under `dist/` directly; they are generated.
- For PHP changes, match existing patterns (e.g. query-string handling, session old data) and run `npm run build` if you touched JS.
- Use the `run_in_terminal` tool to execute build commands when required by the task.

---

This file may be updated as the project evolves. Add new sections or examples as needed to help assistant agents remain effective.