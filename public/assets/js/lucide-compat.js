// Compatibility shim: map "lucide lucide-<name>" classes to the icon font's
// "icon-<name>" classes so the icon font CSS (`lucide.css`) renders them.
// Runs on DOMContentLoaded AND keeps watching the DOM so icons injected
// dynamically (weather widget, notification lists, AI assistant messages,
// Livewire/Alpine x-show panels, etc.) get mapped the moment they appear.
// Intentionally tiny and failure-tolerant.
function mapLucideToIcon(root) {
  try {
    (root || document).querySelectorAll('.lucide:not([data-icon-mapped])').forEach((el) => {
      // copy any lucide-<name> classes to icon-<name>
      Array.from(el.classList).forEach((c) => {
        if (c && c.indexOf('lucide-') === 0) {
          el.classList.add(`icon-${ c.slice(7)}`);
        }
      });
      // mark so repeated calls are cheap and idempotent
      el.setAttribute('data-icon-mapped', '1');
    });
  } catch (e) {
    // fail silently; this shim is non-critical
    console && console.error && console.error('lucide-compat error', e);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => mapLucideToIcon());
} else {
  mapLucideToIcon();
}

// Keep mapping icons added later (innerHTML swaps, Alpine/x-show renders,
// fetch-rendered widgets). AttributeFilter limits callback noise; the
// :not([data-icon-mapped]) guard makes re-scans O(new icons).
try {
  const observer = new MutationObserver(() => mapLucideToIcon());
  const start = () => observer.observe(document.body || document.documentElement, {
    childList: true,
    subtree: true,
  });
  if (document.body) {
    start();
  } else {
    document.addEventListener('DOMContentLoaded', start, { once: true, });
  }
} catch (e) {
  // no MutationObserver / body unavailable — initial pass still ran
}

window.mapLucideToIcon = mapLucideToIcon;
