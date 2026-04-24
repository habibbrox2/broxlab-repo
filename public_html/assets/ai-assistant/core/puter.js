/**
 * BroxLab AI Assistant - Puter.js Provider Module
 * Provides Puter.js as a fallback AI provider
 */

/**
 * Ensure Puter is ready
 * @returns {Promise<boolean>} True if Puter is ready
 */
export async function ensurePuterReady() {
  if (typeof window === 'undefined') return false;

  // Check if Puter is already loaded
  if (window.puter && window.puter.ai) {
    return true;
  }

  // Load Puter if not already loaded
  if (!window.puter) {
    // Try to load Puter script dynamically
    try {
      await loadPuterScript();
    } catch (err) {
      console.warn('Failed to load Puter.js:', err);
      return false;
    }
  }

  return Boolean(window.puter && window.puter.ai);
}

/**
 * Load Puter.js script dynamically
 * @returns {Promise<void>}
 */
function loadPuterScript() {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://puter.com/js/puter-v2.0.0.js';
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Failed to load Puter script'));
    document.head.appendChild(script);
  });
}

/**
 * Get Puter client instance
 * @returns {Object|null} Puter instance or null
 */
export function getPuterClient() {
  return window.puter || null;
}

/**
 * Call Puter AI with a prompt
 * @param {string} prompt - The prompt to send
 * @param {Object} options - Call options
 * @returns {Promise<Object>} Response object
 */
export async function callPuterAI(prompt, options = {}) {
  const ready = await ensurePuterReady();
  if (!ready) {
    throw new Error('Puter.js is not available');
  }

  try {
    const response = await window.puter.ai.chat(prompt, {
      model: options.model || 'default',
      temperature: options.temperature || 0.7,
      max_tokens: options.max_tokens || 2000,
    });

    return {
      choices: [{
        message: {
          content: response,
          role: 'assistant',
        },
      },],
      model: 'puter-ai',
      usage: {
        prompt_tokens: 0,
        completion_tokens: 0,
        total_tokens: 0,
      },
    };
  } catch (err) {
    throw new Error(`Puter AI error: ${err.message}`);
  }
}

/**
 * Stream Puter AI response
 * @param {string} prompt - The prompt to send
 * @param {Function} onChunk - Callback for each chunk
 * @param {Object} options - Stream options
 * @returns {Promise<void>}
 */
export async function streamPuterAI(prompt, onChunk, options = {}) {
  const ready = await ensurePuterReady();
  if (!ready) {
    throw new Error('Puter.js is not available');
  }

  try {
    // Puter.js may not support streaming, fall back to regular call
    const response = await callPuterAI(prompt, options);
    const content = response.choices[0].message.content;

    // Emit chunks for compatibility
    for (let i = 0; i < content.length; i += 10) {
      onChunk(content.substring(i, i + 10));
      await new Promise(r => setTimeout(r, 50));
    }
  } catch (err) {
    throw new Error(`Puter stream error: ${err.message}`);
  }
}

/**
 * Extract response text from various response formats
 * @param {Object} response - Response object
 * @returns {string} Extracted text
 */
export function extractResponseText(response) {
  if (!response) return '';

  // Handle OpenRouter format
  if (response.choices && response.choices[0] && response.choices[0].message) {
    return response.choices[0].message.content || '';
  }

  // Handle Fireworks format
  if (response.choices && response.choices[0] && response.choices[0].text) {
    return response.choices[0].text || '';
  }

  // Handle Puter.js format
  if (typeof response === 'string') {
    return response;
  }

  // Handle generic content field
  if (response.content) {
    return response.content;
  }

  // Handle generic text field
  if (response.text) {
    return response.text;
  }

  return '';
}

/**
 * Get available Puter models
 * @returns {Promise<Array>} List of available models
 */
export async function getPuterModels() {
  const ready = await ensurePuterReady();
  if (!ready) {
    return [{ id: 'default', name: 'Default AI Model', },];
  }

  try {
    if (window.puter && window.puter.ai && typeof window.puter.ai.listModels === 'function') {
      const models = await window.puter.ai.listModels();
      return models || [{ id: 'default', name: 'Default AI Model', },];
    }
  } catch (err) {
    console.warn('Failed to fetch Puter models:', err);
  }

  return [{ id: 'default', name: 'Default AI Model', },];
}

/**
 * Test Puter connection
 * @returns {Promise<boolean>} True if connection is working
 */
export async function testPuterConnection() {
  try {
    const ready = await ensurePuterReady();
    if (!ready) return false;

    // Try a simple test message
    const response = await callPuterAI('Hello, are you working?', { max_tokens: 10, });
    return Boolean(response && response.choices && response.choices[0]);
  } catch (err) {
    console.warn('Puter connection test failed:', err);
    return false;
  }
}

/**
 * Handle Puter errors
 * @param {Error} error - Error object
 * @returns {string} User-friendly error message
 */
export function handlePuterError(error) {
  const msg = error?.message || '';

  if (msg.includes('network')) {
    return 'Network error. Please check your connection.';
  }
  if (msg.includes('auth')) {
    return 'Authentication failed. Please sign in to Puter.';
  }
  if (msg.includes('timeout')) {
    return 'Request timed out. Please try again.';
  }
  if (msg.includes('rate limit')) {
    return 'Rate limit exceeded. Please wait a moment.';
  }
  if (msg.includes('not available')) {
    return 'Puter.js is not available. Using fallback provider.';
  }

  return 'An error occurred with Puter.js. Please try again.';
}

/**
 * Get Puter system info
 * @returns {Promise<Object>} System information
 */
export function getPuterSystemInfo() {
  if (!window.puter) {
    return {
      available: false,
      version: null,
      features: [],
    };
  }

  return {
    available: true,
    version: window.puter.version || 'unknown',
    features: [
      'chat',
      'stream' in window.puter.ai ? 'streaming' : null,
      'models' in window.puter.ai ? 'multiple-models' : null,
    ].filter(Boolean),
  };
}
