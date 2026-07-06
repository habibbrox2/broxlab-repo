/**
 * bangla-digits.js
 *
 * Converts English digits (0-9) to Bengali digits (০-৯) in form inputs.
 * Automatically applies to all text inputs within a container and
 * keeps them in Bangla as the user types.
 *
 * Usage:
 *   applyBanglaDigits(document.getElementById('kharijForm'));
 */
(function () {
  'use strict';

  window.toBnNum = function toBnNum(num) {
    if (num === '' || num === null) return '';
    var bn = ['\u09E6', '\u09E7', '\u09E8', '\u09E9', '\u09EA', '\u09EB', '\u09EC', '\u09ED', '\u09EE', '\u09EF'];
    return String(num).split('').map(function (c) {
      return c === '.' ? '.' : (c === '-' ? '-' : (bn[parseInt(c, 10)] || c));
    }).join('');
  };

  function hasEnglishDigits(str) {
    return /[0-9]/.test(str);
  }

  function convertInput(el) {
    if (!el || el.type !== 'text') return;
    if (el.classList.contains('datepicker')) return;
    var val = el.value;
    if (!val || !hasEnglishDigits(val)) return;

    if (!/^[0-9.,]+$/.test(val)) return;

    el.value = toBnNum(val);
  }

  window.applyBanglaDigits = function applyBanglaDigits(container) {
    if (!container) return;

    var inputs = container.querySelectorAll('input[type="text"]');
    inputs.forEach(function (input) {
      convertInput(input);
    });

    container.addEventListener('input', function (e) {
      if (e.target.type === 'text' && !e.target.classList.contains('datepicker')) {
        convertInput(e.target);
      }
    });

    container.addEventListener('change', function (e) {
      if (e.target.type === 'text' && !e.target.classList.contains('datepicker')) {
        convertInput(e.target);
      }
    });
  };
})();
