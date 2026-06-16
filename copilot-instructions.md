# BroxLab Copilot Instructions

**First, read:** [`CORE_RULES.md`](CORE_RULES.md) → [`AGENTS.md`](AGENTS.md) → [`README.md`](README.md)

## Quick Rules

- Follow [`CORE_RULES.md`](CORE_RULES.md) for all development rules.
- Prepared statements, explicit columns, soft deletes, CSRF protection.
- Never edit `public_html/assets/**/dist/` — edit source files only.
- Rebuild: `npm run build:prod` after frontend changes.
- Validate: `npm run validate` before finishing.

## Code Structure

This project uses MVC with 50 Controllers, 51 Models, 25 Helpers, 244 Views, and 74 SQL schema files.

| Layer | Path | Count | Purpose |
|-------|------|-------|---------|
| Routes | `app/Routes/Router.php` | 1 | Custom regex router, middleware-aware |
| Controllers | `app/Controllers/*.php` | 50 | HTTP handlers + middleware |
| Models | `app/Models/*.php` | 51 | Database access (Mysqli, prepared statements) |
| Services | `app/Services/` | — | Business logic |
| Helpers | `app/Helpers/*.php` | 25 | Shared utilities |
| Views | `app/Views/` | 244 | Twig templates |
| Middleware | `app/Middleware/` | 2+ | Auth, CSRF, rate limit |
| Modules | `app/Modules/` | 2 | PdfTools, AISystem |
| Config | `Config/` | 8 | Twig, DB, uploads, constants |
| Database | `Database/*.sql` | 74 | One SQL file per table |
| Frontend | `public_html/assets/{js,css}/` | 108 JS | Source files (never edit `dist/`) |
| RTE | `public_html/rtceditor/` | 16 JS | Source files + esbuild bundle |

## Workflow

1. Read relevant code first.
2. Make smallest change that solves the issue.
3. Verify syntax immediately: `php -l`, `npm run lint`.
4. Rebuild assets if frontend changed.
5. Run `npm run validate` before finishing.
6. Ask at most one clarifying question if blocked.

## Style

- Be concise, technical, direct.
- Reference exact file paths.
- Prefer code over explanation.
- Keep diffs small and relevant.
