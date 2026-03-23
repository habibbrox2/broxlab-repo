import axios from 'axios';
import { ProxyAgent } from 'proxy-agent';

const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 14; SM-G990B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36'
];

function getRandomInt(min, max) {
    min = Math.ceil(min);
    max = Math.floor(max);
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function getDefaultTimeout() {
    const envTimeout = Number(process.env.SCRAPER_REQUEST_TIMEOUT || 0);
    if (Number.isFinite(envTimeout) && envTimeout >= 8000) {
        return envTimeout;
    }
    return 14000;
}

function getRetryConfig() {
    const count = Number(process.env.SCRAPER_MAX_RETRIES || 2);
    return (Number.isFinite(count) && count >= 0) ? Math.min(count, 4) : 2;
}

async function loadFreeProxies() {
    const sources = [
        'https://www.proxy-list.download/api/v1/get?type=http',
        'https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt'
    ];

    const all = [];
    for (const s of sources) {
        try {
            const response = await axios.get(s, { timeout: 12000 });
            const text = String(response.data || '').trim();
            if (!text) continue;

            const items = text
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line && !line.startsWith('#'));

            all.push(...items);
        } catch (err) {
            console.warn('[httpClient] loadFreeProxies failed', { source: s, error: err?.message || err });
        }
    }

    return Array.from(new Set(all)).slice(0, 300);
}

function normalizeProxyEntry(proxyUrl) {
    if (!proxyUrl || typeof proxyUrl !== 'string') return null;
    let p = proxyUrl.trim();
    if (!p) return null;
    if (!/^https?:\/\//i.test(p)) {
        p = `http://${p}`;
    }
    if (!p.includes('@') && p.split(':').length === 2) {
        return p;
    }
    return p;
}

function getWorkingProxy(proxies) {
    if (!Array.isArray(proxies) || proxies.length === 0) return null;
    const shuffled = proxies.sort(() => 0.5 - Math.random());
    for (const p of shuffled) {
        const normalized = normalizeProxyEntry(p);
        if (normalized) return normalized;
    }
    return null;
}

async function getProxyAgent() {
    const proxyEnv = String(process.env.SCRAPER_PROXIES || '').trim();
    let proxies = [];

    if (proxyEnv) {
        proxies = proxyEnv
            .split(',')
            .map((p) => p.trim())
            .filter(Boolean);
    }

    if (proxies.length === 0 && String(process.env.SCRAPER_PROXY_AUTO_FETCH || 'true').toLowerCase() === 'true') {
        proxies = await loadFreeProxies();
    }

    if (!proxies.length) return null;

    const selected = getWorkingProxy(proxies);
    if (!selected) return null;

    try {
        return new ProxyAgent(selected);
    } catch (err) {
        console.warn('[httpClient] invalid proxy agent', selected, err?.message || err);
        return null;
    }
}

function getHeaders(sourceConfig) {
    const referer = sourceConfig?.listUrl || sourceConfig?.baseUrl || 'https://google.com';
    return {
        'User-Agent': USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)],
        'Accept-Language': 'en-US,en;q=0.9',
        'Referer': referer,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
    };
}

function isWafChallenge(html, status) {
    if (!html || typeof html !== 'string') return false;

    const text = html.toLowerCase();
    if ([503, 403].includes(status) || text.includes('cf-browser-verification') || text.includes('cloudflare') || text.includes('please enable javascript') || text.includes('checking your browser') || text.includes('captcha')) {
        return true;
    }

    if (text.length < 400 && /access denied|403 forbidden|service unavailable/i.test(text)) {
        return true;
    }

    return false;
}

async function request(url, sourceConfig = {}) {
    const timeout = getDefaultTimeout();
    const retries = getRetryConfig();

    let lastError = null;

    for (let attempt = 0; attempt <= retries; attempt += 1) {
        const baseDelayMs = 2000 + Math.floor(Math.random() * 3000);
        await sleep(baseDelayMs);

        if (attempt > 0) {
            const delayMs = 500 + getRandomInt(300, 1200);
            console.warn(`[httpClient] retry attempt ${attempt}/${retries} after ${delayMs}ms`);
            await sleep(delayMs);
        }

        try {
            const useProxy = String(process.env.SCRAPER_PROXY_ROTATION || 'false').toLowerCase() === 'true' || String(process.env.SCRAPER_PROXIES || '').trim() !== '';
            const proxyAgent = useProxy ? await getProxyAgent() : null;

            const response = await axios.get(url, {
                timeout,
                headers: getHeaders(sourceConfig),
                responseType: 'text',
                maxRedirects: 5,
                httpAgent: proxyAgent,
                httpsAgent: proxyAgent,
                validateStatus: (status) => status >= 200 && status < 600
            });

            const body = response.data;
            const waf = isWafChallenge(body, response.status);
            if (waf) {
                lastError = new Error('waf_detected');
                lastError.waf = true;
                lastError.status = response.status;
                console.warn('[httpClient] WAF detected', { url, status: response.status });
                continue;
            }

            if (response.status >= 400) {
                lastError = new Error(`http_error_${response.status}`);
                lastError.status = response.status;
                console.warn('[httpClient] HTTP status not OK', { url, status: response.status });
                continue;
            }

            return { success: true, status: response.status, body };
        } catch (err) {
            lastError = err;
            console.warn('[httpClient] request failed', { url, error: err?.message || err });
        }
    }

    return { success: false, error: lastError?.message || 'request_failed', waf: lastError?.waf === true };
}

export default {
    request,
    sleep,
    getRandomInt,
    isWafChallenge
};
