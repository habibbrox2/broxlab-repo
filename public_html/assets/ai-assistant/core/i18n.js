function tryStorage(storage) {
  return storage || {
    getItem: () => null,
    setItem: () => {}
  };
}

export function createLanguageState({ storageKey, defaultLang = 'bn', storage = window.sessionStorage }) {
  const store = tryStorage(storage);
  let currentLang = defaultLang;

  try {
    const stored = store.getItem(storageKey);
    if (stored === 'bn' || stored === 'en') {
      currentLang = stored;
    }
  } catch {
    currentLang = defaultLang;
  }

  const setLanguage = (lang) => {
    if (lang !== 'bn' && lang !== 'en') return;
    currentLang = lang;
    try {
      store.setItem(storageKey, lang);
    } catch {
      // ignore
    }
  };

  const getLanguage = () => currentLang;

  return { getLanguage, setLanguage };
}
