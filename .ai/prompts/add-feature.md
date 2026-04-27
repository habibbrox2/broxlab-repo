# PROMPT: Add Feature

Use this prompt to implement a new feature end-to-end.

---

## Prompt Template

```
Add the following feature:

[FEATURE]
{describe the feature}

[CONTEXT]
Stack: {your stack}
Related files: {existing relevant files}

Execute in this order:
1. Understand where this fits in the codebase
2. Create or modify backend (model, migration, controller, route)
3. Update or create frontend (form, component, page)
4. Ensure frontend and backend are connected
5. Check for security issues (auth, validation)
6. Show all changed/created files
```

---

## Expected Output

- Migration file (if DB change needed)
- Controller method
- Route definition
- Frontend component or view
- Any middleware or policy needed
