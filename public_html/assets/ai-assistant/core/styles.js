const LOADED_STYLE_IDS = new Set();

export function ensureAssistantStyles(styleUrl, styleId = 'bb-assistant-ui-css') {
  const href = String(styleUrl || '').trim();
  if (!href) return;

  if (LOADED_STYLE_IDS.has(styleId) || document.getElementById(styleId)) {
    LOADED_STYLE_IDS.add(styleId);
    return;
  }

  const link = document.createElement('link');
  link.id = styleId;
  link.rel = 'stylesheet';
  link.href = href;
  document.head.appendChild(link);
  LOADED_STYLE_IDS.add(styleId);
}
