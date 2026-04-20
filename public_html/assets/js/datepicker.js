/**
 * DatePicker Wrapper - browser script wrapper for the standalone datepicker
 * Source: /assets/datepicker/datepicker.js
 * This file is bundled by esbuild into /assets/js/dist/datepicker.js
 */

(function initBundledDatePicker() {
  'use strict';

  // Wait for the datepicker library to be available
  const checkDatePickerLib = () => {
    if (typeof window.DatePicker !== 'undefined') {
      initializeDatapicker();
    } else {
      // Retry after a short delay
      setTimeout(checkDatePickerLib, 100);
    }
  };

  function initializeDatapicker() {
    // Re-export as BroxBhaiDatePicker for compatibility
    if (typeof window.DatePicker !== 'undefined') {
      window.BroxBhaiDatePicker = window.DatePicker;
    }

    // Initialize all datepicker inputs
    const initializeInputs = () => {
      const inputs = document.querySelectorAll('input.datepicker, input[data-bxdp-input]');
      inputs.forEach((input) => {
        if (!input.hasAttribute('data-bxdp-initialized')) {
          try {
            if (window.DatePicker && window.DatePicker.attachToInput) {
              window.DatePicker.attachToInput(input);
              input.setAttribute('data-bxdp-initialized', 'true');
            }
          } catch (error) {
            console.error('[Datepicker] Failed to initialize:', error);
          }
        }
      });
    };

    // Initialize on DOM ready
    if (document.readyState !== 'loading') {
      initializeInputs();
    } else {
      document.addEventListener('DOMContentLoaded', initializeInputs);
    }

    // Watch for dynamically added content
    document.addEventListener('brox:dynamic-content-added', initializeInputs);
    window.initDatepicker = initializeInputs;
  }

  // Start checking for the library
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkDatePickerLib);
  } else {
    checkDatePickerLib();
  }
})();
