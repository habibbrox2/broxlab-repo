import { puter } from '@heyputer/puter.js';

const UI = {
    wrapper: document.getElementById('adminAssistantWrapper'),
    chat: document.getElementById('adminAssistantChat'),
    messages: document.getElementById('assistantMessages'),
    input: document.getElementById('assistantInput'),
    sendBtn: document.getElementById('sendToAssistant'),
    toggleBtn: document.getElementById('adminAssistantBtn'),
    closeBtn: document.getElementById('closeAssistant'),
    status: document.getElementById('adminAssistantStatus'),
    title: document.getElementById('adminAssistantTitle'),
    langBnBtn: document.getElementById('adminAssistantLangBn'),
    langEnBtn: document.getElementById('adminAssistantLangEn'),
    loading: document.getElementById('adminAssistantLoading'),
    typingText: document.getElementById('adminAssistantTypingText')
};

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const CHAT_MODEL = typeof window.BROX_ADMIN_ASSISTANT_MODEL === 'string'
    ? window.BROX_ADMIN_ASSISTANT_MODEL.trim()
    : '';

const CHAT_STORAGE_KEY = 'brox.adminAssistant.chat.v2';
const LAST_ACTIVITY_KEY = 'brox.adminAssistant.lastActivity.v2';
const LANGUAGE_KEY = 'brox.adminAssistant.language.v2';
const MAX_STORED_MESSAGES = 40;
const INACTIVITY_LIMIT_MS = 30 * 60 * 1000;
const MAX_TOOL_ROUNDS = 4;
const PUTER_POPUP_WIDTH = 600;
const PUTER_POPUP_HEIGHT = 700;
const PUTER_SIGN_IN_TIMEOUT_MS = 2 * 60 * 1000;
const CHAT_MODEL_PREFERENCES = [
    'openai/gpt-4.1-mini',
    'openai/gpt-4o-mini',
    'openai/gpt-4.1',
    'openai/gpt-4o',
    'anthropic/claude-3-5-sonnet',
    'anthropic/claude-3-7-sonnet',
    'google/gemini-2.0-flash',
    'google/gemini-2.5-flash',
    'gpt-4.1-mini',
    'gpt-4o-mini',
    'gpt-4.1',
    'gpt-4o',
    'claude-3-5-sonnet',
    'claude-3-7-sonnet',
    'gemini-2.0-flash',
    'gemini-2.5-flash'
];
const TYPEWRITER_CHUNK_DELAY_MS = 16;
const TYPEWRITER_MAX_STEPS = 90;
const ASSISTANT_SITE_URL = 'https://broxlab.online';

const REDIRECT_ACTIONS = new Set([
    'create_mobile',
    'edit_mobile',
    'create_post',
    'edit_post',
    'create_page',
    'edit_page',
    'create_service',
    'edit_service'
]);

const ACTION_PERMISSIONS = {
    create_post: 'post.create',
    edit_post: 'post.edit',
    delete_post: 'post.delete',
    create_service: 'service.create',
    edit_service: 'service.edit',
    delete_service: 'service.delete',
    create_page: 'page.create',
    edit_page: 'page.edit',
    delete_page: 'page.delete',
    create_category: 'category.create',
    edit_category: 'category.edit',
    delete_category: 'category.delete',
    create_tag: 'tag.create',
    edit_tag: 'tag.edit',
    delete_tag: 'tag.delete',
    create_mobile: 'mobile.create',
    edit_mobile: 'mobile.edit',
    delete_mobile: 'mobile.delete'
};

const LINK_ACTIONS = {
    upload_file: { url: '/admin/media/upload', label: '/admin/media/upload' },
    view_uploads: { url: '/admin/media', label: '/admin/media' },
    manage_service_applications: { url: '/admin/applications', label: '/admin/applications' },
    manage_payments: { url: '/admin/payments', label: '/admin/payments' },
    manage_chats: { url: '/admin/contact', label: '/admin/contact' }
};

