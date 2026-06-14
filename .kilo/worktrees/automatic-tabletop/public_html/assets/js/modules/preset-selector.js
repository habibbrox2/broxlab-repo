/**
 * Auto preset selector
 * Watches the source form URL/content type and auto-selects a matching preset.
 */

const DEFAULT_HINT = 'Select a preset configuration for this source';
const DEBOUNCE_MS = 450;

const debounce = (fn, delay = DEBOUNCE_MS) => {
  let timeoutId;
  return (...args) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn(...args), delay);
  };
};

const createHintUpdater = (hintEl) => {
  if (!hintEl) return () => {};
  const defaultMessage = hintEl.dataset.defaultHint || DEFAULT_HINT;
  hintEl.textContent = defaultMessage;
  return (text) => {
    if (!text) {
      hintEl.textContent = defaultMessage;
      return;
    }
    hintEl.textContent = text;
  };
};

export function initPresetAutoSelector() {
  const urlInput = document.getElementById('url');
  const contentTypeSelect = document.getElementById('content_type');
  const presetSelect = document.getElementById('presets');
  const hintEl = document.getElementById('presetAutoHint');

  if (!presetSelect || !urlInput) {
    return;
  }

  const updateHint = createHintUpdater(hintEl);
  let manualOverride = false;

  const resetOverride = () => {
    manualOverride = false;
  };

  presetSelect.addEventListener('change', () => {
    manualOverride = true;
  });

  const applyPreset = ({ key, name, reason, }) => {
    if (manualOverride || !key) {
      return;
    }
    presetSelect.value = key;
    updateHint(`Matched preset "${name}" (${reason || 'auto'})`);
  };

  const guessPreset = async () => {
    const url = urlInput.value.trim();
    const contentType = contentTypeSelect?.value.trim() || '';
    if (url === '' && contentType === '') {
      updateHint('');
      return;
    }
    try {
      const params = new URLSearchParams();
      if (url) params.set('url', url);
      if (contentType) params.set('content_type', contentType);
      const response = await fetch(`/api/admin/scraper/presets/guess?${params.toString()}`, {
        headers: {
          Accept: 'application/json',
        },
      });
      if (!response.ok) {
        updateHint('');
        return;
      }
      const payload = await response.json();
      if (!payload.success) {
        updateHint('');
        return;
      }
      if (payload.preset?.key) {
        applyPreset(payload.preset);
      } else if (!manualOverride) {
        updateHint('');
      }
    } catch (error) {
      console.error('Preset auto-select failed:', error);
      updateHint('');
    }
  };

  const debouncedGuess = debounce(() => {
    resetOverride();
    guessPreset();
  });

  urlInput.addEventListener('input', debouncedGuess);

  if (contentTypeSelect) {
    contentTypeSelect.addEventListener('change', debouncedGuess);
  }

  // Run on init if URL already provided
  guessPreset();
}
