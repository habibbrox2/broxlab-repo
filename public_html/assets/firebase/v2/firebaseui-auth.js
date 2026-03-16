const FIREBASE_COMPAT_APP_SRC = 'https://www.gstatic.com/firebasejs/10.12.5/firebase-app-compat.js';
const FIREBASE_COMPAT_AUTH_SRC = 'https://www.gstatic.com/firebasejs/10.12.5/firebase-auth-compat.js';
const FIREBASEUI_JS_SRC = 'https://www.gstatic.com/firebasejs/ui/6.1.0/firebase-ui-auth.js';
const FIREBASEUI_CSS_SRC = 'https://www.gstatic.com/firebasejs/ui/6.1.0/firebase-ui-auth.css';

let resourcesPromise = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[data-firebaseui-src="${src}"]`);
        if (existing) {
            if (existing.dataset.loaded === '1') {
                resolve();
            } else {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
            }
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.defer = true;
        script.dataset.firebaseuiSrc = src;
        script.addEventListener('load', () => {
            script.dataset.loaded = '1';
            resolve();
        }, { once: true });
        script.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
        document.head.appendChild(script);
    });
}

function loadCss(href) {
    const existing = document.querySelector(`link[data-firebaseui-href="${href}"]`);
    if (existing) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.dataset.firebaseuiHref = href;
    document.head.appendChild(link);
}

async function ensureFirebaseUiResources() {
    if (!resourcesPromise) {
        resourcesPromise = (async () => {
            loadCss(FIREBASEUI_CSS_SRC);
            await loadScript(FIREBASE_COMPAT_APP_SRC);
            await loadScript(FIREBASE_COMPAT_AUTH_SRC);
            await loadScript(FIREBASEUI_JS_SRC);
        })();
    }
    return resourcesPromise;
}

async function fetchJson(url, options = {}) {
    const timeoutMs = Number(options.timeoutMs || 12000);
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal
        });
        const data = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data };
    } catch (error) {
        return { ok: false, status: 0, data: {}, error };
    } finally {
        clearTimeout(timer);
    }
}

function normalizeProvider(providerId) {
    const raw = String(providerId || '').toLowerCase();
    if (raw === 'google.com') return 'google';
    if (raw === 'facebook.com') return 'facebook';
    if (raw === 'github.com') return 'github';
    if (raw === 'google') return 'google';
    if (raw === 'facebook') return 'facebook';
    if (raw === 'github') return 'github';
    return '';
}

function buildSignInOptions(firebaseGlobal, providers) {
    const providerIds = {
        google: firebaseGlobal?.auth?.GoogleAuthProvider?.PROVIDER_ID,
        facebook: firebaseGlobal?.auth?.FacebookAuthProvider?.PROVIDER_ID,
        github: firebaseGlobal?.auth?.GithubAuthProvider?.PROVIDER_ID
    };

    return Object.keys(providers || {})
        .map((key) => String(key || '').toLowerCase())
        .map((key) => providerIds[key])
        .filter(Boolean);
}

function isPopupCancelError(error) {
    const code = String(error?.code || error?.errorCode || '').toLowerCase().replace(/^auth\//, '');
    return code === 'popup-closed-by-user' || code === 'popup_closed_by_user' || code === 'cancelled-popup-request' || code === 'cancelled_popup_request';
}

function renderInfoState(container, message) {
    container.innerHTML = `
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle-fill me-2"></i>${message}
        </div>
    `;
}

async function postFirebaseSignin(idToken, provider) {
    const { data } = await fetchJson('/api/firebase/signin', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ idToken, provider })
    });

    return data || {};
}

export async function initFirebaseUISignIn(options = {}) {
    const container = typeof options.container === 'string'
        ? document.querySelector(options.container)
        : options.container;

    if (!container) {
        return { initialized: false, reason: 'missing_container' };
    }

    const setStatus = typeof options.setStatus === 'function' ? options.setStatus : (() => { });
    const onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : (() => { });
    const onConflict = typeof options.onConflict === 'function' ? options.onConflict : (() => { });
    const onError = typeof options.onError === 'function' ? options.onError : (() => { });

    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <p class="text-muted small mt-2 mb-0">Loading sign-in options...</p>
        </div>
    `;

    try {
        await ensureFirebaseUiResources();
    } catch (error) {
        setStatus('OAuth providers could not be loaded right now.', 'warning');
        renderInfoState(container, 'OAuth sign-in is temporarily unavailable. Please use email and password.');
        return { initialized: false, reason: 'resource_load_failed', error };
    }

    const firebaseGlobal = window.firebase;
    const firebaseUiGlobal = window.firebaseui;
    if (!firebaseGlobal || !firebaseUiGlobal?.auth?.AuthUI) {
        setStatus('Firebase sign-in is currently unavailable.', 'warning');
        renderInfoState(container, 'Sign-in providers are unavailable. Please use email and password.');
        return { initialized: false, reason: 'firebaseui_not_available' };
    }

    try {
        const configResponse = await fetchJson('/api/firebase-config', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            timeoutMs: 10000
        });

        if (!configResponse.ok || configResponse.data?.success === false) {
            setStatus('Unable to load Firebase configuration.', 'warning');
            renderInfoState(container, 'OAuth sign-in is unavailable right now.');
            return { initialized: false, reason: 'config_fetch_failed' };
        }

        const config = configResponse.data?.config || {};
        if (!config.apiKey || !config.authDomain || !config.projectId) {
            setStatus('Incomplete Firebase configuration.', 'warning');
            renderInfoState(container, 'OAuth sign-in is not configured.');
            return { initialized: false, reason: 'config_invalid' };
        }

        if (!Array.isArray(firebaseGlobal.apps) || firebaseGlobal.apps.length === 0) {
            firebaseGlobal.initializeApp({
                apiKey: config.apiKey,
                authDomain: config.authDomain,
                projectId: config.projectId,
                appId: config.appId,
                messagingSenderId: config.messagingSenderId
            });
        }

        const providersResponse = await fetchJson('/api/oauth/providers', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            timeoutMs: 12000
        });

        if (!providersResponse.ok) {
            setStatus('OAuth providers are temporarily unavailable.', 'warning');
            renderInfoState(container, 'OAuth sign-in is temporarily unavailable. Please use email and password.');
            return { initialized: false, reason: 'providers_fetch_failed' };
        }

        const providerData = providersResponse.data?.providers || {};
        const signInOptions = buildSignInOptions(firebaseGlobal, providerData);

        if (!signInOptions.length) {
            renderInfoState(container, 'No OAuth providers are currently available.');
            return { initialized: false, reason: 'no_providers' };
        }

        const auth = firebaseGlobal.auth();
        const AuthUI = firebaseUiGlobal.auth.AuthUI;
        let ui = AuthUI.getInstance();
        if (!ui) {
            ui = new AuthUI(auth);
        } else {
            try {
                ui.reset();
            } catch (error) {
                // no-op; stale UI state should not block init
            }
        }

        const uiConfig = {
            signInFlow: 'popup',
            signInSuccessUrl: '/user/dashboard',
            signInOptions,
            callbacks: {
                signInSuccessWithAuthResult: (authResult) => {
                    (async () => {
                        try {
                            const idToken = await authResult?.user?.getIdToken(true);
                            if (!idToken) {
                                onError('Failed to verify your sign-in token.', { redirectToLogin: true });
                                return;
                            }

                            const provider = normalizeProvider(
                                authResult?.additionalUserInfo?.providerId ||
                                authResult?.credential?.providerId ||
                                authResult?.user?.providerData?.[0]?.providerId
                            );

                            if (!provider) {
                                onError('Unsupported authentication provider.', { redirectToLogin: true });
                                return;
                            }

                            const backendResult = await postFirebaseSignin(idToken, provider);

                            if (backendResult?.conflict) {
                                onConflict(backendResult.conflict, provider);
                                return;
                            }

                            if (!backendResult?.success) {
                                onError(backendResult?.error || 'Authentication failed.', { redirectToLogin: true });
                                return;
                            }

                            onSuccess(backendResult);
                        } catch (error) {
                            if (isPopupCancelError(error)) {
                                setStatus('Login request was cancelled.', 'warning');
                                return;
                            }
                            onError(error?.message || 'Authentication failed.', { redirectToLogin: true });
                        }
                    })();
                    return false;
                },
                signInFailure: (error) => {
                    if (isPopupCancelError(error)) {
                        setStatus('Login request was cancelled.', 'warning');
                        return Promise.resolve();
                    }
                    onError(error?.message || 'Authentication failed.', { redirectToLogin: false });
                    return Promise.resolve();
                }
            }
        };

        if (!container.id) {
            container.id = `firebaseui-auth-container-${Date.now()}`;
        }

        // Clear loading placeholder before FirebaseUI renders.
        container.innerHTML = '';
        ui.start(`#${container.id}`, uiConfig);
        return { initialized: true, providerCount: signInOptions.length };
    } catch (error) {
        setStatus('OAuth providers could not be initialized.', 'warning');
        renderInfoState(container, 'OAuth sign-in is temporarily unavailable. Please use email and password.');
        return { initialized: false, reason: 'init_exception', error };
    }
}

export {
    ensureFirebaseUiResources,
    normalizeProvider as normalizeProviderId,
    isPopupCancelError,
    postFirebaseSignin
};

// Default export (backwards compatible)
export default {
    initFirebaseUISignIn,
    ensureFirebaseUiResources,
    normalizeProviderId: normalizeProvider,
    isPopupCancelError,
    postFirebaseSignin
};

