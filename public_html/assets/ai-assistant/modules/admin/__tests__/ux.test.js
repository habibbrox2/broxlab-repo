/** @vitest-environment jsdom */

import { describe, it, expect } from 'vitest';
import { setToggleState, formatCharCount } from '../ux.js';

describe('admin UX helpers', () => {
    it('updates toggle state and aria-pressed for on/off controls', () => {
        const button = document.createElement('button');
        button.setAttribute('aria-pressed', 'false');
        button.className = 'bg-slate-800';
        const knob = document.createElement('span');
        knob.className = 'translate-x-0';
        button.appendChild(knob);

        setToggleState(button, true);

        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(button.className).toContain('active');
        expect(button.className).toContain('bg-cyan-400');
        expect(button.className).toContain('text-slate-950');
        expect(knob.className).toContain('translate-x-6');

        setToggleState(button, false);

        expect(button.getAttribute('aria-pressed')).toBe('false');
        expect(button.className).not.toContain('active');
        expect(button.className).toContain('bg-slate-800');
        expect(knob.className).toContain('translate-x-0');
    });

    it('formats character count with a readable label', () => {
        expect(formatCharCount(0)).toBe('0 chars');
        expect(formatCharCount(123)).toBe('123 chars');
    });
});
