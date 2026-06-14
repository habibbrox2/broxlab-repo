# BroxLab Copilot Instructions

**First, read:** [`CORE_RULES.md`](CORE_RULES.md) → [`AGENTS.md`](AGENTS.md) → [`README.md`](README.md)

## Quick Rules

- Follow [`CORE_RULES.md`](CORE_RULES.md) for all development rules.
- Prepared statements, explicit columns, soft deletes, CSRF protection.
- Never edit `public_html/assets/**/dist/` — edit source files only.
- Rebuild: `npm run build:prod` after frontend changes.
- Validate: `npm run validate` before finishing.

## Code Structure

| Layer | Path | Purpose |
|-------|------|---------|
| Routes | `app/Controllers/` | HTTP handlers + middleware |
| Data | `app/Models/` | Database access |
| Utils | `app/Helpers/` | Shared helpers |
| Views | `app/Views/` | Twig templates |
| Frontend | `public_html/assets/{js,css}/` | Source files (never edit `dist/`) |
| Node/TS | `src/` | Backend services |

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
