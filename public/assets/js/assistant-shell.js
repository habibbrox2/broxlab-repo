/**
 * assistant-shell.js
 * Toggles AI assistant chat shell visibility with smooth animations.
 * Uses CSS class .ai-hidden for opacity+transform transitions.
 */
document.addEventListener('DOMContentLoaded', () => {
  const toggleShell = (button) => {
    const shellId = button.getAttribute('aria-controls');
    const shell = shellId ? document.getElementById(shellId) : null;

    if (!shell) {
      return;
    }

    const shouldOpen = shell.classList.contains('ai-hidden');
    const wrapper = shell.closest('[data-ai-role]') || shell.parentElement;

    if (shouldOpen) {
      shell.classList.remove('ai-hidden');
      shell.classList.add('ai-shell');
      button.setAttribute('aria-expanded', 'true');

      // Focus textarea after animation completes
      setTimeout(() => {
        const textarea = wrapper.querySelector('textarea[aria-label="Message input"]');
        if (textarea) textarea.focus();
      }, 300);
    } else {
      shell.classList.add('ai-hidden');
      button.setAttribute('aria-expanded', 'false');
    }

    const openIcon = button.querySelector('[data-icon="open"]');
    const closeIcon = button.querySelector('[data-icon="close"]');

    if (openIcon) {
      openIcon.classList.toggle('hidden', shouldOpen);
    }

    if (closeIcon) {
      closeIcon.classList.toggle('hidden', !shouldOpen);
    }
  };

  // Toggle on trigger button click
  document.querySelectorAll('[data-assistant-trigger]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      toggleShell(button);
    });
  });

  // Close on Escape
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    document.querySelectorAll('[role="dialog"]').forEach((shell) => {
      if (!shell.classList.contains('ai-hidden')) {
        const button = document.querySelector(`[aria-controls="${shell.id}"]`);
        if (button) {
          toggleShell(button);
        }
      }
    });
  });

  // Toggle on Ctrl+K / Cmd+K
  document.addEventListener('keydown', (event) => {
    const isModK = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
    if (!isModK) return;

    event.preventDefault();

    const button = document.querySelector('[data-assistant-trigger]');
    if (button) {
      toggleShell(button);
    }
  });
});
