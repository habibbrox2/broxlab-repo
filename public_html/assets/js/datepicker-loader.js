/**
 * Datepicker Lazy Loader
 *
 * Dynamically loads the datepicker module only when:
 * - Elements with class "datepicker" are present on the page
 * - OR elements with attribute "data-bxdp-input" are present
 *
 * This prevents loading datepicker.js on pages that don't need it.
 */

function initDatepickerLoader() {
  // Check if datepicker elements exist on page
  const hasDatepickerElements = () => {
    return Boolean(document.querySelector('.datepicker') ||
            document.querySelector('[data-bxdp-input]'));
  };

  // Load datepicker module dynamically
  const loadDatepicker = () => {
    try {
      const assetVersion = document.documentElement.getAttribute('data-asset-version') || 'dev';
      const script = document.createElement('script');
      script.type = 'module';
      script.src = `/assets/js/dist/datepicker.js?v=${assetVersion}`;
      document.body.appendChild(script);
    } catch (error) {
      console.error('[Datepicker Loader] Failed to load datepicker:', error);
    }
  };

  // Initialize on DOMContentLoaded if not already loaded
  const initLoader = () => {
    if (hasDatepickerElements()) {
      loadDatepicker();
    }
  };

  // Run on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLoader);
  } else {
    initLoader();
  }

  // Also support dynamic content (MutationObserver for AJAX-loaded content)
  if (typeof MutationObserver !== 'undefined' && !window.__DATEPICKER_LOADER_OBSERVER_ACTIVE__) {
    window.__DATEPICKER_LOADER_OBSERVER_ACTIVE__ = true;

    const observer = new MutationObserver((mutations) => {
      // Check if any added nodes contain datepicker elements
      for (const mutation of mutations) {
        if (mutation.type === 'childList') {
          for (const node of mutation.addedNodes) {
            if (node.nodeType === 1) { // Element node
              if (
                node.classList?.contains('datepicker') ||
                                node.querySelector?.('.datepicker') ||
                                node.getAttribute?.('data-bxdp-input') ||
                                node.querySelector?.('[data-bxdp-input]')
              ) {
                loadDatepicker();
                break;
              }
            }
          }
        }
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }
}

// Start loader immediately
if (typeof window !== 'undefined') {
  initDatepickerLoader();
}

export { initDatepickerLoader };
