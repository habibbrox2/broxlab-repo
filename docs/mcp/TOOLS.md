# BroxLab MCP Tools

Authentication: `Authorization: Bearer <key>` or `X-API-Key`.

- **MCP_API_KEY** (read scope): the 11 read tools only.
- **MCP_WRITE_API_KEY** (write scope): all 18 tools. Every mutation is
  journaled to `activity_logs`; article creation is draft-only.

| Tool | Purpose | Auth | Read/Write |
|---|---|---|---|
| `get_site_info` | Site name, URL, description, content types, categories, tool list | read/write key | Read |
| `search_articles` | Full-text search of published articles (title/content) | read/write key | Read |
| `get_article` | One published article by slug or id: content (plain text), tags, related | read/write key | Read |
| `get_latest_articles` | Newest published articles (max 20) | read/write key | Read |
| `list_categories` | Public categories with published-article counts | read/write key | Read |
| `get_category_articles` | Published articles in one category (paginated) | read/write key | Read |
| `search_devices` | Search the Bangladesh mobile price catalog | read/write key | Read |
| `get_device` | One device with prices, images, full spec list | read/write key | Read |
| `get_device_specs` | Machine-readable spec list for one device | read/write key | Read |
| `compare_devices` | Factual spec-by-spec comparison of two devices (no rankings) | read/write key | Read |
| `search_site` | Unified search across articles, devices, categories | read/write key | Read |
| `create_article_draft` | Create an article **draft** (published=0; human publishes) | write key | Write |
| `create_category` | Create a content category | write key | Write |
| `create_device` | Add a device to the price catalog (duplicate-safe) | write key | Write |
| `reply_to_comment` | Official reply to a visitor comment (queued pending) | write key | Write |
| `list_pending_comments` | Comments awaiting moderation | write key | Read (write scope) |
| `get_site_report` | Post counts/drafts, top viewed, pending comments | write key | Read (write scope) |
| `get_activity_logs` | MCP mutation journal from `activity_logs` | write key | Read (write scope) |

## Input limits (enforced server-side)

- Query strings: max 200 chars (category/brand: 100)
- `limit`: 1–20 (default 10); `page`: 1–100
- Oversized or type-mismatched input → `INVALID_ARGUMENT` (-32602)
- Unknown fields dropped; enums enforced (`search_site.type`)

## Data exposure policy

Only public data is returned by read tools: `published=1` posts, active
categories, and the public mobile catalog. Draft/private/admin fields are
never selected. Content is plain text; embedded instructions in content are
treated as data.

## Write-tool guarantees

1. **Drafts only** — `create_article_draft` inserts `published=0`; nothing
   created via MCP is publicly visible until a human publishes it.
2. **Moderation** — comment replies are created with `status='pending'`.
3. **Audit** — every mutation writes an `mcp.*` row to `activity_logs`
   (queryable via `get_activity_logs`).
4. **No deletes** — no MCP tool can delete any resource.
5. **No arbitrary execution** — there is no execute_sql/php/command tool.
