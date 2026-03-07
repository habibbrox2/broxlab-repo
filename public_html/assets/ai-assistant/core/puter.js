const DEFAULT_POPUP = { width: 600, height: 700, timeoutMs: 2 * 60 * 1000 };

let puterInstance = null;

async function getPuter() {
  if (window.puter) return window.puter;
  if (!puterInstance) {
    const module = await import('@heyputer/puter.js');
    puterInstance = module.puter;
  }
  return puterInstance;
}

// For synchronous access, but will be null until loaded
let puter = null;

export async function getPuterClient() {
  return getPuter();
}

function getCenteredPopupFeatures({ width, height }) {
  const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
  const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
  const viewportWidth = window.outerWidth || document.documentElement.clientWidth || screen.width;
  const viewportHeight = window.outerHeight || document.documentElement.clientHeight || screen.height;
  const left = Math.max(0, Math.round(dualScreenLeft + ((viewportWidth - width) / 2)));
  const top = Math.max(0, Math.round(dualScreenTop + ((viewportHeight - height) / 2)));

  return [
    'toolbar=no',
    'location=no',
    'directories=no',
    'status=no',
    'menubar=no',
    'scrollbars=yes',
    'resizable=yes',
    `width=${width}`,
    `height=${height}`,
    `top=${top}`,
    `left=${left}`
  ].join(', ');
}

function buildError(message) {
  return new Error(message || 'Authentication required');
}

function isTrustedPuterOrigin(origin, expectedOrigin) {
  if (typeof origin !== 'string' || !origin) return false;

  try {
    const current = new URL(origin);
    const expected = new URL(expectedOrigin);

    if (current.origin === expected.origin) return true;
    if (current.protocol !== 'https:') return false;

    return current.hostname === 'puter.com' || current.hostname.endsWith('.puter.com');
  } catch {
    return false;
  }
}

function normalizeAuthError(error, t) {
  const code = String(error?.error || error?.code || '').trim().toLowerCase();
  const message = String(error?.msg || error?.message || '').trim();
  if (code === 'popup_blocked') return buildError(t?.('error_sign_in_popup_blocked') || 'Popup blocked');
  if (code === 'auth_window_closed') return buildError(t?.('error_sign_in_popup_closed') || 'Popup closed early');
  if (code === 'auth_timeout') return buildError(t?.('error_sign_in_timeout') || 'Sign-in timed out');
  if (message) return buildError(message);
  return buildError(t?.('error_sign_in_required') || 'Sign-in required');
}

export async function getPuterGuiOrigin() {
  const p = await getPuter();
  const origin = typeof p?.defaultGUIOrigin === 'string' && p.defaultGUIOrigin
    ? p.defaultGUIOrigin
    : 'https://puter.com';
  return origin.replace(/\/+$/, '');
}

async function buildPuterSignInUrl(msgId) {
  const origin = await getPuterGuiOrigin();
  const params = new URLSearchParams({
    embedded_in_popup: 'true',
    msg_id: String(msgId),
    attempt_temp_user_creation: 'true'
  });
  if (window.crossOriginIsolated) {
    params.set('cross_origin_isolated', 'true');
  }
  return `${origin}/action/sign-in?${params.toString()}`;
}

export function buildPopupSignIn({ popupSize = DEFAULT_POPUP, t } = {}) {
  let authMessageId = 1;
  let pending = null;

  return async function openPopup() {
    if (pending) return pending;
    const msgId = authMessageId++;
    const origin = await getPuterGuiOrigin();
    const popup = window.open(await buildPuterSignInUrl(msgId), 'Puter', getCenteredPopupFeatures(popupSize));
    if (!popup) {
      return Promise.reject(normalizeAuthError({ code: 'popup_blocked' }, t));
    }
    popup.focus?.();

    pending = new Promise((resolve, reject) => {
      const expectedOrigin = origin;
      let settled = false;
      let closedIntervalId = 0;
      let timeoutId = 0;

      const cleanup = () => {
        if (closedIntervalId) window.clearInterval(closedIntervalId);
        if (timeoutId) window.clearTimeout(timeoutId);
        window.removeEventListener('message', handleMessage);
        pending = null;
      };

      const finalize = (cb, value) => {
        if (settled) return;
        settled = true;
        cleanup();
        try { popup.close(); } catch {}
        cb(value);
      };

      const handleMessage = async (event) => {
        if (event.source !== popup) return;
        if (!isTrustedPuterOrigin(event.origin, expectedOrigin)) return;
        const payload = event.data;
        if (!payload || typeof payload !== 'object' || Number(payload.msg_id) !== msgId) return;

        if (payload.success) {
          const p = await getPuter();
          if (payload.token) {
            p.setAuthToken?.(payload.token);
          }
          finalize(resolve, payload);
          return;
        }

        finalize(reject, normalizeAuthError(payload, t));
      };

      window.addEventListener('message', handleMessage);

      closedIntervalId = window.setInterval(() => {
        if (!popup.closed) return;
        finalize(reject, normalizeAuthError({ code: 'auth_window_closed' }, t));
      }, 250);

      timeoutId = window.setTimeout(() => {
        finalize(reject, normalizeAuthError({ code: 'auth_timeout' }, t));
      }, popupSize.timeoutMs ?? DEFAULT_POPUP.timeoutMs);
    });

    return pending;
  };
}

