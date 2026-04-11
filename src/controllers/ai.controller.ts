import { FastifyRequest, FastifyReply } from 'fastify';
import { providers } from '../providers/registry';

// Mock database interaction - in production, use actual database
const getSettingsFromDB = async () => {
  // This would query the database in a real implementation
  // For now, return from environment or defaults
  return {
    provider: process.env.AI_PROVIDER || 'openrouter',
    apiKey: process.env.OPENROUTER_API_KEY || '',
    model: process.env.AI_MODEL || 'openrouter/auto',
    availableProviders: Object.keys(providers),
  };
};

const saveSettingsToDB = async (provider: string, apiKey: string, model: string) => {
  // This would save to database in a real implementation
  // For now, update environment variables
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
      console.error('Error fetching AI settings:', error);
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

      // Validate provider
      if (!provider || !(provider in providers)) {
        return reply.code(400).send({ error: 'Invalid provider selected' });
      }

      // Validate API key for OpenRouter (required)
      if (provider === 'openrouter' && !apiKey) {
        return reply.code(400).send({ error: 'API key is required for OpenRouter' });
      }

      await saveSettingsToDB(provider, apiKey || '', model || '');

      reply.send({
        success: true,
        message: 'AI settings saved successfully',
        settings: {
          provider,
          apiKey: apiKey ? '••••••••' + apiKey.slice(-4) : '',
          model: model || (provider === 'openrouter' ? 'openrouter/auto' : 'llama2'),
        },
      });
    } catch (error) {
      console.error('Error saving AI settings:', error);
      reply.code(500).send({ error: 'Failed to save AI settings' });
    }
  },
};
