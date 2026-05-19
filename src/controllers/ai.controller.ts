import { FastifyRequest, FastifyReply } from 'fastify';
import { config } from '../config/index';
import { providers } from '../providers/registry';
import logger from '../utils/logger';

const getSettingsFromDB = async () => {
  return {
    provider: config.ai.defaultProvider || 'openrouter',
    apiKey:
      config.ai.defaultProvider === 'openai'
        ? config.ai.openai.apiKey || ''
        : config.ai.defaultProvider === 'anthropic'
          ? config.ai.anthropic.apiKey || ''
          : config.ai.defaultProvider === 'google' || config.ai.defaultProvider === 'gemini'
            ? config.ai.google.apiKey || ''
            : config.ai.defaultProvider === 'openrouter'
              ? config.ai.openrouter.apiKey || ''
              : config.ai.defaultProvider === 'ollama'
                ? process.env.OLLAMA_API_KEY || ''
                : '',
    model: config.ai.defaultModel || 'meta-llama/llama-3-8b-instruct:free',
    availableProviders: Object.keys(providers),
  };
};

const saveSettingsToDB = async (provider: string, apiKey: string, model: string) => {
  if (provider === 'openrouter') {
    process.env.OPENROUTER_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'openrouter';
    process.env.AI_MODEL = model || 'meta-llama/llama-3-8b-instruct:free';
  } else if (provider === 'openai') {
    process.env.OPENAI_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'openai';
    process.env.AI_MODEL = model || 'gpt-3.5-turbo';
  } else if (provider === 'anthropic') {
    process.env.ANTHROPIC_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'anthropic';
    process.env.AI_MODEL = model || 'claude-3-haiku';
  } else if (provider === 'google' || provider === 'gemini') {
    process.env.GOOGLE_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'google';
    process.env.AI_MODEL = model || 'gemini-1.5-flash';
  } else if (provider === 'ollama') {
    process.env.OLLAMA_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'ollama';
    process.env.AI_MODEL = model || 'llama2';
  }

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
