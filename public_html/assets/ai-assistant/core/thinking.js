/**
 * BroxLab AI Assistant - Thinking Indicator Module
 * Manages the modern thinking animation and step updates.
 */
const DEFAULT_STEPS = [
  { id: 'understanding', label: 'Understanding request', },
  { id: 'planning', label: 'Planning response', },
  { id: 'tools', label: 'Calling tools...', },
  { id: 'generating', label: 'Generating final answer', },
];

function createNoopIndicator() {
  return {
    show: () => { },
    hide: () => { },
    setStatus: () => { },
    setStep: () => { },
    setToolLabel: () => { },
    setCustomSteps: () => { },
  };
}

export function createThinkingIndicator(root, options = {}) {
  if (!root) return createNoopIndicator();

  root.classList.add('thinking-indicator');
  root.setAttribute('aria-live', 'polite');
  root.setAttribute('aria-busy', 'true');

  root.innerHTML = `
    <div class="ai-avatar">
      <div class="pulse-ring"></div>
      <div class="pulse-ring delay"></div>
      <div class="core"></div>
    </div>
    <div class="thinking-panel">
      <div class="thinking-header">
        <span class="ai-name">${options.aiName || 'Brox AI'}</span>
        <span class="thinking-status">${options.initialStatus || 'Thinking...'}</span>
      </div>
      <div class="tool-steps"></div>
      <div class="thinking-bars">
        <span></span><span></span><span></span><span></span>
      </div>
    </div>
  `;

  const statusEl = root.querySelector('.thinking-status');
  const stepsEl = root.querySelector('.tool-steps');
  const stepEls = [];
  const stepList = Array.isArray(options.steps) && options.steps.length > 0 ? options.steps : DEFAULT_STEPS;

  stepList.forEach((step) => {
    const stepEl = document.createElement('div');
    stepEl.className = 'tool-step';
    stepEl.innerHTML = `<span class="dot"></span><span class="step-label">${step.label}</span>`;
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
    if (statusEl) statusEl.textContent = text || '';
    return indicator;
  }

  function setToolLabel(label) {
    if (statusEl && label) {
      statusEl.textContent = label;
    }
    return indicator;
  }

  function setCustomSteps(steps = []) {
    stepsEl.innerHTML = '';
    stepEls.length = 0;
    steps.forEach((step) => {
      const stepEl = document.createElement('div');
      stepEl.className = 'tool-step';
      stepEl.innerHTML = `<span class="dot"></span><span class="step-label">${step.label}</span>`;
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
    return indicator;
  }

  function hide() {
    root.classList.add('brox-ai-hidden');
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
