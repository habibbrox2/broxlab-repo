/**
 * DOM Helpers - Lightweight non-module utility functions
 *
 * This file is loaded via a plain <script> tag in layout.twig so the
 * helpers are available synchronously to the inline IIFE that runs during
 * HTML parsing (before deferred module scripts execute).
 *
 * The same functions are also exported as ES modules from shared/utils.js for
 * use in other module-based scripts. Both implementations are kept in sync.
 */

(function () {
  'use strict';

  window.debounce = function debounce(fn, ms) {
    let t;
    return function () {
      const a = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(this, a); }, ms);
    };
  };

  window.throttle = function throttle(fn, ms) {
    let last = 0;
    return function () {
      const now = Date.now();
      if (now - last >= ms) {
        last = now;
        fn.apply(this, arguments);
      }
    };
  };
})();
