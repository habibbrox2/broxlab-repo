import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index;
import logger from '../../utils/logger;

const listToolsSchema = z.object({
    namespace: z.string().optional(),
    requiresAuth: z.boolean().optional(),
});

export const listToolsTool: ToolDefinition = {
    name: 'list_tools',
    displayName: 'List Available Tools',
    description: 'List all available AI tools with their descriptions and parameters',
    parameters: listToolsSchema,
    requiresAuth: false, // Publicly accessible to see what tools are available
    cacheable: true,
    timeout: 5000,
    maxRetries: 1,
    execute: async (args: any, context: ToolContext): Promise<ToolResult> => {
        const { namespace, requiresAuth } = args;

        try {
            const registry = context.registry;
            if (!registry) {
                return {
                    success: false,
                    error: 'Tool registry not available',
                };
            }

            let tools = registry.getAll();

            // Apply filters
            if (namespace) {
                tools = tools.filter((tool: ToolDefinition) => tool.namespace === namespace);
            }

            if (requiresAuth !== undefined) {
                tools = tools.filter((tool: ToolDefinition) => tool.requiresAuth === requiresAuth);
            }

            // Format response (hide implementation details)
            const formattedTools = tools.map((tool: ToolDefinition) => ({
                name: tool.name,
                displayName: tool.displayName,
                description: tool.description,
                namespace: tool.namespace,
                requiresAuth: tool.requiresAuth,
                cacheable: tool.cacheable,
                timeout: tool.timeout,
                parameters: tool.parameters,
            }));

            // Group by namespace
            const grouped: Record<string, any[]> = {};
            for (const tool of formattedTools) {
                const ns = tool.namespace || 'default';
                if (!grouped[ns]) {
                    grouped[ns] = [];
                }
                grouped[ns].push(tool);
            }

            return {
                success: true,
                data: {
                    total: tools.length,
                    tools: formattedTools,
                    grouped,
                },
            };
        } catch (error: any) {
            logger.error('Failed to list tools:', error);
            return {
                success: false,
                error: `Failed to list tools: ${error.message}`,
            };
        }
    },
};
