/**
 * AdaptiveDelayManager
 * Minimal fallback for rate-limiting on shared hosting
 */

class AdaptiveDelayManager {
    constructor(options = {}) {
        this.baseDelay = options.baseDelay || 1500;
        this.backoffMultiplier = options.backoffMultiplier || 1.5;
        this.currentDelay = this.baseDelay;
    }

    async initialize() {
        return true;
    }

    async wait(url) {
        await new Promise((resolve) => setTimeout(resolve, this.currentDelay));
    }

    recordSuccess(url) {
        this.currentDelay = Math.max(this.baseDelay, this.currentDelay / this.backoffMultiplier);
    }

    recordFailure(url) {
        this.currentDelay = Math.min(60000, this.currentDelay * this.backoffMultiplier);
    }

    async cleanup() {
        return true;
    }
}

export default AdaptiveDelayManager;
