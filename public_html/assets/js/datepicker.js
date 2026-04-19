/**
 * Datepicker Module
 * Wrapper for BroxBhai DatePicker (located in /assets/datepicker/)
 * This module ensures the datepicker is properly initialized on all .datepicker elements
 */

(function () {
    'use strict';

    /**
     * Initialize BroxBhai DatePicker on elements with .datepicker or [data-bxdp-input]
     */
    function initDatepicker() {
        // Wait for BroxBhai DatePicker to be available
        if (typeof BroxBhaiDatePicker === 'undefined') {
            console.warn('[Datepicker] BroxBhai DatePicker library not loaded');
            return;
        }

        // Find all input elements that need datepicker
        const datepickerInputs = document.querySelectorAll('input.datepicker, input[data-bxdp-input]');

        if (!datepickerInputs.length) return;

        datepickerInputs.forEach((el) => {
            // Skip if already initialized
            if (el.hasAttribute('data-bxdp-initialized')) {
                return;
            }

            try {
                new BroxBhaiDatePicker(el, {
                    format: el.dataset.format || 'DD-MM-YYYY',
                    locale: el.dataset.locale || 'en',
                    firstDay: el.dataset.firstDay || 0,
                    minDate: el.dataset.minDate || null,
                    maxDate: el.dataset.maxDate || null,
                    disabledDates: el.dataset.disabledDates || null,
                    range: el.dataset.range === 'true',
                    multi: el.dataset.multi === 'true',
                });

                // Mark as initialized
                el.setAttribute('data-bxdp-initialized', 'true');
            } catch (error) {
                console.error('[Datepicker] Failed to initialize:', error);
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState !== 'loading') {
        initDatepicker();
    } else {
        document.addEventListener('DOMContentLoaded', initDatepicker);
    }

    // Re-initialize on dynamic content
    document.addEventListener('brox:dynamic-content-added', initDatepicker);

    // Expose globally if needed
    if (!window.initDatepicker) {
        window.initDatepicker = initDatepicker;
    }
})();
