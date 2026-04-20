import { FastifyRequest, FastifyReply } from 'fastify';
import { config } from '../config/index';
import { providers } from '../providers/registry';
import logger from '../utils/logger';

const getSettingsFromDB = async () => {
  return {
    provider: config.ai.defaultProvider || 'openrouter',
    apiKey: config.ai.openrouter.apiKey || '',
    model: config.ai.defaultModel || 'openrouter/auto',
    availableProviders: Object.keys(providers),
  };
};

const saveSettingsToDB = async (provider: string, apiKey: string, model: string) => {
  if (provider === 'openrouter') {
    process.env.OPENROUTER_API_KEY = apiKey;
    process.env.AI_PROVIDER = 'openrouter';
    process.env.AI_MODEL = model || 'openrouter/auto';
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
          model: model || (provider === 'openrouter' ? 'openrouter/auto' : 'llama2'),
        },
      });
    } catch (error) {
      logger.error('Error saving AI settings:', error);
      reply.code(500).send({ error: 'Failed to save AI settings' });
    }
  },
};
