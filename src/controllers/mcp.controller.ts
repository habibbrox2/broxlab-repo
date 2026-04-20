import { FastifyRequest, FastifyReply } from 'fastify';
import logger from '../utils/logger';

const getMCPSettingsFromDB = async () => {
  return {
    serverUrl: process.env.MCP_SERVER_URL || '',
    apiKey: process.env.MCP_API_KEY || '',
  };
};

const saveMCPSettingsToDB = async (serverUrl: string, apiKey: string) => {
  process.env.MCP_SERVER_URL = serverUrl;
  process.env.MCP_API_KEY = apiKey;
  return true;
};

export const mcpController = {
  getMCPSettings: async (_req: FastifyRequest, reply: FastifyReply) => {
    try {
      const settings = await getMCPSettingsFromDB();
      reply.send(settings);
    } catch (error) {
      logger.error('Error fetching MCP settings:', error);
      reply.code(500).send({ error: 'Failed to fetch MCP settings' });
    }
  },

  saveMCPSettings: async (_req: FastifyRequest, reply: FastifyReply) => {
    try {
      const { serverUrl, apiKey } = _req.body as { serverUrl?: string; apiKey?: string };

      if (!serverUrl) {
        return reply.code(400).send({ error: 'Server URL is required' });
      }

      try {
        new URL(serverUrl);
      } catch {
        return reply.code(400).send({ error: 'Invalid server URL format' });
      }

      await saveMCPSettingsToDB(serverUrl, apiKey || '');

      reply.send({
        success: true,
        message: 'MCP settings saved successfully',
        settings: {
          serverUrl,
          apiKey: apiKey ? `********${apiKey.slice(-4)}` : '',
        },
      });
    } catch (error) {
      logger.error('Error saving MCP settings:', error);
      reply.code(500).send({ error: 'Failed to save MCP settings' });
    }
  },
};
