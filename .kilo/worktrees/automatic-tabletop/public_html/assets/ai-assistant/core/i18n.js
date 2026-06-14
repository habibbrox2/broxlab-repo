/**
 * BroxLab AI Assistant - Internationalization Module
 * Provides multi-language support for the assistant
 */

/**
 * Create a language state manager
 * @param {Object} messages - Object mapping language codes to message dictionaries
 * @param {string} defaultLang - Default language code (default: 'en')
 * @returns {Object} Language state API
 */
export function createLanguageState(messages, defaultLang = 'en') {
  let currentLang = defaultLang;
  const listeners = new Set();
  const storageKey = 'brox.ai.language';

  // Load language preference from storage
  function load() {
    try {
      const stored = window.localStorage.getItem(storageKey);
      if (stored && messages[stored]) {
        currentLang = stored;
      }
    } catch (err) {
      console.error('Failed to load language preference:', err);
    }
    return currentLang;
  }

  // Save language preference to storage
  function save() {
    try {
      window.localStorage.setItem(storageKey, currentLang);
    } catch (err) {
      console.error('Failed to save language preference:', err);
    }
  }

  // Get current language
  function getCurrentLanguage() {
    return currentLang;
  }

  // Set language
  function setLanguage(lang) {
    if (!messages[lang]) {
      console.warn(`Language '${lang}' not available, using '${defaultLang}'`);
      currentLang = defaultLang;
    } else {
      currentLang = lang;
    }
    save();
    notifyListeners();
    return currentLang;
  }

  // Get translated string
  function t(key, defaultValue = key) {
    const msg = messages[currentLang];
    if (!msg) return defaultValue;
    return msg[key] || defaultValue;
  }

  // Get translated string with fallback to other languages
  function translate(key, fallbackLang = defaultLang) {
    let msg = messages[currentLang];
    if (msg && msg[key]) return msg[key];

    msg = messages[fallbackLang];
    if (msg && msg[key]) return msg[key];

    return key;
  }

  // Get available languages
  function getAvailableLanguages() {
    return Object.keys(messages);
  }

  // Subscribe to language changes
  function subscribe(callback) {
    listeners.add(callback);
    return () => listeners.delete(callback);
  }

  // Notify listeners of language change
  function notifyListeners() {
    listeners.forEach(callback => {
      try {
        callback(currentLang);
      } catch (err) {
        console.error('Error in language listener:', err);
      }
    });
  }

  // Apply language to DOM elements
  function applyToDom() {
    // Update all elements with data-i18n attribute
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      el.textContent = t(key, key);
    });

    // Update all elements with data-i18n-placeholder attribute
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      el.setAttribute('placeholder', t(key, key));
    });

    // Update all elements with data-i18n-title attribute
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      el.setAttribute('title', t(key, key));
    });
  }

  // Bulk translate object
  function translateObject(obj) {
    const result = {};
    for (const [key, value,] of Object.entries(obj)) {
      if (typeof value === 'string') {
        result[key] = t(value, value);
      } else if (typeof value === 'object' && value !== null) {
        result[key] = translateObject(value);
      } else {
        result[key] = value;
      }
    }
    return result;
  }

  // Initialize by loading from storage
  load();

  return {
    load,
    save,
    getCurrentLanguage,
    setLanguage,
    t,
    translate,
    getAvailableLanguages,
    subscribe,
    applyToDom,
    translateObject,
  };
}

/**
 * Default English messages
 */
export const EN_MESSAGES = {
  assistant_title: 'AI Assistant',
  typing_text: 'Typing...',
  chat_input_placeholder: 'Type your message...',
  no_history: 'No chat history',
  new_chat: 'New Chat',
  clear_history_confirm: 'Are you sure you want to clear history?',
  settings_saved: 'Settings saved',
  error_loading_models: 'Error loading models',
  default_greeting: 'Hello! How can I help you today?',
  session_expired_notice: 'Your session has expired. Starting a new chat...',
  chat_reset_notice: 'Chat history cleared. Starting fresh!',
};

/**
 * Default Bengali messages
 */
export const BN_MESSAGES = {
  assistant_title: 'এআই সহায়ক',
  typing_text: 'টাইপ করছে...',
  chat_input_placeholder: 'আপনার বার্তা লিখুন...',
  no_history: 'কোন চ্যাট ইতিহাস নেই',
  new_chat: 'নতুন চ্যাট',
  clear_history_confirm: 'আপনি কি ইতিহাস সাফ করতে নিশ্চিত?',
  settings_saved: 'সেটিংস সংরক্ষিত হয়েছে',
  error_loading_models: 'মডেল লোড করতে ত্রুটি',
  default_greeting: 'নমস্কার! আজ আমি কীভাবে সাহায্য করতে পারি?',
  session_expired_notice: 'আপনার সেশন মেয়াদ শেষ। নতুন চ্যাট শুরু করছি...',
  chat_reset_notice: 'চ্যাট ইতিহাস সাফ করা হয়েছে। নতুন শুরু!',
};

/**
 * Detect user's language preference
 * @returns {string} Language code ('en' or 'bn' or other available)
 */
export function detectLanguage() {
  // Check localStorage first
  try {
    const stored = window.localStorage.getItem('brox.ai.language');
    if (stored) return stored;
  } catch (err) {
    console.error('Failed to read language from storage:', err);
  }

  // Check browser language
  const browserLang = navigator.language || navigator.userLanguage;
  if (browserLang.startsWith('bn')) return 'bn';
  if (browserLang.startsWith('en')) return 'en';

  // Check for site's current language from data-lang attribute
  const siteLang = document.documentElement.getAttribute('data-lang');
  if (siteLang && (siteLang === 'bn' || siteLang === 'en')) return siteLang;

  // Check for meta tag
  const metaLang = document.querySelector('meta[lang]')?.getAttribute('lang');
  if (metaLang) return metaLang;

  // Default to English
  return 'en';
}

/**
 * Format a message with variables
 * @param {string} template - Message template with {variable} placeholders
 * @param {Object} vars - Variables to substitute
 * @returns {string} Formatted message
 */
export function formatMessage(template, vars = {}) {
  return template.replace(/\{(\w+)\}/g, (match, key) => {
    return vars[key] !== undefined ? String(vars[key]) : match;
  });
}

/**
 * Pluralize a message based on count
 * @param {string} singular - Singular form
 * @param {string} plural - Plural form
 * @param {number} count - Count to determine form
 * @returns {string} Singular or plural form
 */
export function pluralize(singular, plural, count) {
  return count === 1 ? singular : plural;
}
