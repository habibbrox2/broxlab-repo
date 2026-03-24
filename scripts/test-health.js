const DEFAULT_DIRECT = process.env.SCRAPER_DIRECT_API_URL || process.env.APP_URL || '';
const DEFAULT_QUEUE = process.env.SCRAPER_API_URL || '';
const DEFAULT_PHP = process.env.APP_URL || '';

const args = process.argv.slice(2);
const getArg = (prefix) => {
    const hit = args.find((a) => a.startsWith(prefix + '='));
    return hit ? hit.slice(prefix.length + 1) : '';
};

const mode = (getArg('--mode') || 'both').toLowerCase();
const phpCookie = getArg('--cookie') || '';

const fetchJson = async (url, headers = {}) => {
    const res = await fetch(url, { headers });
    const text = await res.text();
    try {
        return { ok: res.ok, status: res.status, data: JSON.parse(text) };
    } catch {
        return { ok: false, status: res.status, data: { error: 'invalid_json', raw: text } };
    }
};

const check = async (label, baseUrl, path = '/health', headers = {}) => {
    if (!baseUrl) {
        console.log(`[${label}] not_configured`);
        return;
    }
    const url = baseUrl.replace(/\/+$/, '') + path;
    const res = await fetchJson(url, headers);
    if (res.ok && res.data?.success) {
        console.log(`[${label}] ok`);
        return;
    }
    console.log(`[${label}] fail (status=${res.status})`);
};

const run = async () => {
    if (mode === 'direct' || mode === 'both') {
        await check('direct', DEFAULT_DIRECT);
    }
    if (mode === 'queue' || mode === 'both') {
        await check('queue', DEFAULT_QUEUE);
    }
    if (mode === 'php' || mode === 'both') {
        const headers = phpCookie ? { Cookie: phpCookie } : {};
        await check('php', DEFAULT_PHP, '/admin/autocontent/health?ajax=1', headers);
        if (!phpCookie) {
            console.log('[php] note: admin health requires auth; pass --cookie="session=..."');
        }
    }
};

run().catch((err) => {
    console.error('health test failed:', err?.message || err);
    process.exit(1);
});