export async function ensurePuterReady({ interactive = false, t, openPopup }) {
  const p = await getPuter();
  if (!p?.ai?.chat) throw buildError(t?.('error_puter_missing') || 'Puter client missing');
  const auth = p.auth;
  if (!auth) return;
  let signedIn = true;
  if (typeof auth.isSignedIn === 'function') {
    const state = auth.isSignedIn();
    signedIn = state && typeof state.then === 'function' ? await state : Boolean(state);
  }
  if (!signedIn && interactive) {
    if (openPopup) {
      await openPopup();
    } else if (typeof auth.signIn === 'function') {
      await auth.signIn({ attempt_temp_user_creation: true });
    }
    if (typeof auth.isSignedIn === 'function') {
      const next = auth.isSignedIn();
      signedIn = next && typeof next.then === 'function' ? await next : Boolean(next);
    } else {
      signedIn = true;
    }
  }
  if (!signedIn) throw normalizeAuthError({ code: 'auth_required' }, t);
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
  const candidates = [model.id, model.model, model.model_id, model.modelId, model.name];
  return candidates.find((v) => typeof v === 'string' && v.trim())?.trim() || '';
}

function isFailedChatResponse(response) {
  return !!response
    && typeof response === 'object'
    && response.success === false
    && typeof response.error === 'string'
    && response.error.trim() !== '';
}

export function buildChatClient({
  chatModel,
  modelPreferences = [],
  listModels = async () => (await getPuter()).ai.listModels(),
  chat = async (messages, options) => (await getPuter()).ai.chat(messages, options)
} = {}) {
  let resolvedModel = chatModel || '';
  let discoveryPromise = null;

  const discoverModel = async (forceRefresh = false) => {
    const p = await getPuter();
    if (!p?.ai?.listModels) return chatModel;
    if (!forceRefresh && resolvedModel) return resolvedModel;
    if (!forceRefresh && discoveryPromise) return discoveryPromise;

    discoveryPromise = (async () => {
      try {
        const models = await listModels();
        const ids = Array.isArray(models) ? models.map(getModelId).filter(Boolean) : [];
        if (!ids.length) return chatModel;
        if (chatModel) {
          const exact = ids.find((id) => id.toLowerCase() === chatModel.toLowerCase());
          if (exact) {
            resolvedModel = exact;
            return resolvedModel;
          }
        }
        for (const pref of modelPreferences) {
          const match = ids.find((id) => id.toLowerCase() === pref.toLowerCase());
          if (match) {
            resolvedModel = match;
            return resolvedModel;
          }
        }
        resolvedModel = ids[0];
        return resolvedModel;
      } finally {
        discoveryPromise = null;
      }
    })();
    return discoveryPromise;
  };

  const chatWithFallback = async (
    messages,
    {
      includeTools = true,
      tools = [],
      temperature,
      maxTokens,
      reasoningEffort,
      textVerbosity,
      stream = false
    } = {}
  ) => {
    const preferredModel = await discoverModel(false);
    const buildOptions = (model) => {
      const opts = {};
      if (model) opts.model = model;
      if (includeTools && tools?.length) opts.tools = tools;
      if (typeof temperature === 'number') opts.temperature = temperature;
      if (typeof maxTokens === 'number') opts.max_tokens = maxTokens;
      if (typeof reasoningEffort === 'string' && reasoningEffort.trim()) {
        opts.reasoning_effort = reasoningEffort.trim();
      }
      if (typeof textVerbosity === 'string' && textVerbosity.trim()) {
        opts.text_verbosity = textVerbosity.trim();
        opts.verbosity = textVerbosity.trim();
      }
      if (stream) opts.stream = true;
      return opts;
    };

    const attempt = async (model) => {
      const response = await chat(messages, buildOptions(model));
      if (isFailedChatResponse(response)) throw response;
      return response;
    };

    try {
      return await attempt(preferredModel);
    } catch (err) {
      if (!shouldRetryWithoutModel(err)) throw err;
      const rediscovered = await discoverModel(true);
      const queue = [];
      if (rediscovered && rediscovered !== preferredModel) queue.push(rediscovered);
      if (chatModel && chatModel !== preferredModel && chatModel !== rediscovered) queue.push(chatModel);
      queue.push('');
      for (const model of queue) {
        try {
          const resp = await attempt(model);
          if (model) resolvedModel = model;
          return resp;
        } catch (retryErr) {
          if (!shouldRetryWithoutModel(retryErr)) throw retryErr;
        }
      }
      throw err;
    }
  };

  return { chatWithFallback, discoverModel, getResolvedModel: () => resolvedModel };
}

export function extractResponseText(response) {
  if (!response) return '';
  if (typeof response === 'string') return response;
  if (typeof response.text === 'string') return response.text;
  if (typeof response.message?.content === 'string') return response.message.content;
  if (Array.isArray(response.message?.content)) {
    return response.message.content
      .map((part) => (typeof part?.text === 'string' ? part.text : typeof part === 'string' ? part : ''))
      .filter(Boolean)
      .join('\n')
      .trim();
  }
  return '';
}

export async function generateImage(prompt, {
  model = 'gpt-image-1.5',
  aspectRatio = '1:1',
  negativePrompt = '',
  testMode = false
} = {}) {
  const p = await getPuter();
  const image = await p.ai.txt2img({
    prompt: String(prompt || '').trim(),
    model,
    aspect_ratio: aspectRatio,
    negative_prompt: negativePrompt,
    test_mode: testMode
  });
  return image;
}

export async function speakText(text, {
  provider = 'openai',
  model = 'gpt-4o-mini-tts',
  voice = 'alloy',
  language = 'en-US',
  instructions = '',
  testMode = false
} = {}) {
  const p = await getPuter();
  const audio = await p.ai.txt2speech(String(text || ''), {
    provider,
    model,
    voice,
    language,
    instructions,
    test_mode: testMode
  });
  return audio;
}
