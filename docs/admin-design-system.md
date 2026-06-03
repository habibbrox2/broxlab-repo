# Admin Panel Design System

## Design Style

Modern SaaS Admin Dashboard inspired by:
- Linear
- Vercel
- Stripe Dashboard
- Notion
- GitHub
- Clerk
- Supabase

Clean, minimal, and professional interface with subtle shadows, rounded corners, and thoughtful spacing.

## Color System

### Light Mode
| Category | Variable | Tailwind Class | Usage |
|----------|----------|----------------|-------|
| **Primary** | `--color-primary` | `primary-500` (`#6366f1`) | Buttons, links, active states |
| **Secondary** | `--color-secondary` | `neutral-500` (`#64748b`) | Secondary buttons, less prominent elements |
| **Success** | `--color-success` | `success-500` (`#10b981`) | Success messages, positive actions |
| **Warning** | `--color-warning` | `warning-500` (`#f59e0b`) | Warnings, pending states |
| **Danger** | `--color-danger` | `danger-500` (`#ef4444`) | Errors, destructive actions |
| **Info** | `--color-info` | `info-500` (`#0ea5e9`) | Informational content |
| **Background** | `--color-bg` | `neutral-50` (`#f8fafc`) | Page background |
| **Surface** | `--color-surface` | `neutral-100` (`#f1f5f9`) | Cards, sidebars, elevated elements |
| **Border** | `--color-border` | `neutral-200` (`#e2e8f0`) | Dividers, input borders, card borders |
| **Muted Text** | `--color-muted` | `neutral-500` (`#64748b`) | Secondary text, placeholders |
| **Text Primary** | `--color-text` | `neutral-900` (`#0f172a`) | Primary body text |
| **Text Secondary** | `--color-text-secondary` | `neutral-600` (`#475569`) | Secondary body text |

### Dark Mode (data-theme="dark")
| Category | Variable | Tailwind Class | Usage |
|----------|----------|----------------|-------|
| **Primary** | `--color-primary` | `primary-400` (`#818cf8`) | Buttons, links, active states |
| **Secondary** | `--color-secondary` | `neutral-400` (`#94a3b8`) | Secondary buttons, less prominent elements |
| **Success** | `--color-success` | `success-400` (`#34d399`) | Success messages, positive actions |
| **Warning** | `--color-warning` | `warning-400` (`#fbbf24`) | Warnings, pending states |
| **Danger** | `--color-danger` | `danger-400` (`#f87171`) | Errors, destructive actions |
| **Info** | `--color-info` | `info-400` (`#60a5fa`) | Informational content |
| **Background** | `--color-bg` | `neutral-950` (`#020617`) | Page background |
| **Surface** | `--color-surface` | `neutral-900` (`#0f172a`) | Cards, sidebars, elevated elements |
| **Border** | `--color-border` | `neutral-700` (`#1e293b`) | Dividers, input borders, card borders |
| **Muted Text** | `--color-muted` | `neutral-400` (`#94a3b8`) | Secondary text, placeholders |
| **Text Primary** | `--color-text` | `neutral-50` (`#f8fafc`) | Primary body text |
| **Text Secondary** | `--color-text-secondary` | `neutral-300` (`#cbd5e1`) | Secondary body text |

## Typography

### Font Family
- **Sans**: `Inter`, `Noto Sans Bengali`, system fallbacks
- **Mono**: `JetBrains Mono`, `Fira Code`, `Cascadia Code`, `Consolas`, `monospace`

### Type Scale
| Class | Font Size | Line Height | Usage |
|-------|-----------|-------------|-------|
| `text-xs` | 0.75rem | 1rem | Helper text, captions |
| `text-sm` | 0.875rem | 1.25rem | Form labels, table cell text |
| `text-base` | 1rem | 1.625rem | Body text, paragraph |
| `text-lg` | 1.125rem | 1.75rem | Section titles, large body text |
| `text-xl` | 1.25rem | 1.75rem | Page subtitles, card titles |
| `text-2xl` | 1.5rem | 2rem | Page titles, section headers |
| `text-3xl` | 1.875rem | 2.25rem | Dashboard headers, large titles |
| `text-4xl` | 2.25rem | 2.5rem | Major section titles |
| `text-5xl` | 3rem | 1.1 | Page headers, hero text |

