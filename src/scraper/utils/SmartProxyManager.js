/**
 * SmartProxyManager
 * Minimal fallback for shared hosting and production architecture.
 */

class SmartProxyManager {
    constructor(options = {}) {
        this.proxies = options.proxies || [];
        this.health = new Map();
    }

    async initialize() {
        // no heavy init required
        return true;
    }

    async getProxy() {
        if (this.proxies.length === 0) {
            return null;
        }

        const available = this.proxies.filter((p) => !this.health.get(p) || this.health.get(p) > Date.now());
        const candidate = available.length ? available[Math.floor(Math.random() * available.length)] : this.proxies[Math.floor(Math.random() * this.proxies.length)];
        return candidate;
    }

    async markHealthy(proxy) {
        this.health.set(proxy, Date.now() + 5 * 60 * 1000);
    }

    async markFailed(proxy) {
        this.health.set(proxy, Date.now() + 1 * 60 * 1000);
    }

    async cleanup() {
        this.proxies = [];
        this.health.clear();
        return true;
    }
}

export default SmartProxyManager;
