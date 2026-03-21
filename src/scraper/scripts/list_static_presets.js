/**
 * Prints static preset definitions from `src/scraper/config.js` as JSON.
 *
 * Usage:
 *   node src/scraper/scripts/list_static_presets.js
 */

import CONFIG from '../config.js';

const sources = CONFIG?.sources || {};

const presets = Object.entries(sources)
    .filter(([key, value]) => key && value && typeof value === 'object' && value.selectors)
    .map(([key, value]) => ({
        preset_key: key,
        name: value.name || key,
        baseUrl: value.baseUrl || '',
        homepageUrl: value.homepageUrl || '',
        selectors: value.selectors || null
    }))
    .sort((a, b) => a.preset_key.localeCompare(b.preset_key));

process.stdout.write(JSON.stringify(presets));

