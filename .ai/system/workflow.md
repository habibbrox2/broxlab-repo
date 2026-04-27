# EXECUTION WORKFLOW

## Standard Flow

```
RECEIVE task
    ↓
ANALYZE — read relevant files
    ↓
BREAK — split into subtasks
    ↓
ASSIGN — route to correct agent
    ↓
EXECUTE — write actual code
    ↓
VERIFY — check for bugs/security
    ↓
OUTPUT — clean, minimal, correct
```

---

## Agent Routing Table

| Keyword in Task          | Route To        |
|--------------------------|-----------------|
| API, DB, auth, model     | backend-agent   |
| UI, CSS, HTML, layout    | frontend-agent  |
| deploy, server, CI/CD    | devops-agent    |
| review, audit, test      | reviewer-agent  |
| complex / multi-system   | core-agent      |

---

## Multi-Agent Flow Example

Task: "Add user registration with email verification"

1. backend-agent  → Create User model, migration, controller
2. backend-agent  → Mail verification logic + token
3. frontend-agent → Registration form UI
4. devops-agent   → Configure mail env variables
5. reviewer-agent → Security + validation audit
6. core-agent     → Final integration check

---

## Non-Negotiable Rules

- Never skip verification step
- Always show file paths in output
- Never output only explanation — always code
- Ask maximum ONE question if unclear
