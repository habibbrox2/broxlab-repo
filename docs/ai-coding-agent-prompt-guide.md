# AI Coding Agent Prompt Guide for Admin Twig Refresh

Use this file as the working brief for the next AI coding agent that continues the admin Twig refresh.

## Goal

Modernize the remaining admin Twig pages so they visually match the new admin shell, while keeping:
- Public UI unchanged
- Existing routes unchanged
- Existing form actions unchanged
- Existing JS hooks and `data-*` hooks unchanged
- Lucide icon usage consistent

## Read First

Before editing, review:
- `README.md`
- `AGENTS.md`
- `docs/admin-design-system.md`
- `docs/admin-ui-audit.md`
- `app/Views/admin/layout.twig`
- `public_html/assets/css/tailwind-input.css`

## Current Style Direction

The admin area now uses:
- `admin-page-header`
- `admin-panel-card`
- Tailwind utility styling scoped to `body[data-admin-dir]`
- Lucide icons, not legacy icon packs
- Clean table, badge, filter, and form patterns

When editing remaining Twig files, keep the same visual language:
- Large page header with short supporting text
- Card-based layout
- Clear filters in top panels
- Tables inside `admin-panel-card`
- Primary actions on the top right
- Secondary actions styled as white/neutral buttons

## Files To Edit Next

Priority 1:
- `app/Views/admin/ai/article-writer.twig`
- `app/Views/admin/ai/article-writer-stream.twig`
- `app/Views/admin/ai/bulk-article-writer.twig`
- `app/Views/admin/ai/chats.twig`
- `app/Views/admin/ai/knowledge.twig`
- `app/Views/admin/ai/system.twig`
- `app/Views/admin/ai/test_write_ok.twig`

Priority 2:
- `app/Views/admin/logs/activity.twig`
- `app/Views/admin/logs/device_control.twig`
- `app/Views/admin/logs/sms.twig`
- `app/Views/admin/push-logs.twig`
- `app/Views/admin/push-log-detail.twig`
- `app/Views/admin/pipeline-runs.twig`
- `app/Views/admin/pipeline-run-detail.twig`
- `app/Views/admin/pipeline-failed-logs.twig`
- `app/Views/admin/pipeline-all-logs.twig`

Priority 3:
- `app/Views/admin/media/library.twig`
- `app/Views/admin/media/detail.twig`
- `app/Views/admin/media/upload.twig`
- `app/Views/admin/services/index.twig`
- `app/Views/admin/services/forms.twig`
- `app/Views/admin/services/view.twig`
- `app/Views/admin/pages/list.twig`
- `app/Views/admin/pages/view.twig`

Priority 4:
- `app/Views/admin/profile/edit.twig`
- `app/Views/admin/profile/password.twig`
- `app/Views/admin/profile/view.twig`
- `app/Views/admin/security/2fa.twig`
- `app/Views/admin/security/2fa_setup.twig`
- `app/Views/admin/security/2fa_backup.twig`
- `app/Views/admin/revenue/dashboard.twig`
- `app/Views/admin/revenue/ads.twig`
- `app/Views/admin/revenue/donations.twig`
- `app/Views/admin/revenue/settings.twig`
- `app/Views/admin/revenue/sponsored.twig`

Priority 5:
- `app/Views/admin/notification-templates/form.twig`
- `app/Views/admin/notification-templates/view.twig`
- `app/Views/admin/notification-templates/index.twig` only if it still needs cleanup after functional review
- `app/Views/admin/applications/receipt-download.twig`
- Do not invent new admin Twig paths that do not already exist in the repository.

## Editing Rules

1. Preserve behavior first.
- Do not change routes, endpoints, or POST field names unless required.
- Do not rename existing `id`, `name`, or `data-*` attributes that JS depends on.
- If a page has inline scripts, keep the existing JS contract intact.

2. Keep the shell scoped.
- Do not touch public layout files unless explicitly asked.
- Keep admin-only classes and CSS under `body[data-admin-dir]`.

3. Use the new admin design language.
- Replace old Bootstrap-only surfaces with `admin-page-header` and `admin-panel-card`.
- Prefer `inline-flex` buttons with clear hierarchy:
  - Primary: indigo filled button
  - Secondary: white bordered button
  - Danger: rose filled or rose tinted button
- Prefer neutral bordered tables with subtle row separation.

4. Use Lucide icons.
- Replace legacy `icon-*`, `bi-*`, or other icon packs where encountered.
- Use a single icon per action unless the UI needs a compound label.

5. Clean up accidental class noise.
- Remove duplicate classes such as repeated `inline-flex`, repeated `px-*`, or mixed utility leftovers.
- Remove invalid fragments like `bg-*-subtle`, `focus:border-indigo-500-lg`, or malformed class concatenations.

6. Keep forms readable.
- Group related inputs into cards or sections.
- Add short helper text where it helps the user.
- Preserve validation messages and server-rendered errors.

7. Keep tables friendly.
- Wrap tables in `overflow-x-auto`.
- Use `table` and `thead`/`tbody` without legacy `table-light` or `table-stacked` unless a component truly depends on it.
- Use badge pills built from Tailwind classes.

## Suggested Workflow For Each Page

1. Read the current Twig file.
2. Identify any JS hooks, form field names, and `data-*` hooks that must not change.
3. Rewrite the outer page structure to the new admin shell pattern.
4. Replace icon and button styling with the scoped admin design language.
5. Keep or improve semantic structure:
   - header
   - filters
   - table/list
   - side panels
   - modals
6. Save the file.
7. Run a diff check for the touched file.
8. If styling needs new shared utilities, add them to `public_html/assets/css/tailwind-input.css` under the admin scope only.
9. Rebuild CSS if shared styles changed.

## Validation Checklist

After a batch of edits:
- Run `git diff --check`
- Run `npm run build:css` if CSS changed
- Scan for any accidental `icon-*` or `bi-*` references in the touched admin area
- Verify the public UI files were not modified

## Do Not Do

- Do not edit generated `dist` files directly.
- Do not introduce new icon libraries.
- Do not rewrite backend logic unless a Twig change reveals a real bug.
- Do not change public-facing pages while working on admin refresh.
- Do not bulk-reformat unrelated files.

## Good Prompt To Give The Next Agent

> Continue modernizing the remaining admin Twig pages to match the current admin shell. Work in batches of high-traffic pages first, keep all routes and JS hooks intact, and keep styling scoped to the admin shell. Use Lucide icons, modern Tailwind card/table/button patterns, and clean up any leftover Bootstrap-era classes or malformed utility fragments. Prioritize AI, logs, media, services, pages, profile, security, and revenue views. After each batch, run `git diff --check`, and rebuild CSS only if shared admin CSS changed.
