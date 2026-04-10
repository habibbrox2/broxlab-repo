# Claude Instructions — BroxLab

## Setup
- Read AGENTS.md, then docs/ai/AI_QUICK_CONTEXT.md before you begin.
- Load the repository into Claude with a focus on relevant directories (start with src/, pp/, or the docs linked below).

## Workflow
- Use Claude's semantic search to explore large files; rely on the @ command when you need project-wide context.
- Favor understanding intent over keyword matching: describe the user problem and let Claude summarize before editing.
- Always run php -l path/to/file.php, php scripts/quality_scan.php, and 
pm run lint/
pm run check:assets if you touched JS or assets.

## Links
- docs/editors/rules-base.md (shared hard rules)
- docs/index.md (full documentation catalog)
- docs/guides/coding-standards.md (security, DB, architecture)