const I18N = {
    bn: {
        title: 'ব্রক্স সহকারী',
        input_placeholder: 'আপনার নির্দেশ লিখুন...',
        typing_text: 'টাইপ করছে...',
        default_greeting: 'আমি আপনার ব্রক্স অ্যাডমিন সহকারী। পোস্ট, পেজ, সার্ভিস, মিডিয়া, অ্যানালিটিক্স বা অন্য অ্যাডমিন কাজ নিয়ে জিজ্ঞেস করুন। প্রথম মেসেজে Puter সাইন-ইন চাইতে পারে।',
        status_initializing: 'চালু হচ্ছে...',
        status_ready: 'সহকারী প্রস্তুত',
        status_ready_to_connect: 'বার্তা পাঠালে সংযুক্ত হবে',
        status_connecting: 'Puter-এ সংযুক্ত হচ্ছে...',
        status_initialization_failed: 'সহকারী চালু করা যায়নি',
        status_permission_denied: 'অনুমতি প্রত্যাখ্যাত',
        status_link_provided: 'লিংক দেওয়া হয়েছে',
        status_new_chat_started: 'নতুন চ্যাট শুরু হয়েছে',
        status_analysis_complete: 'বিশ্লেষণ সম্পন্ন',
        status_task_completed: 'কাজ সম্পন্ন',
        status_action_failed: 'অ্যাকশন ব্যর্থ',
        status_thinking: 'ভাবছে...',
        status_error: 'ত্রুটি ঘটেছে',
        status_navigating: 'পেজ খোলা হচ্ছে...',
        status_response: 'রেসপন্স',
        notice_session_expired: 'আগের সেশন নিষ্ক্রিয়তার কারণে মেয়াদোত্তীর্ণ হয়েছে। নতুন সেশন শুরু হয়েছে।',
        notice_chat_reset: 'নিষ্ক্রিয়তার কারণে চ্যাট রিসেট করা হয়েছে।',
        notice_new_chat: 'নতুন চ্যাট শুরু হয়েছে।',
        notice_unknown_tool: 'সহকারী একটি অচেনা টুল ব্যবহার করতে চেয়েছে। আবার চেষ্টা করুন।',
        error_puter_missing: 'Puter client লোড হয়নি।',
        error_sign_in_required: 'চালিয়ে যেতে Puter-এ সাইন-ইন প্রয়োজন।',
        error_sign_in_popup_blocked: 'Puter সাইন-ইন popup খোলা যায়নি। ব্রাউজারে popup allow করে আবার চেষ্টা করুন।',
        error_sign_in_popup_closed: 'Puter সাইন-ইন সম্পন্ন হওয়ার আগে popup বন্ধ হয়েছে।',
        error_sign_in_timeout: 'Puter সাইন-ইনে সময়সীমা অতিক্রম হয়েছে। আবার চেষ্টা করুন।',
        error_generic: 'দুঃখিত, একটি সমস্যা হয়েছে। আবার চেষ্টা করুন।',
        action_success: 'কাজটি সফলভাবে সম্পন্ন হয়েছে।',
        action_failed: 'কাজটি সম্পন্ন করা যায়নি',
        permission_denied: 'এই কাজের জন্য আপনার প্রয়োজনীয় অনুমতি নেই।',
        opening_page: 'প্রাসঙ্গিক পেজ খোলা হচ্ছে...',
        link_intro: 'এখান থেকে খুলুন:',
        analytics_loaded: 'অ্যানালিটিক্স ডেটা লোড হয়েছে।'
    },
    en: {
        title: 'Brox Assistant',
        input_placeholder: 'Type your instruction...',
        typing_text: 'Typing...',
        default_greeting: 'I am your Brox admin assistant. Ask about posts, pages, services, media, analytics, or other admin work. Puter may ask you to sign in on your first message.',
        status_initializing: 'Initializing...',
        status_ready: 'Assistant Ready',
        status_ready_to_connect: 'Will connect on first message',
        status_connecting: 'Connecting to Puter...',
        status_initialization_failed: 'Assistant Unavailable',
        status_permission_denied: 'Permission Denied',
        status_link_provided: 'Link provided',
        status_new_chat_started: 'New chat started',
        status_analysis_complete: 'Analysis Complete',
        status_task_completed: 'Task Completed',
        status_action_failed: 'Action Failed',
        status_thinking: 'Thinking...',
        status_error: 'Error occurred',
        status_navigating: 'Opening page...',
        status_response: 'Response',
        notice_session_expired: 'The previous session expired due to inactivity. A new session has started.',
        notice_chat_reset: 'Chat reset due to inactivity.',
        notice_new_chat: 'Started a fresh chat.',
        notice_unknown_tool: 'The assistant tried to use an unknown tool. Please try again.',
        error_puter_missing: 'Puter client is not loaded.',
        error_sign_in_required: 'You need to sign in with Puter before continuing.',
        error_sign_in_popup_blocked: 'The Puter sign-in popup was blocked. Allow popups and try again.',
        error_sign_in_popup_closed: 'The Puter sign-in popup was closed before authentication completed.',
        error_sign_in_timeout: 'Puter sign-in timed out. Please try again.',
        error_generic: 'Sorry, something went wrong. Please try again.',
        action_success: 'The action completed successfully.',
        action_failed: 'The action could not be completed',
        permission_denied: 'You do not have permission for this action.',
        opening_page: 'Opening the relevant page...',
        link_intro: 'Open it here:',
        analytics_loaded: 'Analytics data loaded.'
    }
};

const STATIC_ASSISTANT_REPLIES = {
    bn: {
        name: `আমি brox বলছি, BroxLab সহকারী হিসেবে আপনার অ্যাডমিন কাজ দ্রুত করতে সাহায্য করি।`,
        about: `আমি brox বলছি। BroxLab হলো ${ASSISTANT_SITE_URL} শিরোনামের Bengali-first tech platform, যেখানে পোস্ট, পেজ, মোবাইল, সার্ভিস ও ডিজিটাল কনটেন্ট পরিচালনা করা হয়।`
    },
    en: {
        name: 'I am Brox, the BroxLab assistant helping you move through admin work faster.',
        about: `I am Brox. BroxLab is the Bengali-first tech platform at ${ASSISTANT_SITE_URL}, where posts, pages, mobiles, services, and digital content are managed.`
    }
};

const ADMIN_ACTION_TOOL = {
    type: 'function',
    function: {
        name: 'admin_action',
        description: 'Run a Brox admin panel action such as opening a prefilled create/edit form, deleting content, reading analytics, opening admin pages, or starting a new chat.',
        parameters: {
            type: 'object',
            properties: {
                action: {
                    type: 'string',
                    enum: [
                        'create_post',
                        'edit_post',
                        'delete_post',
                        'create_service',
                        'edit_service',
                        'delete_service',
                        'create_page',
                        'edit_page',
                        'delete_page',
                        'create_category',
                        'edit_category',
                        'delete_category',
                        'create_tag',
                        'edit_tag',
                        'delete_tag',
                        'create_mobile',
                        'edit_mobile',
                        'delete_mobile',
                        'manage_service_applications',
                        'manage_payments',
                        'create_user',
                        'delete_user',
                        'create_role',
                        'delete_role',
                        'send_notification',
                        'get_analytics_summary',
                        'get_visitor_stats',
                        'get_top_content',
                        'upload_file',
                        'view_uploads',
                        'manage_chats',
                        'start_new_chat'
                    ]
                },
                params: {
                    type: 'object',
                    description: 'Parameters for the selected action. Use flat key/value pairs. For create/edit actions include every field that should prefill in the destination form. For delete actions include id. For analytics include date range, type, or limit as needed.',
                    additionalProperties: true
                }
            },
            required: ['action']
        }
    }
};

let chatHistory = [];
let historyExpired = false;
let currentLang = 'bn';
let currentStatusKey = 'status_initializing';
let puterReady = false;
let puterAuthMessageId = 1;
let pendingPuterSignIn = null;
let resolvedChatModel = CHAT_MODEL || '';
let chatModelDiscoveryPromise = null;

