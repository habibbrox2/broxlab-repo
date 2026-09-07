/**
 * Event Delegation Utilities
 *
 * Provides reusable delegation helpers for pages that use data-action attributes
 * instead of inline event handlers. Supports click, change, and keydown events.
 *
 * Usage (ES module):
 *   import { delegateClick, delegateChange } from './shared/events.js';
 *
 *   delegateClick(document, {
 *     'open-preview': (e, target) => openPreview(target.dataset.slug),
 *     'close-modal':  () => closeModal(),
 *   });
 *
 * Usage (inline <script> — exposed on window after layout loads):
 *   window.delegateClick(document, { 'my-action': handler });
 *
 * Also supports scoped delegation (e.g. inside a container):
 *   delegateClick(containerEl, { 'delete': handler });
 */

/**
 * Set up event delegation on an element for [data-action] targets.
 *
 * @param {string} eventType - DOM event type ('click', 'change', 'keydown', etc.)
 * @param {EventTarget} element - Root element to listen on (document, container, etc.)
 * @param {Object<string, Function>} handlers - Map of action names to handler functions.
 *   Each handler receives (event, target) where target is the matched [data-action] element.
 * @returns {Function} Cleanup function that removes the event listener.
 */
function delegateEvent(eventType, element, handlers) {
  if (!element || !handlers || typeof handlers !== 'object') {
    throw new TypeError('delegateEvent: element and handlers object required');
  }

  const listener = (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;
    const handler = handlers[action];
    if (handler) {
      handler(e, target);
    }
  };

  element.addEventListener(eventType, listener);

  return function cleanup() {
    element.removeEventListener(eventType, listener);
  };
}

/**
 * Shorthand: delegate click events on [data-action] elements.
 * @param {EventTarget} element - Root element
 * @param {Object<string, Function>} handlers - Action name -> handler map
 * @returns {Function} Cleanup function
 */
function delegateClick(element, handlers) {
  return delegateEvent('click', element, handlers);
}

/**
 * Shorthand: delegate change events on [data-action] elements.
 * @param {EventTarget} element - Root element
 * @param {Object<string, Function>} handlers - Action name -> handler map
 * @returns {Function} Cleanup function
 */
function delegateChange(element, handlers) {
  return delegateEvent('change', element, handlers);
}

/**
 * Shorthand: delegate keydown events on [data-action] elements.
 * @param {EventTarget} element - Root element
 * @param {Object<string, Function>} handlers - Action name -> handler map
 * @returns {Function} Cleanup function
 */
function delegateKeydown(element, handlers) {
  return delegateEvent('keydown', element, handlers);
}

/**
 * Set up delegation for multiple event types at once.
 *
 * @param {EventTarget} element - Root element
 * @param {Object} config - Map of event types to handler maps.
 *   Example: { click: { 'save': handler }, change: { 'switch': handler } }
 * @returns {Function} Cleanup function that removes all listeners
 */
function delegateAll(element, config) {
  const cleanups = [];
  for (const [eventType, handlers,] of Object.entries(config)) {
    cleanups.push(delegateEvent(eventType, element, handlers));
  }
  return function cleanupAll() {
    cleanups.forEach((fn) => fn());
  };
}

// Expose on window for inline <script> consumers in Twig templates
if (typeof window !== 'undefined') {
  window.delegateEvent = delegateEvent;
  window.delegateClick = delegateClick;
  window.delegateChange = delegateChange;
  window.delegateKeydown = delegateKeydown;
  window.delegateAll = delegateAll;
}
