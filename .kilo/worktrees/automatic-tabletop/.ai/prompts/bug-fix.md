---
name: bug-fix
description: Prompt template for diagnosing and fixing reported bugs in BroxLab.
---

# PROMPT: Bug Fix

Use this prompt when a defect is reported and a minimal correct fix is needed.

---

## Prompt Template

```
Bug report:

[DESCRIPTION]
{describe the bug clearly}

[ERROR / STACK TRACE]
{include error details or logs}

[STEPS TO REPRODUCE]
{list exact reproduction steps}

[EXPECTED BEHAVIOR]
{what should happen}

[ACTUAL BEHAVIOR]
{what happens now}

Fix it using these steps:
1. Identify affected file(s)
2. Locate the root cause
3. Apply the smallest correct fix
4. Preserve existing behavior outside the bug
5. Show the diff and summarize the change
6. Note if tests should be added or updated
```

---

## Expected Output

- Precise diff for changed files
- Root cause explanation
- Notes on whether tests were added or updated
- Any follow-up TODOs if the bug requires further cleanup
