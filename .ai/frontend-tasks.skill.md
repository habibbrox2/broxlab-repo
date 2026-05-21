---
name: frontend-development-workflow
description: Workflow for implementing frontend features in BroxLab: components, styling, and bundling
license: See repo LICENSE
---

# BroxLab Frontend Development Workflow

Use this skill for frontend tasks: adding UI components, styling, JavaScript interactions, or modifying assets.

## 1. Understand the Task

- **Component**: New button, form, modal, or full page layout?
- **Styling**: Tailwind utilities, custom CSS, or both?
- **Interactivity**: Vanilla JS, events, DOM manipulation, or static markup?
- **Responsive**: Mobile, tablet, desktop breakpoints?
- **Accessibility**: Keyboard navigation, ARIA labels, focus management?

Review [AGENTS.md](../../AGENTS.md) for naming conventions and [copilot-instructions.md](../../copilot-instructions.md) for asset structure.

## 2. Create or Update the Twig Template

**File Location:** `app/Views/{area}/{page}.twig` (organized by area: `public/`, `admin/`, `user/`, `auth/`)

**Pattern:**
```twig
{% extends 'layout/main.twig' %}

{% block title %}Page Title{% endblock %}

{% block content %}
<div class="container mx-auto px-4 py-8">
    <!-- Component example -->
    <div class="card bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-4">Heading</h1>
        
        <form id="my-form" class="space-y-4" action="/api/endpoint" method="POST">
            {% csrf_token %}
            
            <div class="form-group">
                <label for="name" class="block text-sm font-medium mb-2">Name</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md"
                    required>
            </div>
            
            <button 
                type="submit" 
                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
            >
                Submit
            </button>
        </form>
    </div>
</div>

<!-- Asset versioning: Link CSS and JS at end of block -->
<link rel="stylesheet" href="{{ withAssetVersion('/assets/css/dist/my-component.css') }}">
<script src="{{ withAssetVersion('/assets/js/dist/my-component.js') }}"></script>
{% endblock %}
```

**Rules:**
- Use `{{ withAssetVersion('/path/to/file') }}` for CSS/JS links
- Always use `{% csrf_token %}` in forms doing POST/PUT/DELETE
- Extend base layout: `{% extends 'layout/main.twig' %}`
- Use Tailwind classes for styling (avoid inline `style=""`)
- Escape user output by default (Twig auto-escapes)

## 3. Create the JavaScript Component

**File Location:** `public_html/assets/js/{component-name}.js` (use kebab-case)

**Pattern:**
```javascript
// public_html/assets/js/my-component.js

// Initialize component when DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const formElement = document.getElementById('my-form');
    if (formElement) {
        setupForm(formElement);
    }
});

function setupForm(formElement) {
    // Bind events
    formElement.addEventListener('submit', handleSubmit);
    
    // Setup other listeners
    const inputs = formElement.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('change', validateField);
    });
}

function handleSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Loading...';
    
    // Send request
    fetch(form.action, {
        method: form.method,
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Success:', data);
            // Handle success (redirect, show message, update DOM, etc)
        } else {
            console.error('Error:', data.error);
            // Handle error
        }
    })
    .catch(error => {
        console.error('Network error:', error);
    })
    .finally(() => {
        // Restore button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function validateField(event) {
    const input = event.target;
    const value = input.value.trim();
    
    // Add validation logic
    if (value.length === 0) {
        input.classList.add('border-red-500');
    } else {
        input.classList.remove('border-red-500');
    }
}

// Export if used by other modules
export { setupForm, handleSubmit, validateField };
```

**Rules:**
- Use kebab-case for file names: `my-component.js`, `form-validator.js`
- Initialize on `DOMContentLoaded` (not at top level)
- Use `fetch()` for API calls
- Use `FormData` for form submissions
- Handle errors gracefully
- Show loading states during async operations
- Use modern ES6: `const`, `=>`, `async/await`

**Common Patterns:**

### Form Validation
```javascript
function validateForm(form) {
    const errors = [];
    
    // Email validation
    const email = form.querySelector('[name="email"]');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        errors.push('Invalid email');
    }
    
    // Required field validation
    const name = form.querySelector('[name="name"]');
    if (!name.value.trim()) {
        errors.push('Name is required');
    }
    
    return errors;
}
```

### DOM Manipulation
```javascript
// Create and insert element
const newDiv = document.createElement('div');
newDiv.className = 'alert alert-success';
newDiv.textContent = 'Success!';
form.insertAdjacentElement('afterend', newDiv);

// Remove element
newDiv.remove();

// Toggle class
element.classList.toggle('is-active');
```

### API Requests
```javascript
// GET request
const response = await fetch('/api/features');
const data = await response.json();

// POST with JSON
const response = await fetch('/api/features', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Feature' })
});

// POST with FormData
const formData = new FormData();
formData.append('name', 'Feature');
const response = await fetch('/api/features', {
    method: 'POST',
    body: formData
});
```

## 4. Create or Update the CSS

**File Location:** `public_html/assets/css/{component-name}.css` (use kebab-case)

