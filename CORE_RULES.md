---
version: 2.4.0
purpose: Single source of truth for BroxLab dev rules—referenced by all agents, skills, and instructions
---

# BroxLab Core Development Rules

> All agents must read and follow this file. Other files reference it, not duplicate it.

## Essential Rules

### PHP & SQL
- ✅ **Prepared statements** always; never raw SQL
- ✅ **Explicit columns** in SELECT (never `SELECT *`)
- ✅ **Soft deletes**: Filter with `WHERE deleted_at IS NULL`
- ✅ **Validation**: Sanitize input; use `PurifierHelper` for HTML

### HTTP & Security
- ✅ **CSRF tokens** on all POST/PUT/DELETE
- ✅ **Auth checks**: Verify `$_SESSION['user_id']`
- ✅ **Rate limiting**: Use existing middleware
- ✅ **HTTPS only**: No plaintext secrets

### Frontend Assets
- ✅ **Kebab-case** file names: `my-component.js`, `form-validator.css`
- ✅ **Never edit `dist/`** — edit source files only
- ✅ **Rebuild after changes**: `npm run build:prod` or `npm run dev`
- ✅ **Asset versioning**: Use `{{ withAssetVersion('/assets/...') }}`

### Validation Gate
**Always run before finishing:** `npm run validate`
- Syntax: `php -l`
- Linting: `npm run lint`
- Type check: `npm run type-check`
- Tests: `npm run test:run`
- Asset check: `npm run check:assets`

## Project Structure

| Layer | Location | Pattern |
|-------|----------|---------|
| Routes | `app/Controllers/` | Embedded routing, middleware-aware |
| Data | `app/Models/` | Prepared statements, explicit columns |
| Utils | `app/Helpers/` | Reusable formatting, sanitization |
| Views | `app/Views/` | Twig templates, organized by area |
| Middleware | `app/Middleware/` | Auth, CSRF, rate limit |
| Frontend | `public_html/assets/{js,css}/` | Source only; never edit `dist/` |
| Node/TS | `src/` | Unified APIs on Fastify |
| AI Prompts | `system/prompts/` | Model configs and templates |

## Workflow (Always)

1. **Read** relevant code first
2. **Make** smallest change that solves the issue
3. **Verify** syntax/lint immediately
4. **Rebuild** assets if frontend/Node changed
5. **Run** `npm run validate` before finishing
6. **Ask** at most one clarifying question if blocked

## Key Gotchas

| Issue | Fix |
|-------|-----|
| Editing `dist/` files | Edit source; rebuild with `npm run build:prod` |
| Forgetting soft delete filter | Add `WHERE deleted_at IS NULL` to every SELECT |
| Raw SQL | Use prepared statements: `prepare()` → `bind_param()` → `execute()` |
| Missing asset version | Use `{{ withAssetVersion('/path/file.js?v=...') }}` in Twig |
| Old CSS cached | Run `npm run clean && npm run build` |
| Routes scattered | Use `grep -r "\\$router->" app/Controllers/` to find all routes |
| SELECT \* in production | Always list columns: `SELECT id, name, email FROM table` |

## Special: Rich Text Editor (RTE)

- Use **`editor.bundle.js`** (not individual files)
- Never edit `editor.bundle.js` directly
- Edit source `.js` files in `public_html/rtceditor/`
- Rebuild: `npm run build:rte`
- Load in Twig: `{{ withAssetVersion('/rtceditor/editor.bundle.js') }}`
- Lazy modules: `editor.modals.js`, `editor.color.js`, `editor.images.js` (loaded on first interaction)

## Code Examples: Minimal

### Model (Prepared Statement)
```php
$stmt = $mysqli->prepare("SELECT id, name FROM table WHERE user_id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $userId);
$stmt->execute();
return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
```

### Controller Route
```php
$router->post('/api/endpoint', ['middleware' => ['auth', 'csrf']], function() use ($models) {
    $data = ['name' => sanitize_input($_POST['name'] ?? '')];
    $result = $models['Table']->create($data);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result ? true : false]);
});
```

### Frontend (Form + Fetch)
```javascript
document.getElementById('form').addEventListener('submit', (e) => {
    e.preventDefault();
    fetch('/api/endpoint', {method: 'POST', body: new FormData(e.target)})
        .then(r => r.json())
        .then(d => console.log(d.success ? 'OK' : 'Error'));
});
```

### Twig (Template)
```twig
<form method="POST" action="/api/endpoint">
    {% csrf_token %}
    <input name="name" required>
    <button type="submit">Send</button>
</form>
<script src="{{ withAssetVersion('/assets/js/dist/my-component.js') }}"></script>
```

---

**For detailed context:** Read `README.md` → `AGENTS.md` → `copilot-instructions.md` (in that order)

**For task-specific workflows:** See `.ai/` and `.kilo/` skill files

**For prompts/AI behavior:** See `system/prompts/`
