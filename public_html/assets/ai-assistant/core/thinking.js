/**
 * BroxLab AI Assistant - Thinking Indicator Module
 * Manages the modern thinking animation and step updates.
 *
 * v2.1.0 - XSS fix: use textContent instead of innerHTML for user-provided strings
 */
const DEFAULT_STEPS = [
  { id: 'understanding', label: 'Understanding request' },
  { id: 'planning', label: 'Planning response' },
  { id: 'tools', label: 'Calling tools...' },
  { id: 'generating', label: 'Generating final answer' },
];

function createNoopIndicator() {
  return {
    show: () => {},
    hide: () => {},
    setStatus: () => {},
    setStep: () => {},
    setToolLabel: () => {},
    setCustomSteps: () => {},
    updateFromEvent: () => {},
  };
}

/**
 * Safely escape text for use in HTML via textContent
 * @param {string} text
 * @returns {string}
 */
function safeText(text) {
  return String(text || '');
}

export function createThinkingIndicator(root, options = {}) {
  if (!root) return createNoopIndicator();

  root.classList.add('thinking-indicator');
  root.setAttribute('aria-live', 'polite');
  root.setAttribute('aria-busy', 'true');

  // Build DOM safely using createElement instead of innerHTML for dynamic content
  root.innerHTML = '';

  const panel = document.createElement('div');
  panel.className = 'thinking-panel';

  const header = document.createElement('div');
  header.className = 'thinking-header';

  const nameEl = document.createElement('span');
  nameEl.className = 'ai-name';
  nameEl.textContent = options.aiName || 'Brox AI';

  const statusEl = document.createElement('span');
  statusEl.className = 'thinking-status';
  statusEl.textContent = options.initialStatus || 'Thinking...';

  header.appendChild(nameEl);
  header.appendChild(statusEl);
  panel.appendChild(header);

  const stepsEl = document.createElement('div');
  stepsEl.className = 'tool-steps';
  panel.appendChild(stepsEl);

  const barsEl = document.createElement('div');
  barsEl.className = 'thinking-bars';
  for (let i = 0; i < 4; i++) {
    barsEl.appendChild(document.createElement('span'));
  }
  panel.appendChild(barsEl);

  root.appendChild(panel);

  const stepEls = [];
  const stepList = Array.isArray(options.steps) && options.steps.length > 0 ? options.steps : DEFAULT_STEPS;

  stepList.forEach((step) => {
    const stepEl = document.createElement('div');
    stepEl.className = 'tool-step';

    const dot = document.createElement('span');
    dot.className = 'dot';

    const label = document.createElement('span');
    label.className = 'step-label';
    label.textContent = safeText(step.label);

    stepEl.appendChild(dot);
    stepEl.appendChild(label);
    stepsEl.appendChild(stepEl);
    stepEls.push(stepEl);
  });

  function setStep(index) {
    stepEls.forEach((stepEl, idx) => {
      stepEl.classList.toggle('active', idx <= index);
    });
    return indicator;
  }

  function setStatus(text) {
    if (statusEl) statusEl.textContent = safeText(text);
    return indicator;
  }

  function setToolLabel(label) {
    if (statusEl && label) {
      statusEl.textContent = safeText(label);
    }
    return indicator;
  }

  function setCustomSteps(steps = []) {
    stepsEl.innerHTML = '';
    stepEls.length = 0;
    steps.forEach((step) => {
      const stepEl = document.createElement('div');
      stepEl.className = 'tool-step';

      const dot = document.createElement('span');
      dot.className = 'dot';

      const label = document.createElement('span');
      label.className = 'step-label';
      label.textContent = safeText(step.label);

      stepEl.appendChild(dot);
      stepEl.appendChild(label);
      stepsEl.appendChild(stepEl);
      stepEls.push(stepEl);
    });
    return indicator;
  }

  function updateFromEvent(event = {}) {
    if (!event || typeof event !== 'object') return indicator;

    if (Array.isArray(event.steps) && event.steps.length > 0) {
      setCustomSteps(event.steps);
    }

    if (typeof event.step === 'number') {
      setStep(event.step);
    }

    if (event.status) {
      setStatus(event.status);
    }

    if (event.toolLabel) {
      setToolLabel(event.toolLabel);
    }

    if (event.toolName) {
      setToolLabel(`${event.toolName}${event.status ? `: ${event.status}` : ''}`);
    }

    return indicator;
  }

  function show() {
    root.classList.remove('brox-ai-hidden');
    root.setAttribute('aria-busy', 'true');
    return indicator;
  }

  function hide() {
    root.classList.add('brox-ai-hidden');
    root.removeAttribute('aria-busy');
    return indicator;
  }

  const indicator = {
    show,
    hide,
    setStatus,
    setStep,
    setToolLabel,
    setCustomSteps,
    updateFromEvent,
  };

  return indicator;
}
