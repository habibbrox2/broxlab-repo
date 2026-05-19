import { FastifyRequest, FastifyReply } from 'fastify';
import { config } from '../config/index';
import { providers } from '../providers/registry';
import { queryOne, execute } from '../config/database';
import logger from '../utils/logger';

const getSettingFromDB = async (key: string): Promise<string> => {
  try {
    const row = await queryOne<{ setting_value: string }>(
      'SELECT setting_value FROM ai_settings WHERE setting_key = ? LIMIT 1',
      [key]
    );
    return row?.setting_value || '';
  } catch (error) {
    logger.debug('AI setting DB read failed:', error);
    return '';
  }
};

const saveSettingToDB = async (key: string, value: string): Promise<void> => {
  try {
    await execute(
      'INSERT INTO ai_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
      [key, value]
    );
  } catch (error) {
    logger.warn('AI setting DB write failed:', error);
  }
};

const getSettingsFromDB = async () => {
  const provider = config.ai.defaultProvider || 'openrouter';
  const apiKey = await getSettingFromDB(
    provider === 'openrouter' ? 'openrouter_api_key' : `${provider}_api_key`
  );

  return {
    provider,
    apiKey: apiKey || (
      provider === 'openai'
        ? config.ai.openai.apiKey || ''
        : provider === 'anthropic'
          ? config.ai.anthropic.apiKey || ''
          : provider === 'google' || provider === 'gemini'
            ? config.ai.google.apiKey || ''
            : provider === 'openrouter'
              ? config.ai.openrouter.apiKey || ''
              : provider === 'ollama'
                ? process.env.OLLAMA_API_KEY || ''
                : ''
    ),
    model: config.ai.defaultModel || 'meta-llama/llama-3-8b-instruct:free',
    availableProviders: Object.keys(providers),
  };
};

const saveSettingsToDB = async (provider: string, apiKey: string, model: string) => {
  const normalizedProvider = provider.trim().toLowerCase();
  const settingKey = normalizedProvider === 'openrouter'
    ? 'openrouter_api_key'
    : `${normalizedProvider}_api_key`;

  if (normalizedProvider === 'openrouter') {
    process.env.OPENROUTER_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'openrouter';
    process.env.AI_MODEL = model || 'meta-llama/llama-3-8b-instruct:free';
  } else if (normalizedProvider === 'openai') {
    process.env.OPENAI_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'openai';
    process.env.AI_MODEL = model || 'gpt-3.5-turbo';
  } else if (normalizedProvider === 'anthropic') {
    process.env.ANTHROPIC_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'anthropic';
    process.env.AI_MODEL = model || 'claude-3-haiku';
  } else if (normalizedProvider === 'google' || normalizedProvider === 'gemini') {
    process.env.GOOGLE_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'google';
    process.env.AI_MODEL = model || 'gemini-1.5-flash';
  } else if (normalizedProvider === 'ollama') {
    process.env.OLLAMA_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'ollama';
    process.env.AI_MODEL = model || 'llama2';
  }

  await saveSettingToDB(settingKey, apiKey);
  return true;
};

export const aiController = {
  getAISettings: async (_req: FastifyRequest, reply: FastifyReply) => {
    try {
      const settings = await getSettingsFromDB();
      reply.send(settings);
    } catch (error) {
      logger.error('Error fetching AI settings:', error);
      reply.code(500).send({ error: 'Failed to fetch AI settings' });
    }
  },

  saveAISettings: async (_req: FastifyRequest, reply: FastifyReply) => {
    try {
      const { provider, apiKey, model } = _req.body as {
        provider?: string;
        apiKey?: string;
        model?: string;
      };

      if (!provider || !(provider in providers)) {
        return reply.code(400).send({ error: 'Invalid provider selected' });
      }

      if (provider === 'openrouter' && !apiKey) {
        return reply.code(400).send({ error: 'API key is required for OpenRouter' });
      }

      await saveSettingsToDB(provider, apiKey || '', model || '');

      reply.send({
        success: true,
        message: 'AI settings saved successfully',
        settings: {
          provider,
          apiKey: apiKey ? `********${apiKey.slice(-4)}` : '',
          model: model || (provider === 'openrouter' ? 'meta-llama/llama-3-8b-instruct:free' : 'llama2'),
        },
      });
    } catch (error) {
      logger.error('Error saving AI settings:', error);
      reply.code(500).send({ error: 'Failed to save AI settings' });
    }
  },
};
