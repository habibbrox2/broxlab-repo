import { query, queryOne } from '../config/database';
import logger from '../utils/logger';

export interface AIProvider {
    id: number;
    provider_name: string;
    api_key_env?: string;
    is_active: boolean;
    extra_settings: Record<string, any>;
}

export interface ModelInfo {
    id: string;
    name: string;
    supports_multimodal?: boolean;
    default?: boolean;
}

/**
 * AI Provider Service
 * Handles fetching provider configurations and API keys from database
 */
export class AIProviderService {
    /**
     * Get provider by name from database
     */
    async getProviderByName(providerName: string): Promise<AIProvider | null> {
        try {
            const provider = await queryOne<AIProvider>(
                `SELECT id, provider_name, api_key_env, is_active, extra_settings 
                 FROM ai_providers 
                 WHERE provider_name = ? AND is_active = 1`,
                [providerName]
            );
            return provider;
        } catch (error) {
            logger.error(`Failed to fetch provider ${providerName}:`, error);
            return null;
        }
    }

    /**
     * Get API key for a provider
     */
    async getAPIKey(providerName: string): Promise<string | null> {
        const provider = await this.getProviderByName(providerName);
        if (!provider) {
            return null;
        }

        if (provider.api_key_env && provider.api_key_env.trim() !== '') {
            const envKey = process.env[provider.api_key_env];
            if (envKey && envKey.trim() !== '') {
                return envKey;
            }
        }

        const settingKey = providerName === 'openrouter'
            ? 'openrouter_api_key'
            : `${providerName}_api_key`;

        const dbKey = await this.getProviderSetting(settingKey);
        if (dbKey && typeof dbKey === 'string' && dbKey.trim() !== '') {
            return dbKey;
        }

        return null;
    }

    async getProviderSetting(key: string): Promise<string | null> {
        try {
            const row = await queryOne<{ setting_value: string }>(
                'SELECT setting_value FROM ai_settings WHERE setting_key = ? LIMIT 1',
                [key]
            );

            if (row && typeof row.setting_value === 'string') {
                return row.setting_value;
            }
        } catch (error) {
            logger.error(`Failed to fetch AI setting ${key}:`, error);
        }

        return null;
    }

    /**
     * Get extra settings for a provider
     */
    async getExtraSettings(providerName: string): Promise<Record<string, any>> {
        const provider = await this.getProviderByName(providerName);
        if (!provider) {
            return {};
        }
        try {
            return typeof provider.extra_settings === 'string'
                ? JSON.parse(provider.extra_settings)
                : provider.extra_settings;
        } catch (error) {
            logger.error(`Failed to parse extra settings for ${providerName}:`, error);
            return {};
        }
    }

    /**
     * Get all active providers
     */
    async getActiveProviders(): Promise<AIProvider[]> {
        try {
            const providers = await query<AIProvider>(
                `SELECT id, provider_name, api_key_env, is_active, extra_settings 
                 FROM ai_providers 
                 WHERE is_active = 1`
            );
            return providers;
        } catch (error) {
            logger.error('Failed to fetch active providers:', error);
            return [];
        }
    }
}

export default new AIProviderService();