function t(key) {
    const table = I18N[currentLang] || I18N.bn;
    return table[key] || I18N.bn[key] || key;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function linkifyText(text) {
    const escaped = escapeHtml(text);
    return escaped.replace(/(https?:\/\/[^\s<]+|\/[A-Za-z0-9\-._~:/?#[\]@!$&'()*+,;=%]+)/g, (match) => {
        const href = match.startsWith('http') ? match : match;
        return `<a href="${escapeHtml(href)}" ${match.startsWith('http') ? 'target="_blank" rel="noopener noreferrer"' : ''}>${match}</a>`;
    }).replace(/\n/g, '<br>');
}

function formatMessageBody(text, trustedHtml = false) {
    return trustedHtml ? String(text ?? '') : linkifyText(String(text ?? ''));
}

function scrollMessagesToBottom() {
    if (!UI.messages) return;
    UI.messages.scrollTop = UI.messages.scrollHeight;
}

function sleep(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

async function animateMessageBody(body, text, options = {}) {
    if (!body) return;

    const { trustedHtml = false } = options;
    const normalizedText = String(text ?? '');
    if (!normalizedText) {
        body.innerHTML = '';
        return;
    }

    if (trustedHtml || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        body.innerHTML = formatMessageBody(normalizedText, trustedHtml);
        scrollMessagesToBottom();
        return;
    }

    const chunkSize = Math.max(1, Math.ceil(normalizedText.length / TYPEWRITER_MAX_STEPS));
    for (let index = 0; index < normalizedText.length; index += chunkSize) {
        body.textContent = normalizedText.slice(0, index + chunkSize);
        scrollMessagesToBottom();
        await sleep(TYPEWRITER_CHUNK_DELAY_MS);
    }

    body.innerHTML = formatMessageBody(normalizedText, trustedHtml);
    scrollMessagesToBottom();
}

function getStaticAssistantReply(text) {
    const input = String(text || '').trim();
    if (!input) return null;

    const lowered = input.toLowerCase();
    const asksName = /(^|\b)(your name|who are you|what is your name|tell me your name|name\?)\b/i.test(input)
        || input.includes('তোমার নাম')
        || input.includes('আপনার নাম')
        || input.includes('নাম কি')
        || input.includes('নাম কী');
    if (asksName) {
        return STATIC_ASSISTANT_REPLIES[currentLang]?.name || STATIC_ASSISTANT_REPLIES.bn.name;
    }

    const asksAbout = lowered.includes('broxlab')
        || lowered.includes('broxlab.online')
        || lowered.includes('about yourself')
        || lowered.includes('about brox')
        || lowered.includes('tell me about yourself')
        || input.includes('নিজের সম্পর্কে')
        || input.includes('ব্রক্সল্যাব')
        || input.includes('broxlab.online');

    if (asksAbout) {
        return STATIC_ASSISTANT_REPLIES[currentLang]?.about || STATIC_ASSISTANT_REPLIES.bn.about;
    }

    return null;
}

function loadLanguage() {
    try {
        const stored = sessionStorage.getItem(LANGUAGE_KEY);
        if (stored === 'bn' || stored === 'en') {
            currentLang = stored;
        }
    } catch (err) {
        currentLang = 'bn';
    }
}

function updateLanguageButtons() {
    const setState = (button, active) => {
        if (!button) return;
        button.classList.toggle('active', active);
        button.classList.toggle('btn-light', active);
        button.classList.toggle('btn-outline-light', !active);
    };

    setState(UI.langBnBtn, currentLang === 'bn');
    setState(UI.langEnBtn, currentLang === 'en');
}

function setStatus(keyOrText, options = {}) {
    if (!UI.status) return;

    if (options.raw) {
        currentStatusKey = null;
        UI.status.textContent = keyOrText;
        return;
    }

    currentStatusKey = keyOrText;
    UI.status.textContent = t(keyOrText);
}

function applyLanguage() {
    if (UI.title) UI.title.textContent = t('title');
    if (UI.input) UI.input.setAttribute('placeholder', t('input_placeholder'));
    if (UI.typingText) UI.typingText.textContent = t('typing_text');
    if (currentStatusKey) setStatus(currentStatusKey);
    updateLanguageButtons();
}

function setLanguage(lang) {
    if (lang !== 'bn' && lang !== 'en') return;
    currentLang = lang;

    try {
        sessionStorage.setItem(LANGUAGE_KEY, lang);
    } catch (err) {
        // ignore storage failures
    }

    applyLanguage();
    renderChatHistory();
}

function setTyping(active) {
    if (!UI.loading) return;
    UI.loading.classList.toggle('d-none', !active);
    UI.loading.classList.toggle('active', active);
}

function formatMessageMeta(role, ts, responseMs) {
    const parts = [];
    const parsed = ts ? new Date(ts) : null;

    if (parsed && !Number.isNaN(parsed.getTime())) {
        const locale = currentLang === 'bn' ? 'bn-BD' : 'en-US';
        parts.push(new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(parsed));
    }

    if (role === 'assistant' && Number.isFinite(responseMs)) {
        const duration = responseMs < 1000 ? `${responseMs}ms` : `${(responseMs / 1000).toFixed(1)}s`;
        parts.push(`${t('status_response')}: ${duration}`);
    }

    return parts.join(' • ');
}

function normalizeStoredMessage(row) {
    if (!row || typeof row !== 'object') return null;

    const role = String(row.role || '').trim().toLowerCase();
    const text = String(row.text || '').trim();
    if (!text || (role !== 'user' && role !== 'assistant')) return null;

    const ts = row.ts ? String(row.ts).trim() : null;
    const responseMsRaw = Number(row.responseMs);
    const responseMs = Number.isFinite(responseMsRaw) ? Math.max(0, Math.round(responseMsRaw)) : null;

    return { role, text, ts, responseMs };
}

function trimHistory(history) {
    if (!Array.isArray(history)) return [];
    return history.length <= MAX_STORED_MESSAGES ? history : history.slice(history.length - MAX_STORED_MESSAGES);
}

function loadChatHistory() {
    try {
        const tsRaw = sessionStorage.getItem(LAST_ACTIVITY_KEY);
        if (tsRaw) {
            const last = parseInt(tsRaw, 10);
            if (!Number.isNaN(last) && Date.now() - last > INACTIVITY_LIMIT_MS) {
                historyExpired = true;
                sessionStorage.removeItem(CHAT_STORAGE_KEY);
                sessionStorage.removeItem(LAST_ACTIVITY_KEY);
                return [];
            }
        }

        const raw = sessionStorage.getItem(CHAT_STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return trimHistory(parsed.map(normalizeStoredMessage).filter(Boolean));
    } catch (err) {
        return [];
    }
}

function updateLastActivity() {
    try {
        sessionStorage.setItem(LAST_ACTIVITY_KEY, Date.now().toString());
    } catch (err) {
        // ignore
    }
}

function saveChatHistory() {
    try {
        sessionStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(trimHistory(chatHistory)));
        updateLastActivity();
    } catch (err) {
        // ignore
    }
}

function clearRenderedMessages() {
    if (!UI.messages) return;
    UI.messages.querySelectorAll('.message').forEach((node) => node.remove());
}

function appendMessage(role, text, options = {}) {
    if (!UI.messages) return null;

    const {
        persist = true,
        ts = new Date().toISOString(),
        responseMs = null,
        trustedHtml = false,
        deferContent = false
    } = options;

    if (persist) {
        chatHistory = trimHistory([...chatHistory, { role, text, ts, responseMs }]);
        saveChatHistory();
    }

    const message = document.createElement('div');
    message.className = `message ${role}`;

    const body = document.createElement('div');
    body.className = 'message-content';
    if (!deferContent) {
        body.innerHTML = formatMessageBody(text, trustedHtml);
    }
    message.appendChild(body);

    const metaText = formatMessageMeta(role, ts, responseMs);
    if (metaText) {
        const meta = document.createElement('div');
        meta.className = 'message-time';
        meta.textContent = metaText;
        message.appendChild(meta);
    }

    UI.messages.appendChild(message);
    scrollMessagesToBottom();
    return message;
}

async function appendAssistantMessage(text, options = {}) {
    const animate = options.animate === true;
    const trustedHtml = options.trustedHtml === true;
    const message = appendMessage('assistant', text, {
        ...options,
        trustedHtml,
        deferContent: animate
    });

    if (!message) return null;

    if (animate) {
        const body = message.querySelector('.message-content');
        await animateMessageBody(body, text, { trustedHtml });
    }

    return message;
}

function renderWelcomeMessage() {
    appendMessage('assistant', t('default_greeting'), { persist: false });
}

function renderChatHistory() {
    clearRenderedMessages();

    if (!chatHistory.length) {
        renderWelcomeMessage();
        return;
    }

    chatHistory.forEach((entry) => {
        appendMessage(entry.role, entry.text, {
            persist: false,
            ts: entry.ts,
            responseMs: entry.responseMs
        });
    });
}

function buildSystemPrompt() {
    const sections = [
        // === IDENTITY ===
        'You are Brox, the official AI assistant for BroxBhai Admin Dashboard.',
        'You represent BroxLab and help users manage their admin panel efficiently.',
        `BroxLab website: ${ASSISTANT_SITE_URL}`,

        // === CONTEXT ===
        `Current UI language: ${currentLang === 'bn' ? 'Bangla' : 'English'}.`,
        `Current admin page: ${window.location.pathname}.`,
        `Timestamp: ${new Date().toISOString()}.`,

        // === COMMUNICATION STYLE ===
        '## Response Guidelines',
        '- Match the user\'s language; default to the current UI language.',
        '- Be concise, friendly, and action-oriented.',
        '- Use bullet points for lists; avoid walls of text.',
        '- Never expose internal tool syntax, JSON fences, or raw action calls.',
        '- If unsure, ask for clarification rather than guessing.',
        '- Celebrate successes briefly (e.g., "Done! ✓").',

        // === IDENTITY QUERIES ===
        '## Identity & Branding',
        '- Name question → Answer as "Brox" and mention BroxLab with the website URL.',
        '- About BroxLab → Describe it as a creative digital platform; include the URL.',
        '- Never claim to be human or another AI service.',

        // === TOOL USAGE ===
        '## Tool: admin_action',
        'Use when the user requests:',
        '• Navigation or page redirects',
        '• CRUD operations on content (posts, pages, services, etc.)',
        '• Analytics, stats, or visitor data',
        '• Media uploads or file management',
        '• Notifications, users, or roles management',
        '• Starting a fresh chat session',

        '### Available Actions (grouped):',
        '**Content Management:**',
        '  create_post | edit_post | delete_post',
        '  create_page | edit_page | delete_page',
        '  create_service | edit_service | delete_service',
        '  create_category | edit_category | delete_category',
        '  create_tag | edit_tag | delete_tag',
        '',
        '**Mobile & Applications:**',
        '  create_mobile | edit_mobile | delete_mobile',
        '  manage_service_applications',
        '',
        '**Administration:**',
        '  create_user | delete_user',
        '  create_role | delete_role',
        '  send_notification',
        '',
        '**Analytics & Data:**',
        '  get_analytics_summary',
        '  get_visitor_stats',
        '  get_top_content',
        '',
        '**Media & Communication:**',
        '  upload_file | view_uploads',
        '  manage_payments | manage_chats',
        '',
        '**Session:**',
        '  start_new_chat',

        '### Tool Rules:',
        '- Call at most ONE action per turn unless a result explicitly requires follow-up.',
        '- For create/edit requests with missing required fields → ask the user first.',
        '- For create/edit of posts, pages, services → generated content should be clean, minimal HTML.',
        '- Always confirm destructive actions (delete) before executing.',

        // === WEB SEARCH ===
        '## Web Search',
        '- Use built-in web search for current facts, external references, or real-time data.',
        '- Cite sources briefly when using external information.',

        // === ERROR HANDLING ===
        '## Error Handling',
        '- If a tool call fails, explain the issue clearly and suggest alternatives.',
        '- For permission errors, guide users to the appropriate settings or admin.',
        '- Never expose stack traces or technical error details to the user.',

        // === BOUNDARIES ===
        '## What NOT To Do',
        '- Do not perform actions outside the admin panel scope.',
        '- Do not share, expose, or discuss API keys, tokens, or credentials.',
        '- Do not hallucinate data; if information is unavailable, say so.',
        '- Do not execute multiple independent actions in one turn without user confirmation.'
    ];

    return sections.join('\n\n');
}

function buildConversationMessages(extraMessages = []) {
    return [
        { role: 'system', content: buildSystemPrompt() },
        ...chatHistory.map((entry) => ({ role: entry.role, content: entry.text })),
        ...extraMessages
    ];
}

function getPuterGuiOrigin() {
    const rawOrigin = typeof puter?.defaultGUIOrigin === 'string' && puter.defaultGUIOrigin
        ? puter.defaultGUIOrigin
        : 'https://puter.com';

    return rawOrigin.replace(/\/+$/, '');
}

function buildPuterSignInUrl(options = {}) {
    const {
        msgId,
        attemptTempUserCreation = true
    } = options;
    const params = new URLSearchParams({
        embedded_in_popup: 'true',
        msg_id: String(msgId)
    });

    if (attemptTempUserCreation) {
        params.set('attempt_temp_user_creation', 'true');
    }

    if (window.crossOriginIsolated) {
        params.set('cross_origin_isolated', 'true');
    }

    return `${getPuterGuiOrigin()}/action/sign-in?${params.toString()}`;
}

function getCenteredPopupFeatures() {
    const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
    const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
    const viewportWidth = window.outerWidth || document.documentElement.clientWidth || screen.width;
    const viewportHeight = window.outerHeight || document.documentElement.clientHeight || screen.height;
    const left = Math.max(0, Math.round(dualScreenLeft + ((viewportWidth - PUTER_POPUP_WIDTH) / 2)));
    const top = Math.max(0, Math.round(dualScreenTop + ((viewportHeight - PUTER_POPUP_HEIGHT) / 2)));

    return [
        'toolbar=no',
        'location=no',
        'directories=no',
        'status=no',
        'menubar=no',
        'scrollbars=yes',
        'resizable=yes',
        'copyhistory=no',
        `width=${PUTER_POPUP_WIDTH}`,
        `height=${PUTER_POPUP_HEIGHT}`,
        `top=${top}`,
        `left=${left}`
    ].join(', ');
}

function buildError(message) {
    return new Error(message || t('error_generic'));
}

function normalizePuterAuthError(error) {
    const errorCode = String(error?.error || error?.code || '').trim().toLowerCase();
    const errorMessage = String(error?.msg || error?.message || '').trim();

    if (errorCode === 'auth_window_closed') {
        return buildError(t('error_sign_in_popup_closed'));
    }

    if (errorCode === 'popup_blocked') {
        return buildError(t('error_sign_in_popup_blocked'));
    }

    if (errorCode === 'auth_timeout') {
        return buildError(t('error_sign_in_timeout'));
    }

    if (errorMessage) {
        return buildError(errorMessage);
    }

    return buildError(t('error_sign_in_required'));
}

function isPromiseLike(value) {
    return !!value && typeof value.then === 'function';
}

function openPuterSignInPopup(options = {}) {
    if (pendingPuterSignIn) {
        return pendingPuterSignIn;
    }

    const msgId = puterAuthMessageId++;
    const popupUrl = buildPuterSignInUrl({
        msgId,
        attemptTempUserCreation: options.attemptTempUserCreation !== false
    });
    const popup = window.open(popupUrl, 'Puter', getCenteredPopupFeatures());

    if (!popup) {
        return Promise.reject(normalizePuterAuthError({ code: 'popup_blocked' }));
    }

    popup.focus?.();

    pendingPuterSignIn = new Promise((resolve, reject) => {
        const expectedOrigin = getPuterGuiOrigin();
        let settled = false;
        let closedIntervalId = 0;
        let timeoutId = 0;

        const cleanup = () => {
            if (closedIntervalId) {
                window.clearInterval(closedIntervalId);
            }
            if (timeoutId) {
                window.clearTimeout(timeoutId);
            }

            window.removeEventListener('message', handleMessage);
            pendingPuterSignIn = null;
        };

        const finalize = (callback, value) => {
            if (settled) {
                return;
            }

            settled = true;
            cleanup();
            callback(value);
        };

        const handleMessage = (event) => {
            if (event.origin !== expectedOrigin) {
                return;
            }

            const payload = event.data;
            if (!payload || typeof payload !== 'object' || Number(payload.msg_id) !== msgId) {
                return;
            }

            if (payload.success && payload.token) {
                puter.setAuthToken?.(payload.token);
                finalize(resolve, payload);
                return;
            }

            finalize(reject, normalizePuterAuthError(payload));
        };

        window.addEventListener('message', handleMessage);

        closedIntervalId = window.setInterval(() => {
            if (!popup.closed) {
                return;
            }

            finalize(reject, normalizePuterAuthError({ code: 'auth_window_closed' }));
        }, 250);

        timeoutId = window.setTimeout(() => {
            finalize(reject, normalizePuterAuthError({ code: 'auth_timeout' }));
        }, PUTER_SIGN_IN_TIMEOUT_MS);
    });

    return pendingPuterSignIn;
}

async function ensurePuterReady(options = {}) {
    const { interactive = false } = options;

    if (!puter?.ai?.chat) {
        throw new Error(t('error_puter_missing'));
    }

    const auth = puter.auth;
    if (!auth) {
        puterReady = true;
        setStatus('status_ready');
        return;
    }

    let signedIn = true;
    if (typeof auth.isSignedIn === 'function') {
        const signInState = auth.isSignedIn();
        signedIn = isPromiseLike(signInState) ? await signInState : Boolean(signInState);
    }

    if (!signedIn && interactive) {
        setStatus('status_connecting');

        if (typeof window !== 'undefined' && typeof window.open === 'function') {
            await openPuterSignInPopup({ attemptTempUserCreation: true });
        } else if (typeof auth.signIn === 'function') {
            await auth.signIn({ attempt_temp_user_creation: true });
        } else {
            throw new Error(t('error_sign_in_required'));
        }

        if (typeof auth.isSignedIn === 'function') {
            const nextSignInState = auth.isSignedIn();
            signedIn = isPromiseLike(nextSignInState) ? await nextSignInState : Boolean(nextSignInState);
        } else {
            signedIn = true;
        }
    }

    if (!signedIn) {
        throw new Error(t('error_sign_in_required'));
    }

    puterReady = true;
    setStatus('status_ready');
}

function extractResponseText(response) {
    if (!response) return '';
    if (typeof response === 'string') return response;
    if (typeof response.text === 'string') return response.text;

    const content = response.message?.content;
    if (typeof content === 'string') return content;
    if (Array.isArray(content)) {
        return content
            .map((part) => {
                if (typeof part === 'string') return part;
                if (typeof part?.text === 'string') return part.text;
                if (typeof part?.content === 'string') return part.content;
                return '';
            })
            .filter(Boolean)
            .join('\n')
            .trim();
    }

    return '';
}

function extractToolCalls(response) {
    if (Array.isArray(response?.message?.tool_calls)) {
        return response.message.tool_calls;
    }
    return [];
}

function parseToolCall(call) {
    const name = call?.function?.name || call?.name || '';
    const rawArguments = call?.function?.arguments || call?.arguments || '{}';

    let args = {};
    if (typeof rawArguments === 'string') {
        try {
            args = JSON.parse(rawArguments);
        } catch (err) {
            args = {};
        }
    } else if (rawArguments && typeof rawArguments === 'object') {
        args = rawArguments;
    }

    return {
        id: call?.id || `tool_${Date.now()}`,
        name,
        args
    };
}

function actionLabel(action) {
    return String(action || '').replace(/_/g, ' ').trim();
}

function buildRedirectUrl(action, params = {}) {
    let base = '';
    const recordId = params?.id ? String(params.id).trim() : '';

    switch (action) {
        case 'create_mobile': base = '/admin/mobiles/insert'; break;
        case 'edit_mobile': base = recordId ? `/admin/mobiles/update/${encodeURIComponent(recordId)}` : ''; break;
        case 'create_post': base = '/admin/posts/create'; break;
        case 'edit_post': base = '/admin/posts/edit'; break;
        case 'create_page': base = '/admin/pages/create'; break;
        case 'edit_page': base = '/admin/pages/edit'; break;
        case 'create_service': base = '/admin/services/create'; break;
        case 'edit_service': base = recordId ? `/admin/services/${encodeURIComponent(recordId)}/edit` : ''; break;
        default: return '';
    }

    if (!base) {
        return '';
    }

    const query = new URLSearchParams();
    query.set('assistant_prefill', '1');

    if ((action === 'edit_post' || action === 'edit_page') && recordId) {
        query.set('id', recordId);
    }

    Object.entries(params || {}).forEach(([key, value]) => {
        if (key === 'id' || value === undefined || value === null || value === '') return;

        if (Array.isArray(value)) {
            value
                .filter((item) => item !== undefined && item !== null && item !== '')
                .forEach((item) => query.append(`${key}[]`, String(item)));
            return;
        }

        query.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
    });

    const suffix = query.toString();
    if (!suffix) return base;
    return `${base}${base.includes('?') ? '&' : '?'}${suffix}`;
}

async function hasPermission(permission) {
    if (!permission) return true;
    if (window.IS_SUPER_ADMIN) return true;

    if (Array.isArray(window.ADMIN_PERMISSIONS) && window.ADMIN_PERMISSIONS.includes(permission)) {
        return true;
    }

    let userId = window.AUTH_USER_ID || null;
    if (!userId) {
        const metaUser = document.querySelector('meta[name="user-id"]');
        userId = metaUser?.getAttribute('content') || null;
    }

    if (!userId) return false;

    try {
        const response = await fetch(`/api/rbac/check-permission/${userId}/${encodeURIComponent(permission)}`);
        if (!response.ok) return false;
        const json = await response.json();
        return json.has_permission === true;
    } catch (err) {
        console.error('[AdminAssistant] permission check failed', err);
        return false;
    }
}

async function parseResponsePayload(response) {
    const text = await response.text();

    try {
        return { ok: response.ok, payload: JSON.parse(text) };
    } catch (err) {
        if (response.ok) {
            return { ok: true, payload: { success: true, data: text } };
        }

        throw new Error(`Unexpected response (status ${response.status}): ${text}`);
    }
}

async function executeAction(action, params = {}) {
    const requiredPermission = ACTION_PERMISSIONS[action];
    if (requiredPermission && !(await hasPermission(requiredPermission))) {
        setStatus('status_permission_denied');
        return { kind: 'message', text: t('permission_denied') };
    }

    if (action === 'start_new_chat') {
        await resetChat({ renderNotice: false });
        setStatus('status_new_chat_started');
        return { kind: 'message', text: t('notice_new_chat') };
    }

    if (REDIRECT_ACTIONS.has(action)) {
        const url = buildRedirectUrl(action, params);
        if (!url) {
            setStatus('status_action_failed');
            return { kind: 'message', text: `${t('action_failed')}: ${actionLabel(action)}` };
        }

        setStatus('status_navigating');
        return {
            kind: 'redirect',
            url,
            text: t('opening_page')
        };
    }

    if (LINK_ACTIONS[action]) {
        const info = LINK_ACTIONS[action];
        setStatus('status_link_provided');
        return {
            kind: 'message',
            text: `${t('link_intro')} ${info.url}`
        };
    }

    let url = '';
    let method = 'POST';
    const formData = new FormData();
    if (CSRF_TOKEN) {
        formData.append('csrf_token', CSRF_TOKEN);
    }

    switch (action) {
        case 'delete_post': url = '/admin/posts/delete'; break;
        case 'create_category': url = '/admin/categories/create'; break;
        case 'edit_category': url = '/admin/categories/update'; break;
        case 'delete_category': url = '/admin/categories/delete'; break;
        case 'create_tag': url = '/admin/tags/create'; break;
        case 'edit_tag': url = '/admin/tags/update'; break;
        case 'delete_tag': url = '/admin/tags/delete'; break;
        case 'delete_mobile': url = '/admin/mobiles/delete'; break;
        case 'delete_service': url = '/admin/services/delete'; break;
        case 'delete_page': url = '/admin/pages/delete'; break;
        case 'send_notification': url = '/api/resend-notification'; break;
        case 'create_user': url = '/api/admin/users/create'; break;
        case 'delete_user': url = '/api/admin/users/delete'; break;
        case 'create_role': url = '/api/admin/roles/create'; break;
        case 'delete_role': url = '/api/admin/roles/delete'; break;
        case 'get_analytics_summary': url = '/api/admin/analytics/summary'; method = 'GET'; break;
        case 'get_visitor_stats':
            url = `/api/admin/analytics/visitors?start_date=${encodeURIComponent(params.start_date || '')}&end_date=${encodeURIComponent(params.end_date || '')}`;
            method = 'GET';
            break;
        case 'get_top_content':
            url = params.type === 'post' ? '/api/admin/analytics/post-views' : '/api/admin/analytics/page-views';
            url += `?limit=${encodeURIComponent(params.limit || 10)}`;
            method = 'GET';
            break;
        default:
            setStatus('status_action_failed');
            return { kind: 'message', text: `${t('action_failed')}: ${actionLabel(action)}` };
    }

    if ((action === 'create_mobile' || action === 'edit_mobile') && typeof params.status === 'string') {
        if (params.status === 'active') params.status = 'official';
        if (params.status === 'inactive') params.status = 'unofficial';
    }

    const fetchOptions = { method };

    if (method === 'POST') {
        if (action === 'send_notification') {
            fetchOptions.body = JSON.stringify(params);
            fetchOptions.headers = { 'Content-Type': 'application/json' };
        } else {
            Object.entries(params || {}).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach((item) => formData.append(`${key}[]`, item));
                } else if (value !== undefined && value !== null) {
                    formData.append(key, value);
                }
            });
            fetchOptions.body = formData;
        }
    }

    try {
        const response = await fetch(url, fetchOptions);
        const { ok, payload } = await parseResponsePayload(response);

        if (!(ok || payload.success || payload.status === 200 || payload.status === 201)) {
            throw new Error(payload.error || payload.message || 'Unknown error');
        }

        if (method === 'GET') {
            setStatus('status_analysis_complete');
            return {
                kind: 'tool_result',
                payload: {
                    success: true,
                    action,
                    message: t('analytics_loaded'),
                    data: payload.data ?? payload
                }
            };
        }

        setStatus('status_task_completed');
        return {
            kind: 'tool_result',
            payload: {
                success: true,
                action,
                message: payload.message || t('action_success'),
                data: payload.data ?? null
            }
        };
    } catch (err) {
        console.error(`[AdminAssistant] ${action} failed`, err);
        setStatus('status_action_failed');
        return {
            kind: 'message',
            text: `${t('action_failed')}: ${err.message || actionLabel(action)}`
        };
    }
}

