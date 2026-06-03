# Copy-Paste Prompt

Continue modernizing the remaining `app/Views/admin/**/*.twig` pages so they match the current admin shell.

Rules:
- Keep public UI untouched.
- Keep all routes, form actions, `id` / `name` / `data-*` hooks, and inline JS contracts unchanged.
- Use Lucide icons only.
- Use the existing admin style language: `admin-page-header`, `admin-panel-card`, clean Tailwind buttons, cards, tables, and badges.
- Remove leftover Bootstrap-era classes, broken utility fragments, duplicate classes, and old icon packs.
- Keep styles scoped to the admin shell only.
- Do not edit generated `dist` files.

Priority order:
1. AI pages
   - `app/Views/admin/ai/article-writer.twig`
   - `app/Views/admin/ai/article-writer-stream.twig`
   - `app/Views/admin/ai/bulk-article-writer.twig`
   - `app/Views/admin/ai/chats.twig`
   - `app/Views/admin/ai/knowledge.twig`
   - `app/Views/admin/ai/system.twig`
   - `app/Views/admin/ai/test_write_ok.twig`
2. Logs and pipelines
   - `app/Views/admin/logs/activity.twig`
   - `app/Views/admin/logs/device_control.twig`
   - `app/Views/admin/logs/sms.twig`
   - `app/Views/admin/push-logs.twig`
   - `app/Views/admin/push-log-detail.twig`
   - `app/Views/admin/pipeline-runs.twig`
   - `app/Views/admin/pipeline-run-detail.twig`
   - `app/Views/admin/pipeline-failed-logs.twig`
   - `app/Views/admin/pipeline-all-logs.twig`
3. Media, services, pages
   - `app/Views/admin/media/library.twig`
   - `app/Views/admin/media/detail.twig`
   - `app/Views/admin/media/upload.twig`
   - `app/Views/admin/services/index.twig`
   - `app/Views/admin/services/forms.twig`
   - `app/Views/admin/services/view.twig`
   - `app/Views/admin/pages/list.twig`
   - `app/Views/admin/pages/view.twig`
4. Profile, security, revenue
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
5. Finishers
   - `app/Views/admin/notification-templates/form.twig`
   - `app/Views/admin/notification-templates/view.twig`
   - `app/Views/admin/applications/receipt-download.twig`

Workflow:
- Read the Twig file first.
- Preserve all functional hooks.
- Rewrite the layout into the current admin pattern.
- Rebuild CSS only if shared admin CSS changes.
- After each batch, run `git diff --check`.

Suggested prompt:
> Continue modernizing the remaining admin Twig pages to match the current admin shell. Keep public UI untouched, preserve all routes and JS hooks, use Lucide icons only, and refactor the pages into the `admin-page-header` + `admin-panel-card` style with clean Tailwind buttons, tables, badges, and forms. Prioritize AI, logs, pipelines, media, services, pages, profile, security, revenue, and the remaining notification template/application receipt screens. Remove leftover Bootstrap-era classes and malformed utility fragments, keep styling admin-scoped only, and run `git diff --check` after each batch. Rebuild CSS only if shared admin CSS changes.

