import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const DEFAULT_URL = 'https://bangla.bdnews24.com/';
const DEFAULT_MODE = 'both';
const DEFAULT_TIMEOUT_MS = 30000;
const POLL_INTERVAL_MS = 800;

const args = process.argv.slice(2);
const getArg = (prefix) => {
    const hit = args.find((a) => a.startsWith(prefix + '='));
    return hit ? hit.slice(prefix.length + 1) : '';
};

const mode = (getArg('--mode') || DEFAULT_MODE).toLowerCase();
const url = getArg('--url') || DEFAULT_URL;
const outPath = getArg('--out') || '';

const apiKey = String(process.env.SCRAPER_API_KEY || '').trim();
const directBase = String(process.env.SCRAPER_DIRECT_API_URL || process.env.NODE_API_URL || process.env.APP_URL || 'http://127.0.0.1:3000/api/direct-scraper').replace(/\/+$/, '');
const queueBase = String(process.env.SCRAPER_API_URL || process.env.NODE_API_URL || process.env.APP_URL || 'http://127.0.0.1:3000/api/scraper').replace(/\/+$/, '');

const headers = {
    'Content-Type': 'application/json'
};
if (apiKey) {
    headers['X-Api-Key'] = apiKey;
} else {
    console.warn('[warn] SCRAPER_API_KEY is empty; requests may be unauthorized.');
}

const toJson = (value) => {
    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
};

const postJson = async (endpoint, payload) => {
    const res = await fetch(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(payload)
    });
    const text = await res.text();
    const data = toJson(text) || { success: false, error: 'invalid_response', raw: text };
    return { status: res.status, data };
};

const getJson = async (endpoint) => {
    const res = await fetch(endpoint, { headers });
    const text = await res.text();
    const data = toJson(text) || { success: false, error: 'invalid_response', raw: text };
    return { status: res.status, data };
};

const printSummary = (label, result) => {
    if (!result) {
        console.log(`[${label}] no result`);
        return;
    }
    if (!result.success) {
        console.log(`[${label}] failed: ${result.error || 'unknown_error'}`);
        return;
    }
    const title = result.title ? String(result.title).slice(0, 120) : '';
    const contentLen = result.content ? String(result.content).length : 0;
    const htmlLen = result.html ? String(result.html).length : 0;
    console.log(`[${label}] ok | title="${title}" | content=${contentLen} | html=${htmlLen} | ms=${result.elapsed_ms ?? 0}`);
};

const saveHtml = (html) => {
    if (!outPath) return;
    const resolved = path.isAbsolute(outPath)
        ? outPath
        : path.join(path.dirname(fileURLToPath(import.meta.url)), '..', outPath);
    fs.writeFileSync(resolved, html || '', 'utf8');
    console.log(`[direct] saved html -> ${resolved}`);
};

const runDirect = async () => {
    const endpoint = `${directBase}/scrape`;
    const payload = { url };
    const { data } = await postJson(endpoint, payload);
    printSummary('direct', data);
    if (data?.success && outPath) {
        saveHtml(data.html || '');
    }
};

const runQueue = async () => {
    const endpoint = `${queueBase}/scrape`;
    const { data } = await postJson(endpoint, { url, options: {} });
    if (!data?.success || !data?.jobId) {
        console.log(`[queue] enqueue failed: ${data?.error || 'enqueue_failed'}`);
        return;
    }

    const jobId = data.jobId;
    const deadline = Date.now() + DEFAULT_TIMEOUT_MS;
    while (Date.now() < deadline) {
        const { data: resData } = await getJson(`${queueBase}/result/${jobId}`);
        if (resData?.success && resData.result) {
            printSummary('queue', resData.result);
            return;
        }
        if (resData?.state === 'failed') {
            console.log('[queue] job failed');
            return;
        }
        await new Promise((r) => setTimeout(r, POLL_INTERVAL_MS));
    }

    console.log('[queue] timeout waiting for result');
};

const main = async () => {
    if (!url) {
        console.error('Missing --url');
        process.exit(1);
    }

    if (mode === 'direct') {
        await runDirect();
        return;
    }
    if (mode === 'queue') {
        await runQueue();
        return;
    }
    if (mode !== 'both') {
        console.error('Invalid --mode. Use direct|queue|both.');
        process.exit(1);
    }

    await runDirect();
    await runQueue();
};

main().catch((err) => {
    console.error('CLI test failed:', err?.message || err);
    process.exit(1);
});
