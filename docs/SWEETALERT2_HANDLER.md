# SweetAlert2 Message Handler System - Documentation

## Overview

This project now uses **SweetAlert2** for all user notifications and feedback. It provides:

- ✅ Beautiful animated toasts and dialogs
- ✅ Advanced interactions (confirm, prompt, input validation)
- ✅ Server-side flash message support
- ✅ Full accessibility (WCAG 2.1 AA compliant)
- ✅ Responsive design (mobile-friendly)
- ✅ Custom theming and styling

---

## Quick Start

### 1. Server-Side Flash Messages (Automatic)

In your PHP controller:
```php
showMessage('Your profile was updated!', 'success');
redirect('/dashboard');
```

The message will automatically display as a **SweetAlert2 toast** on the destination page.

### 2. Client-Side Messages (JavaScript)

```javascript
// Toast notification (auto-dismisses after 4s)
window.showMessage('Data saved', 'success');
window.showMessage('An error occurred', 'danger');
window.showToast('Quick notification', 'info'); // Alias

// Alert dialog (requires user click to close)
window.showAlert('Confirm action', 'This cannot be undone', 'warning');

// Confirmation dialog (returns boolean Promise)
if (await window.showConfirm('Delete this item?', 'Confirm')) {
    // User clicked Yes
}

// Prompt for input (returns string or null)
const name = await window.showPrompt('Enter your name:', 'Anonymous');
if (name) {
    console.log('You entered:', name);
}
```

---

## Message Status Types

Use these status values to control appearance and color:

| Status | Color | Icon | Use Case |
|--------|-------|------|----------|
| `success` | Green | ✓ Check | Successful operations |
| `danger` | Red | ✗ Error | Errors, critical issues |
| `error` | Red | ✗ Error | Alias for `danger` |
| `warning` | Orange | ⚠ Warning | Warnings, cautions |
| `info` | Blue | ℹ Info | General information |
| `primary` | Blue | ℹ Info | Primary actions |

---

## Configuration

### Default Settings

```javascript
{
    toastPosition: 'top-right',      // Toast position on screen
    toastDuration: 4000,              // Auto-dismiss timeout (ms)
    successColor: '#198754',          // Bootstrap green
    dangerColor: '#dc3545',           // Bootstrap red
    warningColor: '#ffc107',          // Bootstrap warning
    infoColor: '#0dcaf0',             // Bootstrap info
    closeonEscape: true,              // Escape key closes modals
    announceToasts: true,             // Screen reader announcements
    enableLogs: false                  // if true, internal debug logs appear via console.log
}
```

### Customize Configuration

```javascript
// Change single setting
MessageHandlerConfig.set('toastPosition', 'bottom-left');
MessageHandlerConfig.set('toastDuration', 6000);

// Change multiple settings
MessageHandlerConfig.setAll({
    toastPosition: 'bottom-center',
    toastDuration: 5000,
    announceToasts: false
});

// Get current config
const config = MessageHandlerConfig.getAll();
console.log(config);

// --- debug logging ---
//
// the message handler maintains an `enableLogs` flag you can toggle at runtime
// to show or hide console messages (useful during development).
//
// Use the following helper methods:
//
//     MessageHandler.enableLogs();
//     MessageHandler.disableLogs();
//     const active = MessageHandler.logsEnabled();
//
// You may also set the flag directly via `MessageHandlerConfig.set('enableLogs', true)`
// before initialization (e.g. with server‑rendered config).
```

---

## API Reference

### `window.showMessage(message, status, duration, options)`

Display a toast notification.

**Parameters:**
- `message` (string): The message text
- `status` (string): 'success', 'danger', 'warning', 'info' (default: 'info')
- `duration` (number): Auto-dismiss time in ms (default: 4000, use 0 for manual dismissal)
- `options` (object): Additional SweetAlert2 options

**Example:**
```javascript
window.showMessage('Profile updated', 'success', 5000);
window.showMessage('No more changes allowed', 'warning', 0); // Manual dismiss
```

### `window.showToast(message, status, duration, options)`

Alias for `showMessage()` (for backward compatibility).

### `window.showAlert(message, title, status, options)`

Show an alert dialog that requires user acknowledgment.

**Parameters:**
- `message` (string): The message content
- `title` (string): Dialog title (default: 'Alert')
- `status` (string): 'success', 'danger', 'warning', 'info'
- `options` (object): Additional SweetAlert2 options

**Example:**
```javascript
window.showAlert('Payment successful!', 'Transaction Complete', 'success');
```

### `window.showConfirm(message, title, status, options)` → Promise<boolean>

Show a confirmation dialog with Yes/Cancel buttons.

**Returns:** Promise that resolves to:
- `true` if user clicked "Yes, Proceed"
- `false` if user clicked "Cancel"

