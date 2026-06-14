---
name: feature-request
description: Prompt template for implementing a new feature in BroxLab.
---

# PROMPT: Feature Request

Use this prompt to implement a new feature end-to-end with backend, frontend, validation, and tests.

---

## Prompt Template

```
Feature request:

[TITLE]
{brief feature title}

[DESCRIPTION]
{describe the feature, user story, and expected behavior}

[CONTEXT]
Stack: PHP, Twig, Node, Tailwind, MySQL
Relevant files: {existing files or directories}

[PRIORITY]
{low/medium/high}

[ADDITIONAL REQUIREMENTS]
- Auth required? {yes/no}
- Admin-only? {yes/no}
- API endpoint? {yes/no}
- Database change? {yes/no}

Execute in this order:
1. Understand where this belongs in the codebase
2. Add or update backend logic (model, migration, controller, route)
3. Add or update frontend experience (Twig, CSS, JS)
4. Wire backend and frontend together
5. Apply validation, auth, and CSRF protections
6. Rebuild assets if frontend or Node code changed
7. Show changed files and summarize the work
```

---

## Expected Output

- New or updated controller route(s)
- Model or service changes
- DB schema file if schema changes are needed
- Updated view/template and assets
- Validation and auth considerations
- Summary of changed files and next verification steps
