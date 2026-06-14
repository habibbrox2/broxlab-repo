/**
 * Post/Page Form Enhancements
 * Adds polish, validation feedback, and UX improvements
 */

// Form instance
const PostFormEnhancements = (() => {
  'use strict';

  // State management
  const state = {
    isDirty: false,
    isValidating: false,
    lastSaved: null,
    fieldErrors: new Map(),
  };

  /**
     * Initialize form enhancements
     */
  const init = () => {
    const form = document.querySelector('form[data-autosave="true"]');
    if (!form) return;

    setupFormListeners(form);
    setupFieldValidation(form);
    setupAutoSave(form);
    setupCharCounters();
    setupFieldFocus();
    enhanceFormSubmit(form);
    setupToggleSwitches();
    setupPillGroups();
    setupSlugPreview();
  };

  /**
     * Setup form event listeners
     */
  const setupFormListeners = (form) => {
    // Mark form as dirty on input
    form.addEventListener('change', () => {
      state.isDirty = true;
      updateSaveIndicator();
    });

    form.addEventListener('input', () => {
      state.isDirty = true;
      updateSaveIndicator();
    });

    // Prevent unsaved changes
    window.addEventListener('beforeunload', (e) => {
      if (state.isDirty && !state.lastSaved) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
      }
    });
  };

  /**
     * Setup field validation with real-time feedback
     */
  const setupFieldValidation = (form) => {
    const fields = form.querySelectorAll('[required]');
    fields.forEach((field) => {
      field.addEventListener('blur', () => validateField(field));
      field.addEventListener('input', () => {
        if (state.fieldErrors.has(field.name)) {
          validateField(field);
        }
      });
    });
  };

  /**
     * Validate individual field
     */
  const validateField = (field) => {
    const { name, value, type, } = field;
    let error = null;

    // Check required
    if (field.hasAttribute('required') && !value.trim()) {
      error = `${getFieldLabel(field)} is required`;
    }

    // Check email format
    if (type === 'email' && value && !isValidEmail(value)) {
      error = 'Please enter a valid email address';
    }

    // Check min length for slug
    if (name === 'slug' && value && value.length < 3) {
      error = 'Slug must be at least 3 characters';
    }

    // Update field styling
    updateFieldValidation(field, error);
    if (error) {
      state.fieldErrors.set(name, error);
    } else {
      state.fieldErrors.delete(name);
    }

    return !error;
  };

  /**
     * Update field validation styling
     */
  const updateFieldValidation = (field, error) => {
    const container = field.parentElement;
    const existingError = container.querySelector('.field-error');

    if (existingError) {
      existingError.remove();
    }

    if (error) {
      field.classList.add('border-red-500', 'focus:ring-red-500/20');
      field.classList.remove('border-slate-300', 'focus:ring-indigo-500/20');

      const errorEl = document.createElement('div');
      errorEl.className = 'field-error text-xs text-red-600 mt-1.5 flex items-center gap-1';
      errorEl.innerHTML = `<i class="lucide lucide-alert-circle text-sm"></i> ${error}`;
      container.appendChild(errorEl);
    } else {
      field.classList.remove('border-red-500', 'focus:ring-red-500/20');
      field.classList.add('border-slate-300', 'focus:ring-indigo-500/20');
    }
  };

  /**
     * Setup character counters for text fields with progress bars
     */
  const setupCharCounters = () => {
    const titleField = document.getElementById('title');
    const metaTitleField = document.getElementById('meta_title');
    const metaDescField = document.getElementById('meta_description');

    if (titleField) {
      addCharCounter(titleField, null, 'Clear and descriptive');
      // Auto-generate slug from title
      titleField.addEventListener('input', handleTitleToSlug);
    }

    if (metaTitleField) {
      addCharCounter(metaTitleField, 60, 'SEO Title (max 60 chars)');
      updateCharProgress(metaTitleField, 'metaTitleProgress', 'metaTitleCount', 60);
      metaTitleField.addEventListener('input', () => {
        updateCharProgress(metaTitleField, 'metaTitleProgress', 'metaTitleCount', 60);
      });
    }

    if (metaDescField) {
      addCharCounter(metaDescField, 160, 'SEO Description (max 160 chars)');
      updateCharProgress(metaDescField, 'metaDescProgress', 'metaDescCount', 160);
      metaDescField.addEventListener('input', () => {
        updateCharProgress(metaDescField, 'metaDescProgress', 'metaDescCount', 160);
      });
    }
  };

  /**
     * Update character progress bar and count
     */
  const updateCharProgress = (field, progressId, countId, max) => {
    const progressBar = document.getElementById(progressId);
    const countEl = document.getElementById(countId);
    if (!progressBar || !countEl) return;

    const count = field.value.length;
    const pct = Math.min((count / max) * 100, 100);

    progressBar.style.width = `${pct }%`;
    progressBar.className = 'char-progress-bar';
    if (pct > 90) {
      progressBar.classList.add('danger');
    } else if (pct > 75) {
      progressBar.classList.add('warning');
    }

    countEl.textContent = `${count }/${ max}`;

    if (count > max) {
      field.classList.add('border-red-500');
      field.classList.remove('border-slate-300');
      countEl.classList.add('text-red-500');
      countEl.classList.remove('text-slate-500');
    } else {
      field.classList.remove('border-red-500');
      field.classList.add('border-slate-300');
      countEl.classList.remove('text-red-500');
      countEl.classList.add('text-slate-500');
    }
  };

  /**
     * Handle title input for slug auto-generation
     */
  let slugManuallyEdited = false;
  const handleTitleToSlug = () => {
    const titleField = document.getElementById('title');
    const slugField = document.getElementById('seo');
    const slugText = document.getElementById('slugText');
    if (!titleField || !slugField) return;

    // If slug was manually edited, don't auto-generate
    if (slugManuallyEdited) return;

    // Only auto-generate if slug is empty or matches the title pattern
    const title = titleField.value.trim();
    if (!title) return;

    // Check if slug field is empty or was auto-generated
    if (slugField.value.trim()) return;

    const slug = title
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\s\u0980-\u09FF]+/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-+|-+$/g, '');

    if (slug) {
      slugField.value = slug;
      if (slugText) slugText.textContent = slug;
    }
  };

  /**
     * Setup slug preview live update
     */
  const setupSlugPreview = () => {
    const slugField = document.getElementById('seo');
    const slugText = document.getElementById('slugText');
    if (!slugField || !slugText) return;

    // Mark as manually edited when user types in slug
    slugField.addEventListener('input', () => {
      slugManuallyEdited = true;
      slugText.textContent = slugField.value || 'enter-title';
    });

    // Reset auto-generate flag on focus if slug is empty
    slugField.addEventListener('focus', () => {
      if (!slugField.value.trim()) {
        slugManuallyEdited = false;
      }
    });
  };

  /**
     * Add character counter to field
     */
  const addCharCounter = (field, maxChars, label) => {
    const parent = field.parentElement;
    let counter = parent.querySelector('.char-counter');

    if (!counter) {
      counter = document.createElement('div');
      counter.className = 'char-counter text-xs text-slate-500 mt-2 flex items-center justify-between';
      parent.appendChild(counter);
    }

    const updateCounter = () => {
      const count = field.value.length;
      const percentage = maxChars ? Math.round((count / maxChars) * 100) : 0;
      const status = maxChars && count > maxChars * 0.9 ? 'text-orange-500' : 'text-slate-500';

      counter.innerHTML = `
        <span>${label}</span>
        <span class="${status}">${count}${maxChars ? `/${maxChars}` : ''}</span>
      `;

      if (maxChars && count > maxChars) {
        field.classList.add('border-orange-500');
      } else {
        field.classList.remove('border-orange-500');
      }
    };

    field.addEventListener('input', updateCounter);
    updateCounter();
  };

  /**
     * Setup field focus effects
     */
  const setupFieldFocus = () => {
    const formInputs = document.querySelectorAll('[data-form-control], .form-input, .form-select, .form-textarea, form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), form select, form textarea');

    formInputs.forEach((field) => {
      field.addEventListener('focus', () => {
        const parent = field.parentElement;
        parent.classList.add('ring-2', 'ring-indigo-500/10');
      });

      field.addEventListener('blur', () => {
        const parent = field.parentElement;
        parent.classList.remove('ring-2', 'ring-indigo-500/10');
      });
    });
  };

  /**
     * Setup auto-save functionality
     */
  const setupAutoSave = (form) => {
    let autoSaveTimer;

    const autoSave = () => {
      if (!state.isDirty || state.isValidating) return;

      // Show auto-saving indicator
      showAutoSaveNotification('Saving...', 'info');

      // In a real scenario, you'd send form data via AJAX here
      // For now, just simulate a save
      setTimeout(() => {
        state.isDirty = false;
        state.lastSaved = new Date();
        updateSaveIndicator();
        showAutoSaveNotification('Auto-saved', 'success');
      }, 500);
    };

    form.addEventListener('input', () => {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(autoSave, 2000);
    });
  };

  /**
     * Update save indicator
     */
  const updateSaveIndicator = () => {
    const indicator = document.getElementById('save-indicator');
    if (!indicator) {
      const div = document.createElement('div');
      div.id = 'save-indicator';
      div.className = 'fixed bottom-4 right-4 z-40 text-xs text-slate-600 flex items-center gap-2 opacity-0 transition-opacity';
      document.body.appendChild(div);
    }

    const indicator2 = document.getElementById('save-indicator');
    if (state.isDirty && !state.lastSaved) {
      indicator2.innerHTML = '<i class="lucide lucide-edit text-amber-500"></i> Unsaved changes';
      indicator2.classList.remove('opacity-0');
    } else if (state.lastSaved) {
      indicator2.innerHTML = `<i class="lucide lucide-check text-green-500"></i> Last saved ${formatTime(state.lastSaved)}`;
      indicator2.classList.remove('opacity-0');
      setTimeout(() => indicator2.classList.add('opacity-0'), 3000);
    }
  };

  /**
     * Show auto-save notification
     */
  const showAutoSaveNotification = (message, type = 'info') => {
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-100 border-green-300' : 'bg-blue-100 border-blue-300';
    const textColor = type === 'success' ? 'text-green-700' : 'text-blue-700';

    notification.className = `fixed top-4 left-4 z-50 px-4 py-3 rounded-lg border ${bgColor} ${textColor} text-sm font-medium
      animate-fadeIn`;
    notification.innerHTML = `
      <div class="flex items-center gap-2">
        <i class="lucide lucide-${type === 'success' ? 'check-circle' : 'info'} text-base"></i>
        ${message}
      </div>
    `;

    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 2000);
  };

  /**
     * Setup toggle switch buttons (role="switch")
     */
  const setupToggleSwitches = () => {
    document.querySelectorAll('[data-ui-switch][role="switch"], .toggle-switch[role="switch"]').forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const isChecked = toggle.getAttribute('aria-checked') === 'true';
        const newState = !isChecked;
        toggle.setAttribute('aria-checked', newState.toString());
        toggle.setAttribute('data-checked', newState ? 'true' : 'false');

        // Update hidden input value
        const hiddenInput = toggle.nextElementSibling;
        if (hiddenInput && hiddenInput.type === 'hidden') {
          hiddenInput.value = newState ? '1' : '0';
        }

        const thumb = toggle.querySelector('span');
        if (thumb) {
          thumb.classList.toggle('translate-x-5', newState);
          thumb.classList.toggle('translate-x-0', !newState);
        }

        // Dispatch change event for form dirty tracking
        toggle.dispatchEvent(new Event('change', { bubbles: true, }));
      });

      // Keyboard support
      toggle.addEventListener('keydown', (e) => {
        if (e.key === ' ' || e.key === 'Enter') {
          e.preventDefault();
          toggle.click();
        }
      });

      // Make focusable
      if (!toggle.hasAttribute('tabindex')) {
        toggle.setAttribute('tabindex', '0');
      }
    });
  };

  /**
     * Setup pill group radio buttons
     */
  const setupPillGroups = () => {
    document.querySelectorAll('[data-pill-group], .pill-group').forEach((group) => {
      group.querySelectorAll('[data-pill-option] input[type="radio"], .pill-option input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
          if (!radio.checked) return;

          // Update visual state for all pills in this group
          group.querySelectorAll('[data-pill-option], .pill-option').forEach((pill) => {
            const pillRadio = pill.querySelector('input[type="radio"]');
            const active = Boolean(pillRadio && pillRadio.checked);
            pill.dataset.active = active ? 'true' : 'false';
            pill.classList.toggle('active', active);
          });

          // Native radio change event already bubbles to form for dirty tracking
        });
      });
    });
  };

  /**
     * Enhance form submit
     */
  const enhanceFormSubmit = (form) => {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return;

    form.addEventListener('submit', (e) => {
      e.preventDefault();

      // Validate all required fields
      const requiredFields = form.querySelectorAll('[required]');
      let hasErrors = false;

      requiredFields.forEach((field) => {
        if (!validateField(field)) {
          hasErrors = true;
        }
      });

      if (hasErrors) {
        showAutoSaveNotification('Please fix the errors above', 'error');
        return;
      }

      // Show loading state
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="lucide lucide-loader animate-spin"></i> Saving...';

      // Submit form
      setTimeout(() => {
        form.submit();
      }, 500);
    });
  };

  /**
     * Helper: Get field label
     */
  const getFieldLabel = (field) => {
    const label = field.parentElement.querySelector('label');
    if (!label) return field.name;
    return label.textContent.replace('*', '').trim();
  };

  /**
     * Helper: Validate email
     */
  const isValidEmail = (email) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  /**
     * Helper: Format time
     */
  const formatTime = (date) => {
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return date.toLocaleDateString();
  };

  return {
    init,
    validateField,
  };
})();

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', PostFormEnhancements.init);
} else {
  PostFormEnhancements.init();
}

// Expose for external use
window.PostFormEnhancements = PostFormEnhancements;
