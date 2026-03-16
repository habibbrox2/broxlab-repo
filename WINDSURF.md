# Windsurf AI Instructions — BroxBhai

Primary context:
- `AGENTS.md`
- `docs/PROJECT_CONTEXT.md`
- `docs/CODING_CONVENTIONS.md`
- `docs/ai/AI_CODING_GUIDE.md`

Repo truths:
- Routing: Register in `app/Controllers/*.php` using `$router->get/post/...`.
- Views: Twig in `app/Views/`; use macros from `_macros/`.
- Generated files: Edit sources (e.g., `public_html/assets/css/src/`) and build with `npm run build`.
- Security: CSRF mandatory; no secrets in code (use `.env`).

Windsurf-specific guidance:
- For collaborative sessions, reuse helpers like `EmailHelper` or `FirebaseHelper`.
- When proposing changes, suggest minimal diffs to avoid conflicts (e.g., during rebase).
- AI features: Use `AIProvider` for multi-provider abstraction.
- Build commands: `npm run dev` for watch mode.

Checks:
- Quality: `php scripts/quality_scan.php`
- Security: `php scripts/security_scan.php`
- E2E: `npm run e2e:ai-system` (set `BROX_BASE_URL`)