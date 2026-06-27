/**
 * Preset auto-selector module (ES Module)
 * Replaces previous IIFE + window.Brox pattern.
 */
import { debounce } from '../shared/utils.js';

/**
 * Initialize preset auto-selectors on the page.
 * Scans container elements with [data-preset-selector] attribute and
 * dynamically loads matching options in an autocomplete fashion.
 */
export function initPresetAutoSelector() {
  const containers = document.querySelectorAll('[data-preset-selector]');
  if (!containers.length) return;

  containers.forEach((container) => {
    const input = container.querySelector('input[type="text"]');
    const list = container.querySelector('[data-preset-list]');
    const hidden = container.querySelector('input[type="hidden"]');
    if (!input || !list) return;

    const fetchUrl = container.getAttribute('data-preset-url') || '';
    if (!fetchUrl) return;

    const debouncedGuess = debounce(async (query) => {
      if (query.length < 2) {
        list.innerHTML = '';
        list.classList.add('hidden');
        return;
      }

      try {
        const res = await fetch(`${fetchUrl}?q=${encodeURIComponent(query)}`, {
          headers: { 'Accept': 'application/json', },
        });
        if (!res.ok) return;
        const data = await res.json();
        const items = Array.isArray(data) ? data : (data.results || data.data || []);

        if (!items.length) {
          list.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">No results</div>';
          list.classList.remove('hidden');
          return;
        }

        list.innerHTML = items
          .map(
            (item) =>
              `<button type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors preset-option" data-value="${encodeURIComponent(item.value ?? item.id ?? '')}">${item.label ?? item.name ?? item.value ?? ''}</button>`
          )
          .join('');
        list.classList.remove('hidden');

        list.querySelectorAll('.preset-option').forEach((btn) => {
          btn.addEventListener('click', () => {
            const val = decodeURIComponent(btn.getAttribute('data-value') || '');
            const label = btn.textContent?.trim() || val;
            input.value = label;
            if (hidden) hidden.value = val;
            list.classList.add('hidden');
            input.dispatchEvent(new CustomEvent('preset-select', { detail: { value: val, label, }, }));
          });
        });
      } catch {
        // Silently fail
      }
    }, 300);

    input.addEventListener('input', () => debouncedGuess(input.value));
    input.addEventListener('blur', () => {
      // Delay hiding to allow click on dropdown items
      setTimeout(() => list.classList.add('hidden'), 200);
    });
    input.addEventListener('focus', () => {
      if (input.value.length >= 2) {
        list.classList.remove('hidden');
      }
    });

    // Close on Escape
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') list.classList.add('hidden');
    });
  });
}