**Example:**
```javascript
const confirmed = await window.showConfirm(
    'This action cannot be undone.',
    'Delete Forever?',
    'danger'
);

if (confirmed) {
    // Perform deletion...
}
```

### `window.showPrompt(message, defaultValue, label, options)` → Promise<string|null>

Show a text input dialog.

**Parameters:**
- `message` (string): Prompt message
- `defaultValue` (string): Pre-filled input value
- `label` (string): Input label/title
- `options` (object): Additional options including `{ required: true }`

**Returns:** Promise with:
- User's input (string) if submitted
- `null` if cancelled

**Example:**
```javascript
const email = await window.showPrompt(
    'Enter your email address',
    'user@example.com',
    'Email Verification'
);

if (email) {
    sendVerificationEmail(email);
}
```

### `window.showValidationErrors(errors)`

Display multiple error messages as consecutive toasts.

**Parameters:**
- `errors` (array of strings): List of error messages

**Example:**
```javascript
const errors = [
    'Email is required',
    'Password must be at least 8 characters',
    'Passwords do not match'
];
window.showValidationErrors(errors);
```

### `window.handleAjaxSuccess(data, userMessage)`

Handle successful AJAX responses.

**Example:**
```javascript
fetch('/api/update')
    .then(r => r.json())
    .then(data => window.handleAjaxSuccess(data, 'Data updated'))
```

### `window.handleAjaxError(error, userMessage)`

Handle AJAX errors.

**Example:**
```javascript
fetch('/api/update')
    .catch(err => window.handleAjaxError(err, 'Failed to update'))
```

### `window.MessageHandler.init(config)`

Initialize the message handler (auto-called at DOM ready).

Can be called manually to override configuration:
```javascript
window.MessageHandler.init({
    toastPosition: 'bottom-center',
    toastDuration: 6000
});
```

### `window.MessageHandler.showLoading(message, title)`

Show a loading dialog with spinner (does not auto-dismiss).

**Example:**
```javascript
window.MessageHandler.showLoading('Processing payment...', 'Please wait');
// ... do work ...
window.MessageHandler.hideLoading();
```

### `window.MessageHandler.hideLoading()`

Close the loading dialog.

---

## Advanced Usage

### Custom SweetAlert2 Options

All functions support passing additional `options` for advanced SweetAlert2 features:

```javascript
window.showMessage('Custom message', 'info', 5000, {
    allowHtml: true,          // unsafe, use with caution
    didOpen: (toast) => {     // Custom behavior on open
        console.log('Toast opened');
    },
    willClose: () => {        // Custom behavior on close
        console.log('Toast closing');
    }
});
```

### Custom Styling

Override colors for a single message:

```javascript
// Note: This requires custom CSS or inline styles
// For advanced customization, pass SweetAlert2 options:
window.showMessage('Custom styled message', 'info', 5000, {
    color: '#ff6b6b',         // Text color
    background: '#1a1a1a',    // Background color
});
```

### Input Validation in Prompt

```javascript
const username = await window.showPrompt(
    'Choose a username',
    'user123',
    'Create Account',
    {
        inputValidator: (value) => {
            if (!value) return 'Username is required';
            if (value.length < 3) return 'Minimum 3 characters';
            if (!/^[a-z0-9_]+$/i.test(value)) return 'Only letters, numbers, and underscores';
        }
    }
);
```

---

## Architecture

### Files

```
public_html/assets/js/
├── sweetalert2-handler.js         ← Main handler (wrapper around SweetAlert2)
├── dist/
│   └── sweetalert2-handler.js     ← Minified version
├── admin/modules/
│   └── feedback.js                ← Admin adapter (still exists for fallback)

templates/
├── _macros/
│   └── flash.twig                 ← Flash rendering macro (unchanged)
├── layout.twig                    ← Includes SweetAlert2 CDN and handler
└── admin/
    └── layout.twig                ← Same as above for admin
```

### Loading Order

1. **Bootstrap** (button, modal components)
2. **SweetAlert2 CSS** (styling)
3. **SweetAlert2 JS** (library from CDN)
4. **sweetalert2-handler.js** (wrapper + auto-init)
5. **Page-specific scripts** (uses global API)

### How Flash Messages Work

