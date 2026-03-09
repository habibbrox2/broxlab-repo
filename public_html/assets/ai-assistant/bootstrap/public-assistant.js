import { ensureAssistantStyles } from '../core/styles.js';

// Load shared assistant styles (no build step required)
ensureAssistantStyles(new URL('../styles/assistant-ui.css', import.meta.url).href);

import '../modules/public/app.js';
