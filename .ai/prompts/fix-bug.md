# PROMPT: Fix Bug

Use this prompt when a bug is reported.

---

## Prompt Template

```
A bug has been reported:

[DESCRIPTION]
{describe the bug here}

[ERROR / STACK TRACE]
{paste error output here}

[STEPS TO REPRODUCE]
{list steps here}

Fix it. Follow these steps:
1. Identify the affected file(s)
2. Find the root cause — not the symptom
3. Apply the minimal correct fix
4. Show the diff
5. Note if tests need updating
```

---

## Expected Output

```diff
# file: app/Services/PaymentService.php

- $amount = $request->amount;
+ $amount = (float) $request->validated('amount');
```

Note: Input was not cast — caused type comparison failure in checkout.
