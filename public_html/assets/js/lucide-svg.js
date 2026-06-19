function prepare() {
  try {
    document.querySelectorAll('.lucide').forEach((el) => {
      Array.from(el.classList).forEach((c) => {
        if (c && c.indexOf('lucide-') === 0) {
          el.setAttribute('data-lucide', c.slice(7));
        }
      });
    });
  } catch (e) {
    console && console.error && console.error('lucide-svg prepare error', e);
  }
}

function runCreate() {
  try {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  } catch (e) {
    console && console.error && console.error('lucide-svg createIcons error', e);
  }
}

function loadAndRun() {
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    runCreate();
    return;
  }
  // Load from local bundle (copied from node_modules/lucide/dist/umd/lucide.min.js)
  const s = document.createElement('script');
  s.src = '/assets/js/libs/lucide.min.js';
  s.async = true;
  s.onload = runCreate;
  s.onerror = (err) => {
    console && console.warn && console.warn('Failed to load local lucide bundle', err);
  };
  document.head.appendChild(s);
}

function init() {
  prepare();
  loadAndRun();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

export { prepare, runCreate, loadAndRun, init };
