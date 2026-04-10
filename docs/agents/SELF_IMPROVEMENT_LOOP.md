# BroxBhai Self-Improvement Loop (AI Agents)

This repo uses a lightweight, append-only workflow so agents improve documentation and reduce rework over time.

Do this at the end of any non-trivial session:
1) If you discovered a new pattern/gotcha/convention, append it to `docs/guides/coding-conventions.md`.
2) If you made an architectural decision, log it in `docs/project/project-context.md` under `## Decision Log`.
3) If the feature changed the repo layout (new top-level area/module), update the Directory Structure section in `docs/project/project-context.md`.
4) If you learned an AI-specific trick (prompting, token saving, tool usage), add it to `docs/ai/AI_CODING_GUIDE.md`.
5) Bump the `Version:` line in `AGENTS.md` (patch increment).
6) Add a Changelog entry in `AGENTS.md` (keep it short and factual).
7) If you created/used a temporary plan doc under `docs/plans/` (including audit plans), delete it after implementation. If any part is still valuable long-term, move the key decisions to `docs/project/project-context.md` or `docs/ai/AGENT_MEMORY.md` first.

## Agent Improvement Rule - Examples

When proposing changes, use exactly this comment-block format:

<!-- Agent suggestion 2026-03-17 -->
Rule: All new Twig templates must extend `layout.twig` unless they are full standalone pages (error/404/maintenance).
Why: Prevents inconsistent layouts and duplicated header/footer code; already true in 90%+ of existing views.
Confidence: high

<!-- Agent suggestion 2026-03-20 -->
Rule: Never suggest adding inline `<script>` or `<style>` tags in Twig templates; always put JS in component files and CSS in Tailwind utilities or dedicated .css source files.
Why: Breaks asset bundling, versioning, and cache-busting; violates existing build pipeline.
Confidence: high