function applyValueToField(field, values) {
    if (!field || !values.length) return;

    const tagName = String(field.tagName || '').toLowerCase();
    const type = String(field.type || '').toLowerCase();

    if (type === 'checkbox') {
        if (values.length > 1 || field.name.endsWith('[]')) {
            field.checked = values.includes(field.value);
        } else {
            const value = values[0];
            field.checked = value === '1' || value === 'true' || value === 'yes' || value === field.value;
        }
    } else if (type === 'radio') {
        field.checked = values.includes(field.value);
    } else if (tagName === 'select' && field.multiple) {
        Array.from(field.options).forEach((option) => {
            option.selected = values.includes(option.value);
        });
    } else {
        field.value = values[0];
    }

    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

function applyAssistantPrefillFromQuery() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('assistant_prefill') !== '1') {
        return;
    }

    const groupedValues = new Map();
    params.forEach((value, key) => {
        if (key === 'assistant_prefill' || key === 'id') return;
        const normalizedKey = key.endsWith('[]') ? key.slice(0, -2) : key;
        const bucket = groupedValues.get(normalizedKey) || [];
        bucket.push(value);
        groupedValues.set(normalizedKey, bucket);
    });

    if (!groupedValues.size) {
        return;
    }

    const fields = Array.from(document.querySelectorAll('input, textarea, select'));
    groupedValues.forEach((values, key) => {
        const matchingFields = fields.filter((field) => field.name === key || field.name === `${key}[]` || field.id === key);
        matchingFields.forEach((field) => applyValueToField(field, values));
    });
}

