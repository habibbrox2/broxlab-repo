/**
 * PHP Backend Bridge
 * 
 * Connects Node.js AI services to PHP backend for:
 * - Fetching API keys from database
 * - Fetching settings from admin panel
 * - Loading prompts from system/prompts/
 */

import { readFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import axios from 'axios';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

class PHPBridge {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || process.env.PHP_API_URL || 'http://localhost';
        this.promptsDir = options.promptsDir || join(__dirname, '../../../system/prompts');
        this.cache = new Map();
        this.cacheTtl = 60000; // 1 minute cache
    }

    /**
     * Fetch settings from PHP backend
     */
    async fetchSettings() {
        const cacheKey = 'settings';
        const cached = this.cache.get(cacheKey);

        if (cached && Date.now() - cached.timestamp < this.cacheTtl) {
            return cached.data;
        }

        try {
            const response = await axios.get(`${this.baseUrl}/api/ai/settings`, {
                timeout: 5000,
                headers: { 'Accept': 'application/json' }
            });

            this.cache.set(cacheKey, { data: response.data, timestamp: Date.now() });
            return response.data;
        } catch (error) {
            console.warn('Failed to fetch settings from PHP:', error.message);
            return null;
        }
    }

    /**
     * Fetch API keys from PHP backend
     */
    async fetchApiKeys() {
        const cacheKey = 'apiKeys';
        const cached = this.cache.get(cacheKey);

        if (cached && Date.now() - cached.timestamp < this.cacheTtl) {
            return cached.data;
        }

        try {
            const response = await axios.get(`${this.baseUrl}/api/ai/keys`, {
                timeout: 5000,
                headers: { 'Accept': 'application/json' }
            });

            this.cache.set(cacheKey, { data: response.data, timestamp: Date.now() });
            return response.data;
        } catch (error) {
            console.warn('Failed to fetch API keys from PHP:', error.message);
            return null;
        }
    }

    /**
     * Load prompt from file
     */
    loadPrompt(filename) {
        const filepath = join(this.promptsDir, filename);

        if (!existsSync(filepath)) {
            console.warn(`Prompt file not found: ${filepath}`);
            return null;
        }

        try {
            return readFileSync(filepath, 'utf-8');
        } catch (error) {
            console.error(`Failed to read prompt file ${filepath}:`, error.message);
            return null;
        }
    }

    /**
     * Load admin prompt
     */
    loadAdminPrompt() {
        return this.loadPrompt('admin.md');
    }

    /**
     * Load public prompt
     */
    loadPublicPrompt() {
        return this.loadPrompt('public.md');
    }

    /**
     * Load enhancer prompt
     */
    loadEnhancerPrompt() {
        return this.loadPrompt('enhancer.md');
    }

    /**
     * Load all prompts
     */
    loadAllPrompts() {
        return {
            admin: this.loadAdminPrompt(),
            public: this.loadPublicPrompt(),
            enhancer: this.loadEnhancerPrompt(),
            summarizer: this.loadPrompt('summarizer.md'),
            translator: this.loadPrompt('translator.md'),
            codeHelper: this.loadPrompt('code-helper.md'),
            scraper: this.loadPrompt('scraper.md'),
        };
    }

    /**
     * Load skills configuration
     */
    loadSkillsConfig() {
        const filepath = join(this.promptsDir, 'ai-skills.json');

        if (!existsSync(filepath)) {
            console.warn('Skills config not found');
            return null;
        }

        try {
            const content = readFileSync(filepath, 'utf-8');
            return JSON.parse(content);
        } catch (error) {
            console.error('Failed to load skills config:', error.message);
            return null;
        }
    }

    /**
     * Load tools configuration
     */
    loadToolsConfig() {
        const filepath = join(this.promptsDir, 'ai-tools.json');

        if (!existsSync(filepath)) {
            console.warn('Tools config not found');
            return null;
        }

        try {
            const content = readFileSync(filepath, 'utf-8');
            return JSON.parse(content);
        } catch (error) {
            console.error('Failed to load tools config:', error.message);
            return null;
        }
    }

    /**
     * Merge API keys from PHP with environment keys
     */
    async getMergedApiKeys(envKeys = {}) {
        const phpKeys = await this.fetchApiKeys() || {};

        return {
            openai: phpKeys.openai_api_key || envKeys.OPENAI_API_KEY || '',
            anthropic: phpKeys.anthropic_api_key || envKeys.ANTHROPIC_API_KEY || '',
            google: phpKeys.google_api_key || envKeys.GOOGLE_API_KEY || envKeys.GEMINI_API_KEY || '',
            openrouter: phpKeys.openrouter_api_key || envKeys.OPENROUTER_API_KEY || '',
        };
    }

    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }
}

// Export singleton
const phpBridge = new PHPBridge();

export default phpBridge;
export { PHPBridge };