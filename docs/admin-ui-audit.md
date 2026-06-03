## Admin UI Audit Report

Scope: shared admin shell and admin-area Twig templates. All recommended styling changes are scoped to `body[data-admin-dir]`, so the public UI is not affected.

### Current Issues

- Layout issues
  - The admin shell was mixing ad hoc spacing, custom wrappers, and Bootstrap-like fragments instead of a single cohesive layout system.
  - The sidebar/header structure was not aligned with the existing `admin.js` shell contract, which risks broken mobile drawer and resize behavior.

- Spacing issues
  - Many admin pages mix `px-4`, `py-3`, `mb-4`, `gap-3`, and custom wrapper classes without a consistent rhythm.
  - Tables, cards, and forms often rely on page-local spacing rather than a shared scale.

- Typography issues
  - Headings, labels, and table text are inconsistent across pages.
  - Some admin pages still lean on legacy utility names and older class conventions instead of a shared type scale.

- Color issues
  - The admin area mixes Tailwind classes, Bootstrap-style semantic classes, and legacy semantic helpers such as `success`, `info`, `warning`, and `primary`.
  - Status colors and cards are inconsistent, especially across dashboard widgets, alerts, and badges.

- Mobile issues
  - The sidebar needs a clearer mobile drawer treatment with stronger overlay, focus, and close affordances.
  - Several tables and action bars need a better responsive fallback to avoid horizontal overflow on narrow screens.

- Accessibility issues
  - Focus states are inconsistent across buttons, dropdowns, and sidebar links.
  - Some interactive controls rely on visual styling only and need stronger ARIA and keyboard-friendly presentation.

- Inconsistent components
  - Buttons, cards, tables, alerts, dropdowns, and modals are implemented with several different visual languages.
  - Breadcrumbs and pagination are functional but visually dated compared with the newer Tailwind-based admin pages.

- Legacy classes
  - Classes such as `header-glass-rounded-xl`, `header-icon-inline-flex`, `modern-breadcrumb`, `separator`, `stat-label`, `stat-value`, `stat-change`, `stat-icon`, and Bootstrap-like `table`, `alert`, `modal`, `dropdown-menu`, `page-link`, `page-item` patterns still appear in admin templates.

### Recommended Improvements

- Keep the admin shell and component styling scoped to `body[data-admin-dir]` so public pages remain unchanged.
- Use one shared admin layout contract for the header, sidebar, resizer, overlay, and main content area.
- Normalize buttons, forms, cards, alerts, dropdowns, tables, modals, and pagination into shared admin-scoped component styles.
- Preserve existing routes, permissions, Twig variables, and JavaScript hooks while modernizing only the UI layer.
- Use the shared Tailwind component layer to style legacy admin classes as aliases during migration, reducing the need for page-by-page rewrites.
- Prioritize accessible focus states, keyboard navigation, and mobile drawer behavior.
- After layout stabilization, migrate the highest-traffic pages to the new admin component classes incrementally.

### Outcome Target

- Modern SaaS-style admin UI
- Responsive sidebar drawer and resizable desktop shell
- Consistent cards, tables, forms, alerts, and pagination
- Minimal impact on public UI
- No backend, route, or permission changes
