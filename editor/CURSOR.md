# Cursor AI Instructions - BroxBhai (Token Saver)

**→ Read first:** [`AGENTS.md`](../AGENTS.md) + [`docs/ai/AI_QUICK_CONTEXT.md`](../docs/ai/AI_QUICK_CONTEXT.md)

**→ Shared Rules:** See [`editor/.rules-base.md`](.rules-base.md) for hard rules (no duplication here).

## Cursor-Specific Notes
- **Context optimization:** Use `path:line` references instead of pasting large file excerpts
- **Search leverage:** Cursor's RAG is powerful — use `Cmd+K` to search by intent, not just keywords
- **Symlink awareness:** On Windows, be mindful of path semantics when using symlinks

## Quick Verification
```bash
php -l path/to/file.php
php scripts/quality_scan.php
npm run lint                 # if JS changed
npm run check:assets         # if assets changed
```