### Font Weight
- `font-light`: 300
- `font-normal`: 400
- `font-medium`: 500 (default for most text)
- `font-semibold`: 600 (buttons, form labels)
- `font-bold`: 700 (page titles, important headings)
- `font-extrabold`: 800

## Spacing

### Base Unit: 0.25rem (4px)
| Class | Size | Usage |
|-------|------|-------|
| `px-0` / `py-0` | 0 | No horizontal/vertical padding |
| `px-1` / `py-1` | 0.25rem | Tight spacing |
| `px-2` / `py-2` | 0.5rem | Compact spacing |
| `px-3` / `py-3` | 0.75rem | Default spacing |
| `px-4` / `py-4` | 1rem | Standard padding |
| `px-5` / `py-5` | 1.25rem | Larger padding |
| `px-6` / `py-6` | 1.5rem | Section spacing |
| `px-8` / `py-8` | 2rem | Large section spacing |
| `px-10` / `py-10` | 2.5rem | Card padding |
| `px-12` / `py-12` | 3rem | Page padding |

### Grid Gap
- `gap-1` (0.25rem) - Tight grid
- `gap-2` (0.5rem) - Compact grid
- `gap-3` (0.75rem) - Default grid gap
- `gap-4` (1rem) - Standard grid gap
- `gap-6` (1.5rem) - Loose grid gap
- `gap-8` (2rem) - Very loose grid gap

## Border Radius
| Class | Size | Usage |
|-------|------|-------|
| `rounded-sm` | 0.375rem | Small inputs, buttons |
| `rounded` / `rounded-md` | 0.5rem | Default radius |
| `rounded-lg` | 0.75rem | Cards, modals |
| `rounded-xl` | 1rem | Large cards, popup containers |
| `rounded-2xl` | 1.25rem | Prominent containers |
| `rounded-full` | 9999px | Pills, circles |

## Shadows
| Class | Value | Usage |
|-------|-------|-------|
| `shadow-xs` | 0 1px 2px rgba(15,23,42,0.05), 0 1px 2px rgba(15,23,42,0.04) | Subtle shadows |
| `shadow-sm` | 0 1px 3px rgba(15,23,42,0.08), 0 1px 2px rgba(15,23,42,0.04) | Input focus, small cards |
| `shadow-md` | 0 4px 6px rgba(15,23,42,0.08), 0 2px 4px rgba(15,23,42,0.04) | Standard card shadow |
| `shadow-lg` | 0 10px 15px rgba(15,23,42,0.08), 0 4px 6px rgba(15,23,42,0.04) | Elevated cards, dropdowns |
| `shadow-xl` | 0 20px 25px rgba(15,23,42,0.10), 0 10px 10px rgba(15,23,42,0.04) | Modals, popovers |
| `shadow-2xl` | 0 25px 50px rgba(15,23,42,0.15) | Heavy elevation |
| `shadow-inner` | inset 0 2px 4px rgba(0,0,0,0.06) | Inner shadows for pressed states |

## Transitions
| Class | Property | Duration | Timing |
|-------|----------|----------|---------|
| `transition-colors` | color, background-color, border-color | 250ms | cubic-bezier(0.4, 0, 0.2, 1) |
| `transition-transform` | transform | 250ms | cubic-bezier(0.4, 0, 0.2, 1) |
| `transition-shadow` | box-shadow | 250ms | cubic-bezier(0.4, 0, 0.2, 1) |
| `transition-opacity` | opacity | 250ms | cubic-bezier(0.4, 0, 0.2, 1) |
| `transition-all` | all properties | 250ms | cubic-bezier(0.4, 0, 0.2, 1) |

## Component Guidelines