**Pattern:**
```css
/* public_html/assets/css/my-component.css */

/* Use CSS custom properties for theming */
:root {
    --primary-color: #3b82f6;
    --border-color: #e5e7eb;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Component styles */
.my-component {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    box-shadow: var(--shadow);
    padding: 1.5rem;
}

.my-component__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 1rem;
}

.my-component__title {
    font-size: 1.5rem;
    font-weight: bold;
}

.my-component__content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

/* States */
.my-component.is-loading {
    opacity: 0.6;
    pointer-events: none;
}

.my-component.is-error {
    border-color: #ef4444;
    background-color: #fef2f2;
}

/* Responsive */
@media (max-width: 768px) {
    .my-component {
        padding: 1rem;
    }
    
    .my-component__content {
        grid-template-columns: 1fr;
    }
}
```

**Rules:**
- Use kebab-case for class names: `.my-component`, `.btn-primary`
- Use BEM naming for related elements: `.my-component__title`, `.my-component--active`
- Prefer Tailwind classes over custom CSS (Tailwind is already generated)
- Use CSS custom properties (`--var-name`) for theming
- Mobile-first: Base styles, then `@media (min-width: ...)` for larger screens
- Group related styles together
- Keep specificity low (avoid nested selectors)

**When to use custom CSS vs Tailwind:**
- **Tailwind**: Simple utilities, one-off styles, rapid prototyping
  - `class="flex justify-between items-center gap-4"`
- **Custom CSS**: Complex components, animations, responsive layouts, reusable patterns
  - Component-scoped CSS file for `.my-component` patterns

## 5. Add TypeScript/JavaScript Types (Optional)

**File Location:** `public_html/assets/js/types/{component-name}.d.ts`

**Pattern:**
```typescript
// Types for component data
export interface Feature {
    id: number;
    name: string;
    description: string;
    created_at: string;
}

export interface FormData {
    name: string;
    description: string;
}

export interface ApiResponse<T> {
    success: boolean;
    data?: T;
    error?: string;
}
```

## 6. Rebuild Assets

After editing JS/CSS:

```bash
# Watch mode (recommended for development)
npm run dev

# One-time build
npm run build

# Production build (with minification)
npm run build:prod

# Clean + rebuild
npm run clean && npm run build:prod
```

**What happens:**
- ESBuild bundles JS from `public_html/assets/js/` → `dist/`
- Tailwind generates CSS from utility classes
- CSS files are processed and placed in `dist/`
- Asset versions are cached for browser (via `withAssetVersion()`)

## 7. Test Locally

```bash
# Start the PHP app
php -S localhost:8000 -t public_html

# Open browser
# Navigate to http://localhost:8000/your/page

# Check DevTools
# - Network tab: Verify bundled assets load
# - Console: Check for JS errors
# - Elements: Inspect rendered HTML
```

## 8. Validate Your Code

```bash
# Linting (ESLint)
npm run lint

# Type checking (TypeScript)
npm run type-check

# Asset naming conventions
npm run check:assets

# Full validation (required before commit)
npm run validate
```

## Asset Structure Reference

```
public_html/assets/
├── js/                          # JavaScript sources (edit here)
│   ├── script.js                # Main app bundle source
│   ├── admin.js                 # Admin panel source
│   ├── my-component.js          # Component source (kebab-case)
│   ├── auth/
│   │   ├── login.js
│   │   └── register.js
│   └── dist/                    # Generated bundles (don't edit!)
│       ├── script.js            # Built from script.js
│       └── my-component.js      # Built from my-component.js
├── css/
│   ├── tailwind-input.css       # Tailwind source (don't edit)
│   ├── tailwind.css             # Generated Tailwind (don't edit!)
│   ├── my-component.css         # Custom CSS source (edit here)
│   └── dist/
│       └── my-component.css     # Built CSS (don't edit!)
├── firebase/v2/
│   ├── src/                     # Firebase SDK source
│   └── dist/                    # Generated Firebase bundle (don't edit!)
└── ai-assistant/                # AI chat widget
    └── dist/                    # Generated (don't edit!)
```

## Common Gotchas

| Issue | Cause | Fix |
|-------|-------|-----|
| CSS not updating | Old cache or forgot to rebuild | Run `npm run clean && npm run build` |
| JS not found 404 | Editing `dist/` directly instead of source | Edit `public_html/assets/js/my-file.js`, not `dist/` |
| Naming fails validation | Using camelCase for files | Rename to kebab-case: `myComponent.js` → `my-component.js` |
| ESLint errors | Code style violations | Run `npm run lint:fix` to auto-fix |
| Asset not versioned | Missing `withAssetVersion()` | Use `{{ withAssetVersion('/assets/js/dist/file.js') }}` |

## Decision Checklist

- [ ] Twig template created in `app/Views/{area}/`
- [ ] Template uses `{{ withAssetVersion() }}` for CSS/JS
- [ ] Template uses `{% csrf_token %}` in forms
- [ ] JavaScript component created in `public_html/assets/js/` (kebab-case)
- [ ] JS initializes on `DOMContentLoaded`, uses `fetch()` for API calls
- [ ] CSS file created in `public_html/assets/css/` (kebab-case)
- [ ] CSS uses Tailwind classes or custom CSS with BEM naming
- [ ] Responsive design tested on mobile (max-width: 768px)
- [ ] Form validation implemented (client-side + server-side)
- [ ] Error handling in place (try/catch, user-friendly messages)
- [ ] Ran `npm run validate` (linting, type-check, tests pass)
- [ ] Tested in browser: `php -S localhost:8000 -t public_html`
- [ ] Ran `npm run build` to verify bundling works
