# Windsurf AI Instructions - BroxBhai (Token Saver)

**→ Read first:** [`AGENTS.md`](../AGENTS.md) + [`docs/ai/AI_QUICK_CONTEXT.md`](../docs/ai/AI_QUICK_CONTEXT.md)

**→ Shared Rules:** See [`editor/.rules-base.md`](.rules-base.md) for hard rules (no duplication here).

## Windsurf-Specific Notes
- **Flow mode:** Use Windsurf's cascade editing for multi-file refactors (great for coordinated changes)
- **Context building:** Windsurf's file tree helps explore connected files — leverage it before editing

## Quick Verification
```bash
php -l path/to/file.php
php scripts/quality_scan.php
npm run lint                 # if JS changed
npm run check:assets         # if assets changed
```

