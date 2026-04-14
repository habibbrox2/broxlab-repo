import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import { config } from '../../config/index';
import { query } from '../../config/database';
import logger from '../../utils/logger';

const appSettingsSchema = z.object({
    section: z.string().optional().default('all'),
});

export const getAppSettingsTool: ToolDefinition = {
    name: 'get_app_settings',
    displayName: 'Get Application Settings',
    description: 'Retrieve application configuration settings from config and database',
    parameters: appSettingsSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 10000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { section } = args;

        try {
            const settings: any = {
                config: {},
                database: {},
            };

            // Get config-based settings (non-sensitive)
            if (section === 'all' || section === 'app') {
                settings.config.app = {
                    name: 'BroxLab AI Assistant',
                    version: '1.0.0',
                    environment: config.nodeEnv,
                    debug: config.isDevelopment,
                };
            }

            if (section === 'all' || section === 'ai') {
                settings.config.ai = {
                    defaultModel: config.ai.defaultModel,
                    frontendModel: config.ai.frontendModel,
                    temperature: config.ai.temperature,
                    maxTokens: config.ai.maxTokens,
                    providers: {
                        openrouter: !!config.ai.openrouter.apiKey,
                        anthropic: !!config.ai.anthropic.apiKey,
                        ollama: !!config.ai.ollama.baseURL,
                    },
                };
            }

            if (section === 'all' || section === 'database') {
                settings.config.database = {
                    host: config.database.host,
                    port: config.database.port,
                    database: config.database.database,
                    charset: 'utf8mb4',
                };
            }

            if (section === 'all' || section === 'redis') {
                settings.config.redis = {
                    host: config.redis.host,
                    port: config.redis.port,
                    db: config.redis.db,
                    enabled: true, // Redis is configured if we're running
                };
            }

            // Get database settings if table exists
            if (section === 'all' || section === 'database') {
                try {
                    const dbSettings = await query(
                        'SELECT setting_key, setting_value, setting_group FROM app_settings WHERE is_active = 1'
                    );
                    settings.database.settings = dbSettings;
                } catch (error: any) {
                    // Table might not exist
                    if (!error.message.includes('doesn\'t exist') && !error.message.includes('Table')) {
                        logger.warn('Failed to query app settings:', error);
                    }
                    settings.database.settings = [];
                }
            }

            return {
                success: true,
                data: settings,
            };
        } catch (error: any) {
            logger.error('Failed to get app settings:', error);
            return {
                success: false,
                error: `Failed to get application settings: ${error.message}`,
            };
        }
    },
};
