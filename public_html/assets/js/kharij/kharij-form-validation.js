/**
 * kharij-form-validation.js
 *
 * Form validation for Kharij forms — checks required fields on submit,
 * provides real-time error feedback (clear on input).
 *
 * Depends on: nothing
 *
 * Usage:
 *   The module auto-initializes on DOMContentLoaded if #kharijForm exists.
 *   It validates:
 *     - Main required fields (khata_number, mouza, upazila, district)
 *     - Dag entries (dag_number required in each dag)
 *     - Owner entries (name, address, share required in each owner)
 */
(function () {
  'use strict';

  const requiredFields = [
    { id: 'khata_number', label: '\u0996\u09A4\u09BF\u09AF\u09BC\u09BE\u09A8 \u09A8\u09AE\u09CD\u09AC\u09B0', },
    { id: 'mouza', label: '\u09AE\u09CC\u099C\u09BE', },
    { id: 'upazila', label: '\u0989\u09AA\u099C\u09C7\u09B2\u09BE/\u09A5\u09BE\u09A8\u09BE', },
    { id: 'district', label: '\u099C\u09C7\u09B2\u09BE', },
  ];

  const dagRequiredFields = ['dag_number',];
  const dagFieldLabels = {
    dag_number: '\u09A6\u09BE\u0997/\u09AA\u09CD\u09B2\u099F \u09A8\u09AE\u09CD\u09AC\u09B0',
  };

  const ownerRequiredFields = ['name', 'address', 'share',];
  const ownerFieldLabels = {
    name: '\u09AE\u09BE\u09B2\u09BF\u0995\u09C7\u09B0 \u09A8\u09BE\u09AE',
    address: '\u09A0\u09BF\u0995\u09BE\u09A8\u09BE',
    share: '\u0985\u0982\u09B6',
  };

  function clearErrors() {
    document.querySelectorAll('.kharij-field-group.invalid').forEach((el) => {
      el.classList.remove('invalid');
    });
    document.querySelectorAll('.owner-entry.invalid').forEach((el) => {
      el.classList.remove('invalid');
    });
    const summary = document.getElementById('validationSummary');
    if (summary) {
      summary.classList.remove('visible');
      const span = summary.querySelector('span');
      if (span) span.textContent = '';
    }
  }

  function showSummary(message) {
    let summary = document.getElementById('validationSummary');
    if (!summary) {
      const submitArea = document.querySelector('.scroll-fade-in:last-of-type .flex');
      if (submitArea) {
        summary = document.createElement('div');
        summary.id = 'validationSummary';
        summary.className = 'validation-summary';
        summary.innerHTML = '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg><span></span>';
        submitArea.parentNode.insertBefore(summary, submitArea);
      }
    }
    if (summary) {
      const span = summary.querySelector('span');
      if (span) span.textContent = message;
      summary.classList.add('visible');
      summary.classList.add('shake');
      setTimeout(() => { summary.classList.remove('shake'); }, 500);
    }
  }

  function validateField(id, label) {
    const el = document.getElementById(id);
    if (!el) return true;
    const group = el.closest('.kharij-field-group');
    if (!group) return true;
    const val = el.value.trim();
    if (!val) {
      group.classList.add('invalid');
      let errorEl = group.querySelector('.validation-error');
      if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'validation-error';
        group.appendChild(errorEl);
      }
      errorEl.textContent = `\u09A6\u09AF\u09BC\u09BE \u0995\u09B0\u09C7 ${ label } \u09AA\u09C2\u09B0\u09A3 \u0995\u09B0\u09C1\u09A8`;
      return false;
    }
    return true;
  }

  function validateDags() {
    const entries = document.querySelectorAll('#dag-entries-container .dag-entry');
    let valid = true;
    entries.forEach((entry) => {
      dagRequiredFields.forEach((field) => {
        const input = entry.querySelector(`[data-dag-field="${ field }"]`);
        if (!input) return;
        const group = input.closest('.kharij-field-group');
        if (!group) return;
        const val = input.value.trim();
        if (!val) {
          group.classList.add('invalid');
          let errorEl = group.querySelector('.validation-error');
          if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'validation-error';
            group.appendChild(errorEl);
          }
          errorEl.textContent = `\u09A6\u09AF\u09BC\u09BE \u0995\u09B0\u09C7 ${ dagFieldLabels[field] } \u09AA\u09C2\u09B0\u09A3 \u0995\u09B0\u09C1\u09A8`;
          valid = false;
        }
      });
    });
    return valid;
  }

  function validateOwners() {
    const entries = document.querySelectorAll('#owners-container .owner-entry');
    let valid = true;
    entries.forEach((entry) => {
      ownerRequiredFields.forEach((field) => {
        const input = entry.querySelector(`[data-owner-field="${ field }"]`);
        if (!input) return;
        const group = input.closest('.kharij-field-group');
        if (!group) return;
        const val = input.value.trim();
        if (!val) {
          group.classList.add('invalid');
          let errorEl = group.querySelector('.validation-error');
          if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'validation-error';
            group.appendChild(errorEl);
          }
          errorEl.textContent = `\u09A6\u09AF\u09BC\u09BE \u0995\u09B0\u09C7 ${ ownerFieldLabels[field] } \u09AA\u09C2\u09B0\u09A3 \u0995\u09B0\u09C1\u09A8`;
          entry.classList.add('invalid');
          valid = false;
        }
      });
    });
    return valid;
  }

  function validateForm() {
    clearErrors();
    let allValid = true;
    let firstInvalid = null;

    requiredFields.forEach((f) => {
      if (!validateField(f.id, f.label)) {
        allValid = false;
        if (!firstInvalid) firstInvalid = document.getElementById(f.id);
      }
    });

    if (!validateDags()) {
      allValid = false;
      if (!firstInvalid) {
        const firstDagInput = document.querySelector('#dag-entries-container [data-dag-field="dag_number"]');
        if (firstDagInput) firstInvalid = firstDagInput;
      }
    }

    if (!validateOwners()) {
      allValid = false;
      if (!firstInvalid) {
        const firstOwnerInput = document.querySelector('#owners-container [data-owner-field="name"]');
        if (firstOwnerInput) firstInvalid = firstOwnerInput;
      }
    }

    if (!allValid) {
      showSummary('\u09A6\u09AF\u09BC\u09BE \u0995\u09B0\u09C7 \u09B8\u0995\u09B2 \u09AC\u09BE\u09A7\u09CD\u09AF\u09A4\u09BE\u09AE\u09C2\u09B2\u0995 \u0995\u09CD\u09B7\u09C7\u09A4\u09CD\u09B0\u09C7\u09B0 \u09AE\u09BE\u09A8 \u09AA\u09C2\u09B0\u09A3 \u0995\u09B0\u09C1\u09A8');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center', });
        setTimeout(() => { firstInvalid.focus(); }, 400);
      }
      return false;
    }
    return true;
  }

  function initFormValidation() {
    const form = document.getElementById('kharijForm');
    if (!form) return;

    form.addEventListener('submit', (e) => {
      if (!validateForm()) {
        e.preventDefault();
        return false;
      }
    });

    // Real-time error clearing on input
    form.addEventListener('input', (e) => {
      const group = e.target.closest('.kharij-field-group');
      if (group && group.classList.contains('invalid')) {
        group.classList.remove('invalid');
      }
      const ownerEntry = e.target.closest('.owner-entry');
      if (ownerEntry && ownerEntry.classList.contains('invalid')) {
        let allFilled = true;
        ownerRequiredFields.forEach((f) => {
          const input = ownerEntry.querySelector(`[data-owner-field="${ f }"]`);
          if (!input || !input.value.trim()) allFilled = false;
        });
        if (allFilled) ownerEntry.classList.remove('invalid');
      }
    });
  }

  // Auto-init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFormValidation);
  } else {
    initFormValidation();
  }
})();
