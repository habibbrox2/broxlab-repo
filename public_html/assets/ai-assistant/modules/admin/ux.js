/**
 * Shared admin assistant UX helpers for improved interactions.
 */

export function setToggleState(button, isActive) {
    if (!button) return false;

    const active = Boolean(isActive);
    button.setAttribute('aria-pressed', String(active));
    button.classList.toggle('active', active);
    button.classList.toggle('bg-cyan-400', active);
    button.classList.toggle('text-slate-950', active);
    button.classList.toggle('bg-slate-800', !active);
    button.classList.toggle('text-slate-200', !active);

    const knob = button.querySelector('span');
    if (knob) {
        knob.classList.toggle('translate-x-6', active);
        knob.classList.toggle('translate-x-0', !active);
    }

    return active;
}

export function formatCharCount(count) {
    const value = Number(count) || 0;
    return `${value} char${value === 1 ? '' : 's'}`;
}
