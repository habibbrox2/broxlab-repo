/**
 * URL Validator Utility
 * Validates URLs for SSRF protection and format validation
 */

class URLValidator {
    // Private IP ranges to block
    static BLOCKED_RANGES = [
        { min: '127.0.0.0', max: '127.255.255.255' },      // Loopback
        { min: '10.0.0.0', max: '10.255.255.255' },        // Private
        { min: '172.16.0.0', max: '172.31.255.255' },      // Private
        { min: '192.168.0.0', max: '192.168.255.255' },    // Private
        { min: '169.254.0.0', max: '169.254.255.255' }     // Link-local
    ];

    /**
     * Validate URL format and SSRF protection
     * @param {string} urlString - URL to validate
     * @returns {object} {valid: boolean, error: string|null, url: URL|null}
     */
    static validate(urlString) {
        if (!urlString || typeof urlString !== 'string') {
            return { valid: false, error: 'Invalid URL: empty or not a string', url: null };
        }

        // Trim whitespace
        const trimmed = urlString.trim();

        try {
            // Validate URL format
            const url = new URL(trimmed);

            // Check protocol
            if (!['http:', 'https:'].includes(url.protocol)) {
                return { valid: false, error: 'Invalid protocol: only http/https allowed', url: null };
            }

            // Check for javascript: or data: URLs
            if (trimmed.toLowerCase().startsWith('javascript:') ||
                trimmed.toLowerCase().startsWith('data:')) {
                return { valid: false, error: 'Invalid protocol: javascript/data URLs blocked', url: null };
            }

            // SSRF check: validate hostname
            const hostname = url.hostname;
            if (!hostname) {
                return { valid: false, error: 'Invalid URL: missing hostname', url: null };
            }

            // Block localhost variations and private IPs
            if (hostname === 'localhost' || hostname === 'localhost.localdomain') {
                return { valid: false, error: 'SSRF protection: localhost blocked', url: null };
            }

            // Check if IP and in private range
            if (this._isIPAddress(hostname) && this._isPrivateIP(hostname)) {
                return { valid: false, error: 'SSRF protection: private IP blocked', url: null };
            }

            return { valid: true, error: null, url };
        } catch (error) {
            return { valid: false, error: `Invalid URL format: ${error.message}`, url: null };
        }
    }

    /**
     * Normalize URL (canonicalize)
     * @param {string} urlString - URL to normalize
     */
    static normalize(urlString) {
        const validation = this.validate(urlString);
        if (!validation.valid) {
            return null;
        }

        const url = validation.url;
        let normalized = url.origin + url.pathname;

        // Add query string if present
        if (url.search) {
            normalized += url.search;
        }

        // Remove trailing slash for root path
        if (normalized.endsWith('/') && url.pathname === '/') {
            // Keep it
        } else if (normalized.endsWith('/') && url.pathname !== '/') {
            normalized = normalized.slice(0, -1);
        }

        return normalized;
    }

    /**
     * Check if string is IP address
     * @private
     */
    static _isIPAddress(hostname) {
        // Simple IPv4 check
        const ipv4Pattern = /^(\d{1,3}\.){3}\d{1,3}$/;
        return ipv4Pattern.test(hostname);
    }

    /**
     * Check if IP is in private range
     * @private
     */
    static _isPrivateIP(ip) {
        const parts = ip.split('.').map(Number);
        if (parts.length !== 4 || parts.some(p => p < 0 || p > 255)) {
            return false;
        }

        for (const range of this.BLOCKED_RANGES) {
            if (this._ipInRange(ip, range.min, range.max)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in range
     * @private
     */
    static _ipInRange(ip, minIp, maxIp) {
        const ipNum = this._ipToNumber(ip);
        const minNum = this._ipToNumber(minIp);
        const maxNum = this._ipToNumber(maxIp);
        return ipNum >= minNum && ipNum <= maxNum;
    }

    /**
     * Convert IP to number
     * @private
     */
    static _ipToNumber(ip) {
        const parts = ip.split('.').map(Number);
        return (parts[0] << 24) + (parts[1] << 16) + (parts[2] << 8) + parts[3];
    }
}

export default URLValidator;
