import logger from '../utils/scraperLogger.js';

class ProxyManager {
    constructor() {
        const raw = process.env.SCRAPER_PROXIES || process.env.PROXY_LIST || '';
        this.pool = raw
            .split(',')
            .map(p => p.trim())
            .filter(Boolean)
            .map(url => ({
                url,
                success: 0,
                failure: 0,
                latency: 0,
                disabled: false
            }));
        this.index = 0;
    }

    hasProxies() {
        return this.pool.length > 0;
    }

    getProxy() {
        if (!this.hasProxies()) return null;
        const healthy = this.pool.filter(p => !p.disabled);
        if (!healthy.length) return null;
        const proxy = healthy[this.index % healthy.length];
        this.index = (this.index + 1) % healthy.length;
        return proxy.url;
    }

    markSuccess(proxyUrl, latencyMs = 0) {
        const entry = this.pool.find(p => p.url === proxyUrl);
        if (!entry) return;
        entry.success += 1;
        entry.latency = latencyMs;
    }

    markFailure(proxyUrl) {
        const entry = this.pool.find(p => p.url === proxyUrl);
        if (!entry) return;
        entry.failure += 1;
        if (entry.failure >= Number(process.env.SCRAPER_PROXY_MAX_FAILS || 3)) {
            entry.disabled = true;
            logger.warn('Proxy disabled due to failures', { proxy: proxyUrl });
        }
    }
}

export default new ProxyManager();
