// Compatibility shim: map "lucide lucide-<name>" classes to the icon font's
// "icon-<name>" classes so the existing icon font CSS (`lucide.css`) can render
// the icons. This runs on DOMContentLoaded and is intentionally tiny.
function mapLucideToIcon() {
  try {
    document.querySelectorAll('.lucide').forEach((el) => {
      // copy any lucide-<name> classes to icon-<name>
      Array.from(el.classList).forEach((c) => {
        if (c && c.indexOf('lucide-') === 0) {
          el.classList.add(`icon-${ c.slice(7)}`);
        }
      });
    });
  } catch (e) {
    // fail silently; this shim is non-critical
    console && console.error && console.error('lucide-compat error', e);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mapLucideToIcon);
} else {
  mapLucideToIcon();
}

window.mapLucideToIcon = mapLucideToIcon;