function getChatTools() {
    return [
        { type: 'web_search' },
        ADMIN_ACTION_TOOL
    ];
}

function shouldRetryWithoutModel(error) {
    const message = String(error?.message || error?.error || '').toLowerCase();
    return message.includes('no fallback model available')
        || message.includes('model_not_found')
        || message.includes('model not found')
        || message.includes('unknown model')
        || message.includes('unsupported model');
}

function getModelId(model) {
    if (!model) return '';

    const candidates = [
        model.id,
        model.model,
        model.model_id,
        model.modelId,
        model.name
    ];

    for (const value of candidates) {
        if (typeof value === 'string' && value.trim()) {
            return value.trim();
        }
    }

    return '';
}

function isFailedChatResponse(response) {
    return !!response
        && typeof response === 'object'
        && response.success === false
        && typeof response.error === 'string'
        && response.error.trim() !== '';
}

async function discoverChatModel(forceRefresh = false) {
    if (!puter?.ai?.listModels) {
        return CHAT_MODEL;
    }

    if (!forceRefresh && resolvedChatModel) {
        return resolvedChatModel;
    }

    if (!forceRefresh && chatModelDiscoveryPromise) {
        return chatModelDiscoveryPromise;
    }

    chatModelDiscoveryPromise = (async () => {
        try {
            const models = await puter.ai.listModels();
            const availableModelIds = Array.isArray(models)
                ? models.map(getModelId).filter(Boolean)
                : [];

            if (!availableModelIds.length) {
                return CHAT_MODEL;
            }

            if (CHAT_MODEL) {
                const exactMatch = availableModelIds.find((id) => id.toLowerCase() === CHAT_MODEL.toLowerCase());
                if (exactMatch) {
                    resolvedChatModel = exactMatch;
                    return resolvedChatModel;
                }
            }

            for (const preferredModel of CHAT_MODEL_PREFERENCES) {
                const match = availableModelIds.find((id) => id.toLowerCase() === preferredModel.toLowerCase());
                if (match) {
                    resolvedChatModel = match;
                    return resolvedChatModel;
                }
            }

            resolvedChatModel = availableModelIds[0];
            return resolvedChatModel;
        } catch (err) {
            console.warn('[AdminAssistant] model discovery failed', err);
            return CHAT_MODEL;
        } finally {
            chatModelDiscoveryPromise = null;
        }
    })();

    return chatModelDiscoveryPromise;
}

