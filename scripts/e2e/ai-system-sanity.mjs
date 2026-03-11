const baseUrl = (process.env.BROX_BASE_URL || 'http://localhost').replace(/\/+$/, '');
const adminCookie = process.env.BROX_ADMIN_COOKIE || '';

function logResult(ok, message) {
    const label = ok ? 'OK' : 'FAIL';
    console.log(`${label} ${message}`);
}

async function fetchJson(path, { headers = {} } = {}) {
    const res = await fetch(`${baseUrl}${path}`, { headers });
    let data = null;
    try {
        data = await res.json();
    } catch {
        data = null;
    }
    return { res, data };
}

async function checkFrontendDefaults() {
    const { res, data } = await fetchJson('/api/ai-system/frontend');
    const ok = res.ok && data && data.provider && data.model;
    logResult(ok, 'frontend defaults loaded');
    return ok ? data : null;
}

async function checkPublicModels(provider) {
    const { res, data } = await fetchJson(`/api/ai/models?provider=${encodeURIComponent(provider)}`);
    const ok = res.ok && data && data.success && Array.isArray(data.models) && data.models.length > 0;
    logResult(ok, `public models available for ${provider}`);
    return ok;
}

async function checkAdminDefaults() {
    if (!adminCookie) {
        console.log('SKIP admin defaults (set BROX_ADMIN_COOKIE to enable)');
        return null;
    }
    const { res, data } = await fetchJson('/api/ai-system/admin-defaults', {
        headers: { Cookie: adminCookie }
    });
    const ok = res.ok && data && data.provider && data.model;
    logResult(ok, 'admin defaults loaded');
    return ok ? data : null;
}

async function checkAdminModels() {
    if (!adminCookie) {
        console.log('SKIP admin model map (set BROX_ADMIN_COOKIE to enable)');
        return false;
    }
    const { res, data } = await fetchJson('/api/ai/models?scope=admin', {
        headers: { Cookie: adminCookie }
    });
    const ok = res.ok && data && data.success && data.providers && Object.keys(data.providers).length > 0;
    logResult(ok, 'admin model map available');
    return ok;
}

async function main() {
    console.log(`Base URL: ${baseUrl}`);
    const frontend = await checkFrontendDefaults();
    if (frontend?.provider) {
        await checkPublicModels(frontend.provider);
    } else {
        logResult(false, 'public models check skipped (no provider)');
    }

    await checkAdminDefaults();
    await checkAdminModels();
}

main().catch((err) => {
    console.error('E2E sanity check failed:', err?.message || err);
    process.exitCode = 1;
});
