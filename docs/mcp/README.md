# BroxLab Remote MCP Server

A production-ready, **read-only** Remote MCP (Model Context Protocol) server
for BroxLab, exposing public content to ChatGPT and any MCP-compatible AI
client.

- **Endpoint:** `POST https://broxlab.online/mcp`
- **Health check:** `GET https://broxlab.online/mcp/health`
- **Transport:** Streamable HTTP (JSON-RPC 2.0), MCP protocol `2025-06-18`
- **Auth:** Bearer token (`Authorization: Bearer <MCP_API_KEY>`) or `X-API-Key` header
- **Mode:** strictly read-only

## 1. Architecture

```
ChatGPT / MCP client
        │ HTTPS + Bearer key
        ▼
POST https://broxlab.online/mcp        (routes/web.php)
        │
        ▼
McpController (app/Http/Controllers/McpController.php)
   ├── fail-closed gate        config('mcp.enabled') + MCP_API_KEY required
   ├── constant-time auth      hash_equals over configured keys (rotation via comma-separated MCP_API_KEY)
   ├── rate limiting           Laravel RateLimiter, per key, MCP_RATE_LIMIT/min
   ├── JSON-RPC dispatch       initialize / ping / tools/list / tools/call
   ├── schema validation       McpSchema (types, enums, maxLength, min/max)
   └── structured logging      request_id, tool, client hash, duration, outcome (no secrets)
        │
        ▼
McpTools (app/Support/Mcp/McpTools.php)   ← the ONLY new data layer
   ├── reuses App\Support\PostsService        (public published posts)
   ├── reuses App\Support\MobileService       (mobile catalog + specs)
   ├── reuses App\Support\PostTaxonomy        (categories / tags)
   └── read-only caching via Laravel Cache (MCP_CACHE_TTL)
        │
        ▼
Existing MySQL database (no schema changes)
```

Design constraints honored:

- **Additive only.** No existing route, controller, service, or table changed.
- **Isolated.** All MCP code lives in `app/Support/Mcp/`, one controller, one config file, two routes.
- **Reuses business logic.** `PostsService::posts()` already enforces `published=1`;
  `postBySlug` results are additionally gated on `published === 1` inside `McpTools::getArticle()`.
- **Content is data.** Article/device content is returned as plain text. It is never
  interpreted, executed, or treated as instructions (prompt-injection safe by construction).

## 2. Installation

Nothing to install — no new Composer or npm dependencies. The server is plain
Laravel 12 / PHP 8.2 code compatible with the existing cPanel deployment.

```bash
# after deploy
php artisan config:clear
php artisan route:clear
```

## 3. Configuration

Add to the production `.env` (never commit it):

```env
MCP_ENABLED=true
MCP_API_KEY=<openssl rand -hex 32>          # generate: php -r "echo bin2hex(random_bytes(32));"
# Optional — unlocks write tools (drafts, categories, devices, replies):
MCP_WRITE_API_KEY=<openssl rand -hex 32>
MCP_RATE_LIMIT=60                           # requests/minute per key
MCP_CACHE_TTL=300                           # seconds; 0 disables cache
MCP_LOGGING_ENABLED=true
```

Key rotation: `MCP_API_KEY="old-key,new-key"` — both work during rotation;
remove the old one after clients are updated. Revocation = removing the key.

## 4. Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `MCP_ENABLED` | `false` | Fail-closed master switch |
| `MCP_API_KEY` | *(empty)* | Comma-separated valid keys; empty = all requests refused |
| `MCP_WRITE_API_KEY` | *(empty)* | Comma-separated write-scope keys; empty = write tools disabled |
| `MCP_RATE_LIMIT` | `60` | Requests per minute per key (0 disables) |
| `MCP_CACHE_TTL` | `300` | Cache seconds for categories/site-info/latest |
| `MCP_LOGGING_ENABLED` | `true` | Structured request logging |

## 5. Available tools

See [TOOLS.md](TOOLS.md) for the full table and per-tool schemas.

**Read tools (MCP_API_KEY):**
`get_site_info`, `search_articles`, `get_article`, `get_latest_articles`,
`list_categories`, `get_category_articles`, `search_devices`, `get_device`,
`get_device_specs`, `compare_devices`, `search_site`

**Write tools (MCP_WRITE_API_KEY — hidden from read keys, FORBIDDEN if called):**
`create_article_draft`, `create_category`, `create_device`,
`reply_to_comment`, `list_pending_comments`, `get_site_report`,
`get_activity_logs`

### Write-scope security model

- **Two key tiers.** `MCP_API_KEY` = read-only. `MCP_WRITE_API_KEY` = read +
  write. A read key never sees write tools in `tools/list` and gets
  `FORBIDDEN` calling one; write keys see all 18 tools.
