import { ensureAssistantStyles } from '../core/styles.js';

ensureAssistantStyles(new URL('./assistant-ui.css', import.meta.url).href);

if (window.PUTER_PROXY_PUBLIC_ONLY) {
  try {
    window.localStorage?.removeItem('puter.auth.token');
  } catch {
    // ignore storage failures in restricted environments
  }
}

void import('../modules/public/app.js');
