import { ensureAssistantStyles } from '../core/styles.js';

ensureAssistantStyles(new URL('./assistant-ui.css', import.meta.url).href);

import '../modules/public/app.js';
