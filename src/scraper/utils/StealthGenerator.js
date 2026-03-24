/**
 * StealthGenerator
 * Generates realistic browser fingerprints and stealth headers
 */

class StealthGenerator {
    constructor() {
        this.chromeVersions = ['120.0.6099.109', '119.0.6045.159', '118.0.5993.117'];
        this.firefoxVersions = ['121.0', '120.0.1', '119.0.1'];
        this.safariVersions = ['16.3', '16.2', '15.6.1'];
    }

    /**
     * Generate Sec-Ch-Ua header
     */
    generateSecChUa(userAgent) {
        if (userAgent.includes('Chrome')) {
            const version = this.getChromeVersion();
            return `"Chromium";v="${version.split('.')[0]}", "Google Chrome";v="${version.split('.')[0]}", "Not:A-Brand";v="99"`;
        } else if (userAgent.includes('Firefox')) {
            return `"Firefox";v="${this.getFirefoxVersion().split('.')[0]}"`;
        } else if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) {
            return `"Safari";v="${this.getSafariVersion().split('.')[0]}"`;
        }
        return '';
    }

    /**
     * Generate realistic viewport dimensions
     */
    generateViewport() {
        const viewports = [
            { width: 1920, height: 1080 },
            { width: 1366, height: 768 },
            { width: 1440, height: 900 },
            { width: 1536, height: 864 },
            { width: 1280, height: 720 },
            { width: 1680, height: 1050 }
        ];
        return viewports[Math.floor(Math.random() * viewports.length)];
    }

    /**
     * Generate realistic screen dimensions
     */
    generateScreen() {
        const screens = [
            { width: 1920, height: 1080 },
            { width: 1366, height: 768 },
            { width: 1440, height: 900 },
            { width: 2560, height: 1440 },
            { width: 3840, height: 2160 }
        ];
        return screens[Math.floor(Math.random() * screens.length)];
    }

    /**
     * Generate realistic timezone
     */
    generateTimezone() {
        const timezones = [
            'America/New_York',
            'Europe/London',
            'Asia/Dhaka',
            'Asia/Kolkata',
            'Australia/Sydney',
            'Pacific/Auckland'
        ];
        return timezones[Math.floor(Math.random() * timezones.length)];
    }

    /**
     * Generate WebGL renderer info
     */
    generateWebGLRenderer() {
        const renderers = [
            'ANGLE (Intel, Intel(R) UHD Graphics 620 Direct3D11 vs_5_0 ps_5_0, D3D11)',
            'ANGLE (NVIDIA, NVIDIA GeForce GTX 1650 Direct3D11 vs_5_0 ps_5_0, D3D11)',
            'ANGLE (AMD, AMD Radeon RX 580 Direct3D11 vs_5_0 ps_5_0, D3D11)',
            'Intel(R) UHD Graphics 620',
            'NVIDIA GeForce GTX 1650',
            'AMD Radeon RX 580'
        ];
        return renderers[Math.floor(Math.random() * renderers.length)];
    }

    /**
     * Generate canvas fingerprint noise
     */
    generateCanvasNoise() {
        return Math.random() * 0.0001; // Small random variation
    }

    /**
     * Generate audio context fingerprint
     */
    generateAudioFingerprint() {
        return Math.random() * 0.001; // Small random variation
    }

    /**
     * Get random Chrome version
     */
    getChromeVersion() {
        return this.chromeVersions[Math.floor(Math.random() * this.chromeVersions.length)];
    }

    /**
     * Get random Firefox version
     */
    getFirefoxVersion() {
        return this.firefoxVersions[Math.floor(Math.random() * this.firefoxVersions.length)];
    }

    /**
     * Get random Safari version
     */
    getSafariVersion() {
        return this.safariVersions[Math.floor(Math.random() * this.safariVersions.length)];
    }

    /**
     * Generate complete browser fingerprint
     */
    generateFingerprint() {
        const viewport = this.generateViewport();
        const screen = this.generateScreen();

        return {
            userAgent: this.generateUserAgent(),
            viewport: viewport,
            screen: screen,
            timezone: this.generateTimezone(),
            webgl: {
                renderer: this.generateWebGLRenderer(),
                vendor: 'Google Inc. (Intel)'
            },
            canvas: {
                noise: this.generateCanvasNoise()
            },
            audio: {
                fingerprint: this.generateAudioFingerprint()
            },
            languages: this.generateLanguages(),
            platform: this.generatePlatform(),
            cookieEnabled: true,
            doNotTrack: null,
            hardwareConcurrency: this.generateHardwareConcurrency(),
            deviceMemory: this.generateDeviceMemory()
        };
    }

    /**
     * Generate realistic user agent
     */
    generateUserAgent() {
        const browsers = ['chrome', 'firefox', 'safari'];
        const browser = browsers[Math.floor(Math.random() * browsers.length)];

        switch (browser) {
            case 'chrome':
                return `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/${this.getChromeVersion()} Safari/537.36`;
            case 'firefox':
                return `Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:${this.getFirefoxVersion()}) Gecko/20100101 Firefox/${this.getFirefoxVersion()}`;
            case 'safari':
                return `Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/${this.getSafariVersion()} Safari/605.1.15`;
            default:
                return `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/${this.getChromeVersion()} Safari/537.36`;
        }
    }

    /**
     * Generate language preferences
     */
    generateLanguages() {
        const languageSets = [
            ['en-US', 'en', 'bn'],
            ['en-US', 'en'],
            ['en-GB', 'en', 'bn'],
            ['bn', 'en-US', 'en']
        ];
        return languageSets[Math.floor(Math.random() * languageSets.length)];
    }

    /**
     * Generate platform string
     */
    generatePlatform() {
        const platforms = ['Win32', 'MacIntel', 'Linux x86_64'];
        return platforms[Math.floor(Math.random() * platforms.length)];
    }

    /**
     * Generate hardware concurrency
     */
    generateHardwareConcurrency() {
        const cores = [4, 8, 12, 16, 2, 6];
        return cores[Math.floor(Math.random() * cores.length)];
    }

    /**
     * Generate device memory
     */
    generateDeviceMemory() {
        const memories = [4, 8, 16, 2];
        return memories[Math.floor(Math.random() * memories.length)];
    }

    /**
     * Generate realistic mouse movements
     */
    generateMouseMovements(duration = 1000) {
        const movements = [];
        let time = 0;
        const steps = Math.floor(duration / 50); // 50ms intervals

        for (let i = 0; i < steps; i++) {
            movements.push({
                x: Math.random() * 1920,
                y: Math.random() * 1080,
                time: time
            });
            time += 50;
        }

        return movements;
    }

    /**
     * Generate realistic scroll behavior
     */
    generateScrollBehavior() {
        return {
            scrollX: Math.random() * 100,
            scrollY: Math.random() * 500,
            scrollBehavior: 'smooth'
        };
    }

    /**
     * Generate realistic timing variations
     */
    generateTimingVariations() {
        return {
            navigationStart: Date.now() - Math.random() * 1000,
            fetchStart: Date.now() - Math.random() * 500,
            domContentLoaded: Date.now() + Math.random() * 2000,
            loadComplete: Date.now() + Math.random() * 3000
        };
    }
}

export default StealthGenerator;