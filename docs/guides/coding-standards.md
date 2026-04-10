# BroxBhai Coding Standards

## Overview
A single source of truth for security, database, architecture, and logging expectations across the BroxLab stack. This guide keeps non-negotiable rules centralized so every agent and contributor enforces the same safeguards and data practices.

## Purpose
Drive consistent implementation of CSRF protection, input sanitization, prepared statements, explicit column selection, structured responses, and asset handling so that every release honors the platform's security posture and performance expectations.

## Key Actions
### Security (Non-Negotiable)
- Protect every state-changing route (POST/PUT/PATCH/DELETE) with alidateCsrfToken() or leveraged middleware and expose the token via Twig metadata or AJAX headers before dispatching requests.
- Purify any user-provided HTML with PurifierHelper::purify() before rendering, and never use the Twig aw filter on unsanitized data.
- Store secrets only in .env (excluded from source control); rotate exposed credentials immediately and document the change without reverting commits.

### Database / SQL Patterns
- Always run SQL from pp/Models/* and never interpolate user values directly; prefer mysqli prepared statements with bound parameters to keep injection risks at zero.
- Explicitly name columns (SELECT id, name, email) instead of SELECT * to avoid leaking sensitive fields and to keep queries performant.
- Wrap multi-step writes in transactions for consistency, paginate heavy result sets, and surface SQL quotas through helper utilities such as BaseModel::paginate.

### Architecture & Outputs
- Keep controllers focused on request/response flow, persist logic in Models, and render any HTML via Twig templates (with {{ ... | e }} escaping).
- Store reusable helpers in pp/Helpers/* after verifying existing implementations, and centralize routes under pp/Controllers/* with $router->get/post/... definitions.
- Respect generated asset boundaries: edit source files (src/, public_html/assets/) and rebuild with 
pm run build instead of touching public_html/assets/**/dist/**.

### Error Handling & Logging
- Catch exceptions near controllers, log context-rich errors with logError()/logActivity(), and return consistent JSON payloads ({ "success": true/false, "data": ... }) with appropriate HTTP codes.
- Never log raw credentials, and sanitize all logged metadata.

## Verification Checklist
- php -l path/to/file.php (syntax)
- php scripts/quality_scan.php
- php scripts/security_scan.php
- 
pm run lint (when JS/CSS touched)
- 
pm run check:assets (when frontend assets changed)

## Related References
- docs/guides/coding-conventions.md (naming and style patterns)
- docs/guides/context-governance.md (review cadence, change logs)
- docs/index.md (doc catalog)
