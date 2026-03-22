# Context Governance — BroxBhai
**Version:** 1.0 | **Date:** March 22, 2026  
**Purpose:** Guidelines for maintaining the AI context system as the project evolves.

---

## Overview

BroxBhai uses a **3-tier pyramid of context files** (Tier 1/2/3) to keep AI agents focused and token costs low while remaining comprehensive. This document explains:
- Which files are auto-updated vs. manually maintained
- How to deprecate context files safely
- When to create new context files
- Change propagation rules

**For navigation:** See [`docs/ai/AI_CONTEXT_INDEX.md`](docs/ai/AI_CONTEXT_INDEX.md)

---

## File Maintenance Profiles

### Auto-Updated by Agents (append-only)
These files grow over time; agents add entries but NEVER delete.

| File | How | When | Responsible |
|------|-----|------|-------------|
| [`docs/ai/AGENT_MEMORY.md`](docs/ai/AGENT_MEMORY.md) | Append new `[BROX-XXX]` entries | After non-trivial work | Coding agent |
| [`docs/ai/KNOWN_PITFALLS.md`](docs/ai/KNOWN_PITFALLS.md) | Append new `[PIT-XXX]` entries (mark `Resolved: Y/N`) | After finding/fixing a gotcha | Coding agent |
| `AGENTS.md` Changelog | Add entry to "Changelog (latest N)" | After significant feature/fix | Coding agent |

**Key rule:** Append only. Never delete entries. Mark as "Resolved: Yes" to retire them.

### Manually Maintained (Periodic Review)
These files should be reviewed quarterly or after major changes.

| File | Refresh Interval | Who | Notes |
|------|------------------|-----|-------|
| [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) | Quarterly or after architecture change | Tech Lead / Senior Agent | Single source of truth |
| [`docs/ai/AI_QUICK_CONTEXT.md`](docs/ai/AI_QUICK_CONTEXT.md) | Quarterly (token saver baseline) | Tech Lead | Keep ultra-concise |
| [`docs/ai/AI_CODING_GUIDE.md`](docs/ai/AI_CODING_GUIDE.md) | Bi-annual or after major refactor | Tech Lead | Deep-dive reference |
| [`editor/.rules-base.md`](editor/.rules-base.md) | Quarterly (shared foundation) | Tech Lead | Sync with CODING_STANDARDS |
| [`system/prompts/INDEX.md`](system/prompts/INDEX.md) | After prompt changes | AI/Prompt Engineer | Keep in sync with actual prompts |

---

## Extension: Adding New Context Files

When adding a new context file:

1. **Create it in `docs/` or `docs/ai/`** — Follow naming: `TOPIC.md`
2. **Categorize it (Tier 1/2/3):**
   - **Tier 1:** Only for absolutely foundational repo facts (rare)
   - **Tier 2:** Standard features, workflows, patterns
   - **Tier 3:** Deep-dive, optional, specialized
3. **Update [`docs/ai/AI_CONTEXT_INDEX.md`](docs/ai/AI_CONTEXT_INDEX.md)** — Add entry in appropriate tier
4. **Link from parent file** — If part of a set, link from index
5. **Set version number** — Start at 1.0, increment with major updates
6. **Add to this file** — Document maintenance profile above

**Example:** Adding a new guide for "Payment Integration"
- File: `docs/PAYMENT_INTEGRATION.md` (v1.0)
- Tier: 3 (deep-dive, specific feature)
- Maintained by: Payment Engineer (quarterly review)
- Referenced from: `AI_CONTEXT_INDEX.md` → Tier 3, and from relevant controller docs

---

## Deprecation: Removing or Archiving Files

When a context file becomes outdated:

1. **Mark as deprecated** in the file header:
   ```markdown
   # [DEPRECATED] Old Feature Guide
   **Deprecated:** March 22, 2026 | **Replace with:** See docs/NewFeature.md
   ```

2. **Update [`AI_CONTEXT_INDEX.md`](docs/ai/AI_CONTEXT_INDEX.md):**
   - Remove from active tiers
   - Add note: "Deprecated (see docs/NewFeature.md instead)"

3. **Move file** (optional but clean):
   ```bash
   mkdir -p docs/deprecated
   mv docs/old-feature.md docs/deprecated/old-feature.md
   ```

4. **Update any links** that pointed to it (use tool: `grep -r "old-feature.md"`)

5. **Keep for history:** Never delete from git; deprecated files stay in history for reference

**Example:** Deprecating an old scraper guide
```markdown
# [DEPRECATED] Web Scraper v1 Guide
**Deprecated:** March 15, 2026  
**Reason:** Replaced by Web Scraper v2 with improved performance  
**Replacement:** See docs/SCRAPER_V2.md and docs/ai/SYSTEM_SUMMARY.md
```

---

## Sync & Consistency Rules