function buildChatOptions(options = {}) {
    const { includeTools = true, model = CHAT_MODEL } = options;
    const requestOptions = {};

    if (model) {
        requestOptions.model = model;
    }

    if (includeTools) {
        requestOptions.tools = getChatTools();
    }

    return requestOptions;
}

async function chatWithModelFallback(messages, options = {}) {
    const { includeTools = true } = options;
    const preferredModel = await discoverChatModel(false);

    try {
        const response = await puter.ai.chat(messages, buildChatOptions({
            includeTools,
            model: preferredModel
        }));

        if (isFailedChatResponse(response)) {
            throw response;
        }

        return response;
    } catch (err) {
        if (!shouldRetryWithoutModel(err)) {
            throw err;
        }

        const rediscoveredModel = await discoverChatModel(true);
        const retryModels = [];

        if (rediscoveredModel && rediscoveredModel !== preferredModel) {
            retryModels.push(rediscoveredModel);
        }

        if (CHAT_MODEL && CHAT_MODEL !== preferredModel && CHAT_MODEL !== rediscoveredModel) {
            retryModels.push(CHAT_MODEL);
        }

        if (!retryModels.includes('')) {
            retryModels.push('');
        }

        for (const retryModel of retryModels) {
            try {
                console.warn('[AdminAssistant] retrying chat with alternate Puter model', {
                    previousModel: preferredModel || '(backend-default)',
                    retryModel: retryModel || '(backend-default)'
                });

                const retryResponse = await puter.ai.chat(messages, buildChatOptions({
                    includeTools,
                    model: retryModel
                }));

                if (isFailedChatResponse(retryResponse)) {
                    throw retryResponse;
                }

                resolvedChatModel = retryModel || resolvedChatModel;
                return retryResponse;
            } catch (retryErr) {
                if (!shouldRetryWithoutModel(retryErr)) {
                    throw retryErr;
                }
            }
        }

        throw err;
    }
}

