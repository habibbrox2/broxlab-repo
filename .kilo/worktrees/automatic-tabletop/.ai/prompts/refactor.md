---
name: refactor
description: Prompt template for refactoring and improving code without changing behavior
---

# PROMPT: Refactor Code

Use this to clean up and improve existing code without changing behavior.

---

## Prompt Template

```
Refactor the following code / file:

[TARGET]
{file path or paste code}

[GOALS]
- Improve readability
- Remove duplication
- Extract reusable logic
- Simplify complex conditions
- Improve naming

[CONSTRAINT]
Do NOT change external behavior.
Do NOT add new features.
Match existing code style.
```

---

## Checklist Before Refactoring

- [ ] Understand what the code does fully
- [ ] Identify duplication
- [ ] Identify overly nested logic
- [ ] Check what can be extracted
- [ ] Verify tests exist (or note that they should)

---

## Output Format

Show before/after for each change.
Group changes by type: extract, rename, simplify.
