const logoutState = {
    initialized: false,
    inFlight: false
};

const runtimeAssetVersion = (() => {
    try {
        return new URL(import.meta.url).searchParams.get('v') || '';
    } catch (err) {
        return '';
    }
})();

function withAssetVersion(url) {
    if (!runtimeAssetVersion) return url;
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}v=${encodeURIComponent(runtimeAssetVersion)}`;
}

function toAbsoluteUrl(value) {
    try {
        return new URL(String(value || '/logout'), window.location.origin);
    } catch (err) {
        return new URL('/logout', window.location.origin);
    }
}

function resolveLogoutTarget(options = {}, triggerEl = null) {
    const configured = options.logoutUrl || '/logout';
    const anchorHref = triggerEl?.getAttribute?.('href');
    const candidate = anchorHref || configured;
    const url = toAbsoluteUrl(candidate);
    if (url.origin !== window.location.origin) {
        return '/logout';
    }
    return `${url.pathname}${url.search}${url.hash}`;
}

function setGuestDeviceCookie() {
    try {
        const deviceId = localStorage.getItem('__fcm_device_id');
        if (!deviceId) return;
        const maxAge = 60 * 60 * 24 * 365; // 1 year
        document.cookie = `guest_device_id=${encodeURIComponent(deviceId)}; Path=/; Max-Age=${maxAge}; SameSite=Lax`;
    } catch (err) {
        // Silent fail
    }
}

function withTimeout(promise, timeoutMs) {
    const timeout = Number.isFinite(timeoutMs) ? timeoutMs : 2500;
    return Promise.race([
        promise,
        new Promise((resolve) => setTimeout(resolve, timeout))
    ]);
}

async function tryFirebaseSignOut(timeoutMs = 2500) {
    try {
        const mod = await import(withAssetVersion('/assets/firebase/v2/dist/auth.js'));
        const signOutUser = mod?.signOutUser || mod?.default?.signOutUser;
        if (typeof signOutUser !== 'function') return;
        await withTimeout(
            Promise.resolve().then(() => signOutUser({ syncWithBackend: false })),
            timeoutMs
        );
    } catch (err) {
        // Silent fail, local logout must continue.
    }
}

export async function performUnifiedLogout(options = {}) {
    if (logoutState.inFlight) return false;
    logoutState.inFlight = true;
    try {
        const target = resolveLogoutTarget(options, options.triggerEl || null);
        setGuestDeviceCookie();
        await tryFirebaseSignOut(options.timeoutMs);
        window.location.href = target || '/logout';
        return true;
    } finally {
        setTimeout(() => {
            logoutState.inFlight = false;
        }, 1500);
    }
}

export function initUnifiedLogout(options = {}) {
    if (logoutState.initialized) return;
    logoutState.initialized = true;

    const selector = options.selector || '[data-unified-logout], a[href="/logout"]';
    document.addEventListener('click', (event) => {
        const target = event.target?.closest?.(selector);
        if (!target) return;
        if (event.defaultPrevented) return;
        if (event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        performUnifiedLogout({
            ...options,
            triggerEl: target
        });
    });

    window.performUnifiedLogout = (runtimeOptions = {}) =>
        performUnifiedLogout({ ...options, ...runtimeOptions });
}