### Buttons
- **Primary**: `bg-primary-600 text-white hover:bg-primary-700 focus:ring-2 focus:ring-primary-300 focus:ring-offset-2 disabled:opacity-50`
- **Secondary**: `bg-neutral-100 text-neutral-900 hover:bg-neutral-200 focus:ring-2 focus:ring-neutral-300 focus:ring-offset-2 disabled:opacity-50`
- **Success**: `bg-success-600 text-white hover:bg-success-700 focus:ring-2 focus:ring-success-300 focus:ring-offset-2 disabled:opacity-50`
- **Danger**: `bg-danger-600 text-white hover:bg-danger-700 focus:ring-2 focus:ring-danger-300 focus:ring-offset-2 disabled:opacity-50`
- **Ghost**: `text-neutral-600 hover:bg-neutral-100 focus:ring-2 focus:ring-neutral-300 focus:ring-offset-2 disabled:opacity-50`
- **Outline**: `border border-neutral-300 text-neutral-700 hover:bg-neutral-50 focus:ring-2 focus:ring-neutral-300 focus:ring-offset-2 disabled:opacity-50`

### Cards
- **Base**: `bg-surface border border-border rounded-lg shadow-sm`
- **Hover**: `hover:shadow-md transition-shadow`
- **Interactive**: `hover:bg-neutral-50 cursor-pointer`

### Form Elements
- **Input**: `w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 focus:ring-offset-0 disabled:opacity-50`
- **Label**: `block text-sm font-medium text-neutral-700 mb-1`
- **Help Text**: `text-xs text-neutral-500 mt-1`
- **Error Message**: `text-sm text-danger-600 mt-1`

### Tables
- **Container**: `overflow-x-auto`
- **Table**: `min-w-full divide-y divide-neutral-200`
- **Header**: `bg-neutral-50 px-4 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider`
- **Cell**: `px-4 py-3 text-sm text-neutral-900`
- **Hover Row**: `hover:bg-neutral-50 transition-background`
- **Sticky Header**: `position-sticky top-0 z-20`

### Alerts
- **Info**: `bg-info-50 text-info-800 border border-info-200 rounded-lg`
- **Success**: `bg-success-50 text-success-800 border border-success-200 rounded-lg`
- **Warning**: `bg-warning-50 text-warning-800 border border-warning-200 rounded-lg`
- **Error**: `bg-danger-50 text-danger-800 border border-danger-200 rounded-lg`

### Badges
- **Primary**: `bg-primary-100 text-primary-800 text-xs font-medium px-2 py-0.5 rounded`
- **Secondary**: `bg-neutral-100 text-neutral-800 text-xs font-medium px-2 py-0.5 rounded`
- **Success**: `bg-success-100 text-success-800 text-xs font-medium px-2 py-0.5 rounded`
- **Warning**: `bg-warning-100 text-warning-800 text-xs font-medium px-2 py-0.5 rounded`
- **Danger**: `bg-danger-100 text-danger-800 text-xs font-medium px-2 py-0.5 rounded`

### Navigation
- **Sidebar Item**: `flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors`
- **Active Item**: `bg-neutral-900/50 text-neutral-100 shadow-sm`
- **Hover Item**: `bg-neutral-50 text-neutral-900`
- **Header**: `flex items-center px-4 py-3 text-sm font-bold text-neutral-900`

## Implementation Notes

1. **Dark Mode**: Controlled by `[data-theme="dark"]` attribute on `<html>` element
2. **CSS Variables**: All colors defined as CSS variables for potential JavaScript access
3. **Accessibility**: 
   - Minimum 4.5:1 contrast ratio for text/background
   - Focus visible outlines using `focus:ring-*`
   - Appropriate touch targets (minimum 44x44px)
4. **Responsive Design**: Mobile-first approach with breakpoints:
   - `sm`: 640px
   - `md`: 768px
   - `lg`: 1024px
   - `xl`: 1280px
   - `2xl`: 1536px

## Migration Strategy

1. **Phase 1**: Update Tailwind configuration (already complete)
2. **Phase 2**: Replace `brox-polish.css` with Tailwind utilities in admin layout
3. **Phase 3**: Refactor components (sidebar, header, forms, tables, cards, etc.)
4. **Phase 4**: Update all admin templates to use design system classes
5. **Phase 5**: Remove unused CSS and optimize build