1. Server sets flash in session: `showMessage('text', 'status')`
2. Server redirects to new page
3. Template renders `flash_message` variable
4. Flash macro sets: `window.__INITIAL_FLASH = {text, status, duration}`
5. SweetAlert2 handler initializes and displays the toast
6. Flash is cleared (can't be re-displayed on page refresh)

---

## Accessibility Features

### Screen Reader Support

All messages are announced to screen readers:
- Toasts: Announced as alerts
- Dialogs: Announced with title and content
- Default behavior: Can be disabled with `announceToasts: false`

### Keyboard Navigation

- **Tab**: Navigate through buttons and inputs
- **Escape**: Close modals (can be disabled with `closeOnEscape: false`)
- **Enter**: Confirm dialogs in forms
- **Space**: Activate buttons

### Focus Management

- Focus moves to the dialog/alert when opened
- Focus returns to caller when closed
- Focus is trapped inside modals (cannot tab out)

### ARIA Attributes

All elements include proper ARIA labels:
- Toast: `role="status"`, `aria-live="polite"`
- Alert: `role="alertdialog"`, `aria-labelledby`, `aria-describedby`
- Buttons: Built-in accessible labels

### Motion Preferences

Respects `prefers-reduced-motion` system setting:
```css
@media (prefers-reduced-motion: reduce) {
    /* Animations disabled automatically */
}
```

---

## Browser Support

Works on all modern browsers:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

---

## Migration from Old Message Handler

### Old API → New API

The new system maintains backward compatibility:

| Old | New | Status |
|-----|-----|--------|
| `window.showMessage()` | `window.showMessage()` | ✅ Same |
| `window.showToast()` | `window.showToast()` | ✅ Same |
| `window.showAlert()` | `window.showAlert()` | ✅ Same |
| `window.showConfirm()` | `window.showConfirm()` | ✅ Same |
| `window.showPrompt()` | `window.showPrompt()` | ✅ Same |
| Flash messages (HTML) | Flash messages (SweetAlert2) | ✨ Improved |

**No code changes required!** The new API is fully compatible.

---

## Examples

### Complete Form Submission

```javascript
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Show loading
    window.MessageHandler.showLoading('Submitting...', 'Please wait');
    
    try {
        const response = await fetch('/api/submit', {
            method: 'POST',
            body: new FormData(form)
        });
        const data = await response.json();
        
        window.MessageHandler.hideLoading();
        
        if (data.success) {
            window.showMessage('Form submitted successfully!', 'success', 3000);
            setTimeout(() => window.location.reload(), 2000);
        } else {
            window.showMessage(data.message, 'danger');
        }
    } catch (error) {
        window.MessageHandler.hideLoading();
        window.showMessage('An error occurred', 'danger');
    }
});
```

### Service Application with Confirmation

```javascript
document.getElementById('applyBtn').addEventListener('click', async () => {
    const confirmed = await window.showConfirm(
        'Are you sure you want to apply for this service?',
        'Confirm Application',
        'info'
    );
    
    if (confirmed) {
        submitApplication();
    }
});

async function submitApplication() {
    window.MessageHandler.showLoading('Processing application...', 'Please wait');
    
    try {
        const response = await fetch('/services/apply', { /* ... */ });
        const data = await response.json();
        
        window.MessageHandler.hideLoading();
        
        if (data.success) {
            window.showMessage(
                `আপনার ${serviceName} সেবার জন্য আবেদন সফলভাবে সম্পন্ন হয়েছে।`,
                'success',
                5000
            );
            setTimeout(() => window.location.href = data.redirect_url, 2000);
        } else {
            window.showValidationErrors(data.errors || [data.message]);
        }
    } catch (error) {
        window.MessageHandler.hideLoading();
        window.showMessage('Application submission failed', 'danger');
    }
}
```

---

## Troubleshooting

### Messages not appearing?

1. ✅ Check that SweetAlert2 CDN is loaded (Network tab)
2. ✅ Verify `sweetalert2-handler.js` is loaded
3. ✅ Check browser console for errors
4. ✅ Ensure message is not empty string

### SweetAlert2 not styling correctly?

1. ✅ Verify SweetAlert2 CSS CDN is loaded
2. ✅ Clear browser cache (Ctrl+Shift+Delete)
3. ✅ Check for CSS conflicts with site theme

### Flash messages not showing?

1. ✅ Ensure flash macro is in layout: `{{ flash_macros.render_flash(flash_message) }}`
2. ✅ Verify server is sending `flash_message` to template
3. ✅ Check that `window.__INITIAL_FLASH` is set (DevTools Console)
4. ✅ Confirm JavaScript is enabled in browser

---

## Best Practices

✅ **Do:**
- Use appropriate status types (success, danger, etc.)
- Provide context in dialog titles
- Keep messages concise and actionable
- Use loading states for long operations
- Test on mobile and accessibility tools

❌ **Don't:**
- Use `allowHtml: true` with user input (XSS risk)
- Show too many toasts at once (max 5 visible)
- Use duration: 0 for temporary messages
- Disable accessibility features unnecessarily
- Block UI without showing loading state

---

## Support & Documentation

- **SweetAlert2 Docs:** https://sweetalert2.github.io/
- **Examples:** See `/templates/services/view.twig` for real usage
- **Testing:** Run validation tests in `/docs/MESSAGE_HANDLER_TESTING.md`