async function requestAssistant(messages) {
    return chatWithModelFallback(messages, { includeTools: true });
}

async function runAssistantTurn(userText) {
    let messages = buildConversationMessages([{ role: 'user', content: userText }]);

    for (let round = 0; round < MAX_TOOL_ROUNDS; round += 1) {
        const response = await requestAssistant(messages);
        const toolCalls = extractToolCalls(response);

        if (!toolCalls.length) {
            return {
                kind: 'assistant',
                text: extractResponseText(response) || t('error_generic')
            };
        }

        const parsedCall = parseToolCall(toolCalls[0]);
        if (parsedCall.name !== 'admin_action') {
            return { kind: 'message', text: t('notice_unknown_tool') };
        }

        const action = String(parsedCall.args.action || '').trim();
        const params = parsedCall.args.params && typeof parsedCall.args.params === 'object' ? parsedCall.args.params : {};
        const result = await executeAction(action, params);

        if (result.kind === 'redirect' || result.kind === 'message') {
            return result;
        }

        messages = [
            ...messages,
            {
                role: 'assistant',
                tool_calls: response.message.tool_calls
            },
            {
                role: 'tool',
                tool_call_id: parsedCall.id,
                content: JSON.stringify(result.payload)
            }
        ];
    }

    return { kind: 'message', text: t('error_generic') };
}

