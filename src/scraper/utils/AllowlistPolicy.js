/**
 * AllowlistPolicy Utility
 * Enforces scraping only for authorized hosts.
 */

import CONFIG from '../config.js';

class AllowlistPolicy {
    constructor() {
        this.cachedHosts = null;
    }

    _normalizeHost(hostname) {
        return String(hostname || '').toLowerCase().replace(/^www\./, '');
    }

    _extractHost(urlString) {
        try {
            return this._normalizeHost(new URL(urlString).hostname);
        } catch {
            return '';
        }
    }

    _buildDefaultHosts() {
        if (this.cachedHosts) return this.cachedHosts;

        const hosts = new Set();
        const sources = CONFIG.sources || {};
        for (const key of Object.keys(sources)) {
            const source = sources[key] || {};
            if (source.baseUrl) {
                const host = this._extractHost(source.baseUrl);
                if (host) hosts.add(host);
            }
            if (source.homepageUrl) {
                const host = this._extractHost(source.homepageUrl);
                if (host) hosts.add(host);
            }
        }
        this.cachedHosts = Array.from(hosts);
        return this.cachedHosts;
    }

    _isHostAllowed(hostname, allowlistHosts) {
        const host = this._normalizeHost(hostname);
        if (!host) return false;

        for (const allowed of allowlistHosts) {
            const allowedHost = this._normalizeHost(allowed);
            if (!allowedHost) continue;
            if (host === allowedHost) return true;
            if (host.endsWith(`.${allowedHost}`)) return true;
        }
        return false;
    }

    check(url, allowlistHosts = null) {
        const enforce = String(process.env.SCRAPER_ALLOWLIST_ENFORCE || CONFIG.scraper?.allowlistEnforce || 'true').toLowerCase() !== 'false';
        if (!enforce) {
            return { allowed: true, reason: 'allowlist_disabled' };
        }

        const targetUrl = String(url || '').trim();
        if (!targetUrl) {
            return { allowed: false, reason: 'invalid_url' };
        }

        let hosts = Array.isArray(allowlistHosts) && allowlistHosts.length > 0
            ? allowlistHosts
            : this._buildDefaultHosts();

        if (!hosts || hosts.length === 0) {
            return { allowed: false, reason: 'allowlist_empty' };
        }

        const hostname = this._extractHost(targetUrl);
        const ok = this._isHostAllowed(hostname, hosts);
        return { allowed: ok, reason: ok ? 'allowlist_ok' : 'allowlist_blocked' };
    }
}

export default new AllowlistPolicy();
