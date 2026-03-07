function tryStorage(storage) {
  return storage || {
    getItem: () => null,
    setItem: () => {},
    removeItem: () => {}
  };
}

export function createHistoryStore({
  storage = window.sessionStorage,
  chatKey,
  activityKey,
  maxMessages = 40,
  inactivityMs = 30 * 60 * 1000
}) {
  const store = tryStorage(storage);

  const normalize = (row) => {
    if (!row || typeof row !== 'object') return null;
    const role = String(row.role || '').trim().toLowerCase();
    const text = String(row.text || '').trim();
    if (!text || (role !== 'user' && role !== 'assistant')) return null;
    const ts = row.ts ? String(row.ts).trim() : null;
    const responseMsRaw = Number(row.responseMs);
    const responseMs = Number.isFinite(responseMsRaw) ? Math.max(0, Math.round(responseMsRaw)) : null;
    return { role, text, ts, responseMs };
  };

  const trim = (history) => {
    if (!Array.isArray(history)) return [];
    return history.length <= maxMessages ? history : history.slice(history.length - maxMessages);
  };

  const load = () => {
    try {
      const tsRaw = store.getItem(activityKey);
      if (tsRaw) {
        const last = parseInt(tsRaw, 10);
        if (!Number.isNaN(last) && Date.now() - last > inactivityMs) {
          store.removeItem(chatKey);
          store.removeItem(activityKey);
          return { history: [], expired: true };
        }
      }
      const raw = store.getItem(chatKey);
      if (!raw) return { history: [], expired: false };
      const parsed = JSON.parse(raw);
      const history = trim(parsed.map(normalize).filter(Boolean));
      return { history, expired: false };
    } catch {
      return { history: [], expired: false };
    }
  };

  const updateActivity = () => {
    try {
      store.setItem(activityKey, Date.now().toString());
    } catch {
      // ignore
    }
  };

  const save = (history) => {
    try {
      store.setItem(chatKey, JSON.stringify(trim(history)));
      updateActivity();
    } catch {
      // ignore
    }
  };

  return { load, save, trim, updateActivity };
}