async function resetChat(options = {}) {
    const { renderNotice = true } = options;

    chatHistory = [];
    saveChatHistory();
    renderChatHistory();

    if (renderNotice) {
        appendMessage('assistant', t('notice_new_chat'), { persist: false });
    }
}

window.adminAssistantRewrite = async function adminAssistantRewrite(html) {
    try {
        await ensurePuterReady({ interactive: true });
        const response = await chatWithModelFallback([
            {
                role: 'system',
                content: 'Rewrite the provided HTML for clarity and polish while preserving structure and intent. Return only HTML.'
            },
            {
                role: 'user',
                content: String(html || '')
            }
        ], { includeTools: false });

        return extractResponseText(response) || html;
    } catch (err) {
        console.error('[AdminAssistant] rewrite failed', err);
        return html;
    }
};

async function handleUserMessage() {
    const text = UI.input?.value.trim();
    if (!text) return;

    try {
        const tsRaw = sessionStorage.getItem(LAST_ACTIVITY_KEY);
        const last = tsRaw ? parseInt(tsRaw, 10) : 0;
        if (last && Date.now() - last > INACTIVITY_LIMIT_MS) {
            chatHistory = [];
            saveChatHistory();
            renderChatHistory();
            appendMessage('assistant', t('notice_chat_reset'), { persist: false });
        }
    } catch (err) {
        console.warn('[AdminAssistant] inactivity check failed', err);
    }

    if (UI.input) UI.input.value = '';

    appendMessage('user', text);
    const staticReply = getStaticAssistantReply(text);
    if (staticReply) {
        setStatus('status_ready');
        setTyping(true);
        await appendAssistantMessage(staticReply, { animate: true });
        setTyping(false);
        updateLastActivity();
        return;
    }

    setStatus('status_thinking');
    setTyping(true);
    const requestStartedAt = performance.now();

    try {
        await ensurePuterReady({ interactive: true });
        const result = await runAssistantTurn(text);
        const responseMs = Math.max(0, Math.round(performance.now() - requestStartedAt));

        if (result.kind === 'redirect') {
            await appendAssistantMessage(result.text, { responseMs, animate: true });
            window.setTimeout(() => {
                window.location.href = result.url;
            }, 350);
        } else {
            await appendAssistantMessage(result.text, {
                responseMs,
                trustedHtml: result.trustedHtml === true,
                animate: true
            });
            if (result.kind === 'assistant') {
                setStatus('status_ready');
            }
        }
    } catch (err) {
        console.error('[AdminAssistant] chat failed', err);
        
        // Check if it's an auth error - use static reply instead of showing error
        const isAuthError = err?.message?.includes('sign in') || 
                           err?.message?.includes('auth') ||
                           err?.message?.includes('required') ||
                           err?.message?.includes('401') ||
                           err?.message?.includes('token');
        
        if (isAuthError) {
            // Try to get a static reply for the message
            const staticReply = getStaticAssistantReply(text);
            if (staticReply) {
                await appendAssistantMessage(staticReply, { animate: true });
                setStatus('status_ready');
            } else {
                await appendAssistantMessage(t('error_sign_in_required'), { animate: true });
                setStatus('status_ready');
            }
        } else {
            await appendAssistantMessage(err.message || t('error_generic'), { animate: true });
            setStatus('status_error');
        }
    } finally {
        setTyping(false);
        updateLastActivity();
    }
}

function initQuickActionBar() {
    const inputGroup = UI.chat?.querySelector('.input-group');
    if (!inputGroup || inputGroup.querySelector('.assistant-action-strip')) {
        return;
    }

    const actionStrip = document.createElement('div');
    actionStrip.className = 'assistant-action-strip';

    const buttonIds = ['btnNewChat', 'btnViewUploads'];
    buttonIds.forEach((buttonId) => {
        const button = document.getElementById(buttonId);
        if (!button) return;
        button.classList.add('assistant-action-btn');
        button.classList.remove('btn-sm', 'btn-light');
        actionStrip.appendChild(button);
    });

    const manageChatsButton = document.getElementById('btnManageChats');
    if (manageChatsButton) {
        manageChatsButton.remove();
    }

    inputGroup.prepend(actionStrip);
}

function openChat() {
    UI.chat?.classList.remove('hidden');
    UI.chat?.classList.remove('d-none');
    UI.input?.focus();

    if (!puterReady) {
        setStatus('status_ready_to_connect');
    }
}

function closeChat() {
    UI.chat?.classList.add('hidden');
    UI.chat?.classList.add('d-none');
}

function initAssistant() {
    loadLanguage();
    chatHistory = loadChatHistory();
    applyLanguage();
    initQuickActionBar();
    applyAssistantPrefillFromQuery();
    renderChatHistory();

    if (historyExpired) {
        appendMessage('assistant', t('notice_session_expired'), { persist: false });
    }

    if (puter?.ai?.chat) {
        setStatus('status_ready_to_connect');
    } else {
        setStatus('status_initialization_failed');
    }

    updateLastActivity();
}

UI.sendBtn?.addEventListener('click', handleUserMessage);
UI.input?.addEventListener('keypress', (event) => {
    if (event.key === 'Enter') {
        handleUserMessage();
    }
});
UI.langBnBtn?.addEventListener('click', () => setLanguage('bn'));
UI.langEnBtn?.addEventListener('click', () => setLanguage('en'));
UI.toggleBtn?.addEventListener('click', openChat);
UI.closeBtn?.addEventListener('click', closeChat);

const btnNewChat = document.getElementById('btnNewChat');
const btnViewUploads = document.getElementById('btnViewUploads');
const btnManageChats = document.getElementById('btnManageChats');

btnNewChat?.addEventListener('click', async () => {
    await resetChat();
    setStatus('status_new_chat_started');
});

btnViewUploads?.addEventListener('click', () => {
    window.location.href = '/admin/media/upload';
});

initAssistant();
UI.langBnBtn?.addEventListener('click', () => setLanguage('bn'));
UI.langEnBtn?.addEventListener('click', () => setLanguage('en'));
UI.toggleBtn?.addEventListener('click', openChat);
UI.closeBtn?.addEventListener('click', closeChat);

const btnNewChat = document.getElementById('btnNewChat');
const btnViewUploads = document.getElementById('btnViewUploads');
const btnManageChats = document.getElementById('btnManageChats');

btnNewChat?.addEventListener('click', async () => {
    await resetChat();
    setStatus('status_new_chat_started');
});

btnViewUploads?.addEventListener('click', () => {
    window.location.href = '/admin/media/upload';
});

initAssistant();

