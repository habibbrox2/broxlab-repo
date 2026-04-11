import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index;
import { OpenRouterProvider } from '../../providers/openrouter.provider;
import { config } from '../../config/index;
import logger from '../../utils/logger;

const summarizeSchema = z.object({
    text: z.string().min(10).max(10000),
    maxLength: z.number().int().positive().optional().default(500),
    format: z.enum(['paragraph', 'bullets', 'auto']).optional().default('auto'),
});

export const summarizeTextTool: ToolDefinition = {
    name: 'summarize_text',
    displayName: 'Summarize Text',
    description: 'Summarize text using AI. Automatically condenses long content into concise summaries.',
    parameters: summarizeSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 30000,
    maxRetries: 2,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { text, maxLength, format } = args;

        try {
            // Initialize provider
            const provider = new OpenRouterProvider(
                config.ai.openrouter.apiKey || '',
                config.ai.defaultModel
            );

            // Build prompt
            let prompt = `Summarize the following text in ${format === 'auto' ? 'the most appropriate format' : format} with a maximum length of ${maxLength} characters:\n\n${text}`;

            const response = await provider.chat(
                `You are a text summarization assistant. Create concise, accurate summaries that capture the key points without losing important information.`,
                [{ role: 'user', content: prompt }],
                {
                    model: config.ai.backendModel,
                    temperature: 0.3, // Lower temperature for more consistent summaries
                    maxTokens: Math.min(maxLength / 2, 1000), // Rough estimate
                }
            );

            if (!response.content) {
                return {
                    success: false,
                    error: 'No summary generated',
                };
            }

            // Ensure summary doesn't exceed maxLength
            let summary = response.content;
            if (summary.length > maxLength) {
                summary = summary.substring(0, maxLength - 3) + '...';
            }

            return {
                success: true,
                data: {
                    summary,
                    originalLength: text.length,
                    summaryLength: summary.length,
                    compressionRatio: ((summary.length / text.length) * 100).toFixed(1) + '%',
                    model: response.meta.model,
                },
            };
        } catch (error: any) {
            logger.error('Text summarization failed:', error);
            return {
                success: false,
                error: `Summarization failed: ${error.message}`,
            };
        }
    },
};
