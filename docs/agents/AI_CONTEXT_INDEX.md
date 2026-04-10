# AI Context Index (BroxBhai) — 3-Tier Pyramid

This file guides AI agents on what to read, depending on task scope and complexity.

---

## 🔺 Tier 1: Ultra-Quick (Default — 5 min read)

**When:** Starting any task, quick questions, routine coding  
**Goal:** Get orientation + hard rules (no deep context needed)  
**Files:**
1. [`AGENTS.md`](../../AGENTS.md) — Repo facts + hard rules overview
2. [`docs/ai/AI_QUICK_CONTEXT.md`](AI_QUICK_CONTEXT.md) — Token-saver essentials

**Cost:** ~100 tokens | **Sufficient for:** 90%+ of routine work

---

## 🔺 Tier 2: Standard (15 min read)

**When:** Non-trivial feature, security/architecture question, refactoring  
**Addition to Tier 1:**
- [`docs/guides/coding-standards.md`](../guides/coding-standards.md) — All detailed standards (security, DB, architecture, naming)
- [`docs/ai/AGENT_MEMORY.md`](AGENT_MEMORY.md) — Decision log + pattern registry (learn from past decisions)
- [`docs/editors/rules-base.md`](../editors/rules-base.md) — Shared rules for all editors

**Cost:** ~300 tokens | **Sufficient for:** Feature implementation, API design, security fixes

---

## 🔺 Tier 3: Deep-Dive (Optional — as needed)

**When:** Deep architecture changes, debugging complex issues, AI system improvements  
**Files (pick as needed):**

### AI & System Knowledge
- [`docs/ai/AI_CODING_GUIDE.md`](AI_CODING_GUIDE.md) — AI-specific workflows, token-saver patterns
- [`docs/ai/SYSTEM_SUMMARY.md`](SYSTEM_SUMMARY.md) — Web scraping, AI content, tool system overview
- [`docs/ai/PUBLIC-ASSISTANT-BEHAVIOR.md`](PUBLIC-ASSISTANT-BEHAVIOR.md) — How public assistant behaves

### Problem Solving & Gotchas
- [`docs/ai/KNOWN_PITFALLS.md`](KNOWN_PITFALLS.md) — Common mistakes + fixes (scan when stuck)
- [`docs/ai/SELF_IMPROVEMENT_LOOP.md`](SELF_IMPROVEMENT_LOOP.md) — How to improve docs/agents after work

### External References (Rarely Needed)
- [`docs/ai/DEV_REFERENCE_LINKS.md`](DEV_REFERENCE_LINKS.md) — External docs (React, Next, Tailwind, etc.)

### Historical Context
- `docs/project/project-context.md` (if exists) — Business context, integrations
- `docs/GENERATED_ASSETS_AND_BUILD.md` (if exists) — Asset build pipeline details

**Cost:** 500–1000+ tokens | **Sufficient for:** Full architectural redesign, deep system understanding

---

## 🗺️ Quick Navigation Map

| Task Type | Start Here | Spend Time On | Tier |
|-----------|-----------|-----------|------|
| Fix a bug | AGENTS.md → AI_QUICK_CONTEXT | docs/guides/coding-standards (if security) | 1–2 |
| Build a feature | AGENTS.md → AI_QUICK_CONTEXT → docs/guides/coding-standards | Specific pattern in AGENT_MEMORY | 2 |
| Refactor a module | AGENTS.md → docs/guides/coding-standards → AGENT_MEMORY | KNOWN_PITFALLS | 2–3 |
| Design API | AGENTS.md → docs/guides/coding-standards | AI_CODING_GUIDE (if AI-related) | 2 |
| Debug weird issue | AGENTS.md → KNOWN_PITFALLS | SYSTEM_SUMMARY, AGENT_MEMORY | 3 |
| Improve documentation | AGENTS.md → SELF_IMPROVEMENT_LOOP | AGENT_MEMORY, KNOWN_PITFALLS | 2 |

---

##  Editor-Specific Entry Points

- **Claude:** Start with [`docs/editors/claude.md`](../editors/claude.md) (references all tiers)
- **Cursor:** Start with [`docs/editors/cursor.md`](../editors/cursor.md) (references all tiers)
- **Copilot:** Start with [.github/copilot-instructions.md](../../.github/copilot-instructions.md) (references all tiers)
- **Windsurf:** Start with [`docs/editors/windsurf.md`](../editors/windsurf.md) (references all tiers)

All editor files point to:
- Tier 1: `AGENTS.md` + `docs/ai/AI_QUICK_CONTEXT.md`
- Shared rules: [`docs/editors/rules-base.md`](../editors/rules-base.md)

---

## System Prompts & Configuration

- **Prompt inventory:** [`system/prompts/INDEX.md`](../../system/prompts/INDEX.md) — Lists all prompts and loaders
- **AI skills config:** [`system/prompts/ai-skills.json`](../../system/prompts/ai-skills.json)

---

## Rule of Thumb

**Stop reading when you have enough context to act confidently.**  
Don't load Tier 3 unless you're stuck or working on architecture.  
Reference paths + line numbers instead of pasting large files (token efficiency).

- Claude Code: `docs/editors/claude.md`
- GitHub Copilot: `.github/copilot-instructions.md`
