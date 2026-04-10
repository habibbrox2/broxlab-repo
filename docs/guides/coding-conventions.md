# BroxBhai Coding Conventions

## Overview
This reference captures repository-specific patterns for naming, routing, autoloading, and view rendering so every change aligns with the custom framework’s expectations.

## Purpose
Keep every contributor aligned on how controllers, models, and views are organized, how CSRF and middleware are applied, and how production includes are normalized for release symlinks.

## Key Actions
### Backend (PHP)
- Define routes in pp/Controllers/*.php with $router->get(...) and $router->post(...) to keep entry points centralized.
- Apply CSRF and auth middleware to every state-changing endpoint (usually ['middleware' => ['auth', 'admin_only', 'csrf']]).
- Keep database access inside pp/Models/* and use prepared statements instead of raw SQL injections or string interpolation.
- Always list columns explicitly in SELECTs and catch Throwable around controller handlers to prevent uncaught fatal errors.

### Includes & Autoloading
- Normalize shared equire_once calls through ealpath() ?:  so release symlink deployments do not double-include files such as pp/Models/AIProvider.php.

### Views & Frontend
- Render UI exclusively through Twig templates in pp/Views/**; avoid echoing HTML from controllers.
- Include CSRF tokens on AJAX calls via X-CSRF-Token headers and set X-Requested-With: XMLHttpRequest when needed.

## References
- docs/guides/coding-standards.md (security and DB standards)
- docs/index.md (doc catalog)