- **Draft-only publishing.** `create_article_draft` always inserts
  `published=0` — a human publishes from the admin panel. Drafts created via
  MCP are invisible to the public site and to MCP read tools.
- **Moderation queue parity.** `reply_to_comment` posts official replies with
  `status='pending'` — moderators review before they appear.
- **Full audit trail.** Every mutation is journaled to the existing
  `activity_logs` table (`mcp.*` actions) and is queryable through the
  `get_activity_logs` tool itself.
- **Duplicate protection.** `create_device` rejects an existing
  brand+model combination.

## 6. Example session

```jsonc
// 1. initialize
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"chatgpt","version":"1"}}}
// → {"jsonrpc":"2.0","result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{}},"serverInfo":{"name":"broxlab-mcp","version":"1.0.0"}},"id":1}

// 2. tools/list
{"jsonrpc":"2.0","id":2,"method":"tools/list"}
// → 11 tool definitions with strict inputSchema

// 3. tools/call
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"search_devices","arguments":{"query":"Galaxy","limit":5}}}
// → {"content":[{"type":"text","text":"{\"results\":[…],\"pagination\":{…}}"}]}
```

## 7. Error codes

| Code | Meaning |
|---|---|
| `-32700` | Malformed JSON-RPC body |
| `-32600` | Invalid request (body too large, etc.) |
| `-32601` | Method or tool not found |
| `-32602` | Invalid arguments (missing required, bad type, over limit) |
| `-32603` | Internal error (sanitized — no details) |
| `-32001` | Unauthorized (missing/invalid key, or service disabled) |
| `-32002` | Rate limited (HTTP 429 + `Retry-After`) |

Errors never include SQLSTATE, file paths, stack traces, or configuration.

## 8. Security

- Fail-closed: disabled without `MCP_ENABLED=true` **and** a non-empty key list.
- Constant-time key comparison; keys never appear in responses or logs.
- Strict input validation: type checks, `maxLength`, enum, numeric min/max.
- Unknown argument fields are dropped; unknown tools rejected.
- Read-only: no tool mutates state; no arbitrary execution tools exist.
- Public data only: published posts, public catalog devices, active categories.
- Content is plain text (script/style stripped) — treated as untrusted data.
- Canonical URLs only (`https://broxlab.online/...`), derived from `APP_URL`.

## 9. Local testing

```bash
vendor/bin/phpunit tests/Feature/Mcp/McpServerTest.php       # 34 read/protocol tests
vendor/bin/phpunit tests/Feature/Mcp/McpWriteToolsTest.php   # 16 write-scope tests
php artisan test                                          # full suite
```

Manual smoke test:

```bash
curl -s https://broxlab.online/mcp/health
curl -s -X POST https://broxlab.online/mcp \
  -H "Authorization: Bearer $MCP_API_KEY" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

## 10. Production deployment

Ships through the existing GitHub Actions → SSH → `web-host/scripts/deploy.sh`
pipeline on push to `main`. The two new routes and config file are picked up by
the normal `composer install` / route-clear steps. Set the `MCP_*` variables in
the production `.env` before enabling.

## 11. ChatGPT connection

1. ChatGPT → **Settings → Connectors / Actions** (developer/custom-connector
   feature availability depends on plan; ChatGPT Plus/Team/Enterprise with
   developer mode or the Apps SDK is required for remote MCP).
2. Add a **remote MCP server** with URL `https://broxlab.online/mcp`.
3. Authentication: **Bearer token**, paste `MCP_API_KEY`.
4. Save, then verify the tool list appears (11 tools).
5. Test with *"Search BroxLab articles about mobile prices."*

## 12. Troubleshooting

| Symptom | Cause |
|---|---|
| HTTP 503 on `/mcp` | `MCP_ENABLED` not `true` |
| HTTP 401 | Missing/invalid key, or `MCP_API_KEY` empty |
| HTTP 419 | CSRF exception missing (should not happen; `mcp` is exempted in `bootstrap/app.php`) |
| `error.code: -32602` | Client sent invalid arguments — see message |
| Tools empty / NOT_FOUND | No matching public content |

## 13. Limitations & Phase 2 ideas

- Read-only; write tools (draft creation etc.) intentionally deferred — the
  tool registry (`McpSchema::definitions()`) is the single extension point.
- `search_articles` category filtering happens post-hydration (small result
  pages, acceptable); a JOIN-based filter is a Phase 2 optimization.
- `compare_devices` matches by slug → model-name search (mobiles table has no
  slug column); consider adding one in a future migration.
- Rate limiting uses the framework cache/array limiter — Redis-backed if the
  host provides it, file/array otherwise (still safe on shared hosting).
