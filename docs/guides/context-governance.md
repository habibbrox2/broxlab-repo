# Context Governance — BroxBhai
**Version:** 1.1 | **Date:** April 9, 2026

## Overview
Defines how context files are maintained, indexed, and retired so the AI stack stays accurate without duplication. It codifies ownership levels, refresh cadences, and the single-source-of-truth principle for shared rules.

## Purpose
Keep the documentation pyramid healthy (Tier 1: repo facts, Tier 2: key guides, Tier 3: deep dives) by declaring who can edit what and how often, while guiding safe additions or deprecations.

## Key Actions
### File Maintenance Profiles
- **Auto-appended files:** docs/agents/AGENT_MEMORY.md, docs/agents/KNOWN_PITFALLS.md, and the AGENTS changelog get new entries after non-trivial work (append-only, no deletions). Mark resolved entries clearly.
- **Manual reviews:** docs/guides/coding-standards.md, docs/ai/AI_QUICK_CONTEXT.md, docs/agents/AI_CODING_GUIDE.md, docs/editors/rules-base.md, and system/prompts/INDEX.md are refreshed quarterly or after major architectural changes by a Tech Lead or Senior Agent.

### Adding New Context Files
1. Place new content in docs/ (Tier 2/3) or docs/ai/ (AI-specific).
2. Assign tier 1/2/3 status in docs/agents/AI_CONTEXT_INDEX.md and link from docs/index.md.
3. Set Version and Date headers. 4. Document the maintenance plan inside this governance guide.

### Deprecation Process
- Annotate retired docs with [DEPRECATED] headers, list replacement files, keep them in git history, and redirect readers through docs/index.md.
- Update indexes and search for stale references (e.g., g "old-doc.md").

### Sync & Consistency Rules
- Security, DB, architecture rules live exclusively in docs/guides/coding-standards.md; other files link to it.
- Shared editor rules reside in docs/editors/rules-base.md; editor-specific notes (Claude/Cursor/Windsurf) stay short.
- Keep AGENTS.md as the repo fact entry point.

### Quarterly Review Checklist
- [ ] Refresh docs/guides/coding-standards.md
- [ ] Review docs/agents/AGENT_MEMORY.md and docs/agents/KNOWN_PITFALLS.md
- [ ] Validate system/prompts/INDEX.md matches actual prompts
- [ ] Search for duplicated rules across docs
- [ ] Update Tier 3 deep dives and docs/plans/index.md
- [ ] Ensure references in docs/index.md stay current

### Decision Log Expectations
Every major change is logged in docs/agents/AGENT_MEMORY.md using the template with ID, date, agent, context, decision, alternatives, trade-offs, and follow-up flag.
Minor fixes do not need metadata.

### References
- docs/index.md (new catalog)
- docs/guides/coding-standards.md (single source of truth)
- docs/agents/AI_CONTEXT_INDEX.md (tiered navigation)
- AGENTS.md (repo facts + changelog)
- docs/agents/SELF_IMPROVEMENT_LOOP.md (post-work instructions)