### Rule: Single Source of Truth
- **Security rules** → live in [`docs/CODING_STANDARDS.md`](docs/CODING_STANDARDS.md) (only source)
- **Repo facts** → live in [`AGENTS.md`](AGENTS.md) (entry point + quick check-ins)
- **Shared editor rules** → live in [`editor/.rules-base.md`](editor/.rules-base.md) (referenced, not duplicated)

**Violation:** If a rule appears in 2+ places, consolidate to the canonical source, then link from others.

### Rule: Editor Files Don't Duplicate
- `editor/CLAUDE.md`, `editor/CURSOR.md`, `editor/WINDSURF.md`, `.github/copilot-instructions.md`
- All reference [`editor/.rules-base.md`](.rules-base.md) instead of repeating rules
- Editor-specific notes (shortcuts, workflow) can be unique

**Violation check:** `grep -r "prepared statements" editor/` should only find `.rules-base.md` mention

### Rule: Version Bumping  
- Major doc structure change → bump `AGENTS.md` version (e.g., 2.0 → 2.1)
- New entry in AGENT_MEMORY, KNOWN_PITFALLS → no version change needed (append-only)
- Significant prompt change → bump `system/prompts/INDEX.md` version for that prompt

---

## CI/Automated Checks (Future)

When CI/pre-commit integration is set up:

1. **Integrity check** (proposed in Phase 6):
   - Verify all referenced files exist (no broken links)
   - Check for duplicate rules across files
   - Validate JSON/YAML prompt configs

2. **Enforcement rules:**
   - No new TIER 1 files without approval
   - If CODING_STANDARDS.md changes, editor files must reference it (no duplication)
   - AGENT_MEMORY.md entries must follow format

3. **Pre-commit hook:**
   ```bash
   scripts/context-integrity-check.sh  # Runs before commit
   ```

---

## Quarterly Review Checklist

Every 3 months (or on demand):

- [ ] Review CODING_STANDARDS.md — are hard rules current?
- [ ] Scan AGENT_MEMORY.md — are old decisions still valid?
- [ ] Scan KNOWN_PITFALLS.md — are marked "Resolved" entries truly resolved?
- [ ] Check system/prompts/ — do all files match INDEX.md?
- [ ] Search for duplicate rules — consolidate any found
- [ ] Update Tier 3 docs — ensure deep-dive content is current
- [ ] Check external link references — update broken URLs

---

## Decision Log Examples

**Good examples of decisions to log in AGENT_MEMORY:**
- "Chose pattern X over pattern Y because of constraint Z"
- "Fixed bug in module M; root cause was R; guard against it for future X"
- "Evaluated tool A vs B; chose B for reason R"

**Do NOT log:**
- Minor typo fixes
- Formatting changes
- One-off script runs

**Template:**
```markdown
### [BROX-999] Meaningful short title
- Date: YYYY-MM-DD
- Agent: <name>
- Context: Problem statement
- Decision: What was decided
- Alternatives Considered: What else was considered + why rejected
- Trade-offs: What was gained/lost
- Follow-up needed: Y/N + details
```

---

## Communication & Transparency

When making significant context changes:

1. **Log in AGENT_MEMORY.md** — Why was it done, what changed
2. **Bump version in AGENTS.md** — Visible in all editor startup
3. **Update AI_CONTEXT_INDEX.md** if navigation changes
4. **Notify team** (in commit message or PR): "Context refactor: consolidated X rules into CODING_STANDARDS.md"

This keeps the system transparent and prevents stale mental models.

---

## FAQ

**Q: Can I rename a context file?**  
A: Yes. Update all references (INDEX, links in other files), move the file, add deprecation note to old path.

**Q: Should I create a new context file for a small feature?**  
A: Only if it's significant enough to warrant deep-dive documentation. Otherwise, add to existing docs (e.g., CODING_STANDARDS or AGENT_MEMORY).

**Q: What if AGENT_MEMORY.md gets too long?**  
A: Once it exceeds 500 lines, archive resolved entries: `git mv docs/ai/AGENT_MEMORY.md docs/ai/AGENT_MEMORY_archived_2026_Q2.md`, start fresh.

**Q: Are Tier 2/3 files optional?**  
A: Yes. Tier 1 is always needed. Layers 2+ are "on-demand" based on task scope.

**Q: How do I know if I should update a context file?**  
A: If you spent >30 min learning something that required reading multiple files, that's a signal the docs are stale.

---

## References

- [AI Context Index (3-Tier Navigation)](docs/ai/AI_CONTEXT_INDEX.md)
- [CODING STANDARDS (Single Source of Truth)](docs/CODING_STANDARDS.md)
- [AGENTS.md (Repo Facts + Changelog)](AGENTS.md)
- [AGENT_MEMORY.md (Decision Log)](docs/ai/AGENT_MEMORY.md)
- [SELF_IMPROVEMENT_LOOP.md (Post-Work Docs)](docs/ai/SELF_IMPROVEMENT_LOOP.md)
