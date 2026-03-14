// Editor shared utilities
(function (global) {
    'use strict';

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text == null ? '' : text).replace(/[&<>"']/g, (m) => map[m]);
    }

    function rgbToHex(rgb) {
        const match = String(rgb || '').match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
        if (!match) return null;
        const r = parseInt(match[1], 10);
        const g = parseInt(match[2], 10);
        const b = parseInt(match[3], 10);
        if ([r, g, b].some((n) => Number.isNaN(n))) return null;
        return '#' + [r, g, b].map((n) => n.toString(16).padStart(2, '0')).join('').toLowerCase();
    }

    function normalizeColor(color) {
        const raw = String(color || '').trim();
        if (!raw || raw === 'transparent' || raw === 'rgba(0, 0, 0, 0)') return null;

        if (/^#[0-9a-f]{6}$/i.test(raw)) return raw.toLowerCase();
        if (/^#[0-9a-f]{3}$/i.test(raw)) {
            return '#' + raw.slice(1).split('').map((c) => c + c).join('').toLowerCase();
        }
        if (/^rgba?/i.test(raw)) return rgbToHex(raw);

        try {
            const el = document.createElement('div');
            el.style.color = raw;
            el.style.position = 'absolute';
            el.style.left = '-99999px';
            document.body.appendChild(el);
            const computed = window.getComputedStyle(el).color;
            if (el.parentNode) el.parentNode.removeChild(el);
            return rgbToHex(computed);
        } catch (e) {
            return null;
        }
    }

    function installUtilsHelpers() {
        global.RTE_utils = global.RTE_utils || {};
        global.RTE_utils.escapeHtml = escapeHtml;
        global.RTE_utils.rgbToHex = rgbToHex;
        global.RTE_utils.normalizeColor = normalizeColor;
        return true;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installUtilsHelpers, escapeHtml, rgbToHex, normalizeColor };
    }
    if (typeof window !== 'undefined') {
        window.installUtilsHelpers = installUtilsHelpers;
        window.RTE_utils = window.RTE_utils || {};
        window.RTE_utils.escapeHtml = escapeHtml;
        window.RTE_utils.rgbToHex = rgbToHex;
        window.RTE_utils.normalizeColor = normalizeColor;
    }
})(typeof window !== 'undefined' ? window : {});
