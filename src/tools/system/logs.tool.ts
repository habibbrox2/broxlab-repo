import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index;
import { readFile } from 'fs/promises';
import logger from '../../utils/logger;

const analyzeLogsSchema = z.object({
    logFile: z.string().optional().default('storage/logs/app.log'),
    lines: z.number().int().positive().optional().default(100),
    level: z.enum(['error', 'warn', 'info', 'debug']).optional().default('error'),
});

export const analyzeErrorLogsTool: ToolDefinition = {
    name: 'analyze_error_logs',
    displayName: 'Analyze Error Logs',
    description: 'Analyze application error logs to identify common issues and patterns',
    parameters: analyzeLogsSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 15000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { logFile, lines, level } = args;

        try {
            // Check if log file exists
            try {
                await readFile(logFile, 'utf-8');
            } catch (error: any) {
                if (error.code === 'ENOENT') {
                    return {
                        success: false,
                        error: `Log file not found: ${logFile}`,
                    };
                }
                throw error;
            }

            // Read last N lines of the log file
            const fileContent = await readFile(logFile, 'utf-8');
            const allLines = fileContent.split('\n').filter(line => line.trim());

            // Get last N lines
            const recentLines = allLines.slice(-lines);

            // Filter by level if specified
            const filteredLines = recentLines.filter(line => {
                if (level === 'error') return line.toLowerCase().includes('error') || line.toLowerCase().includes('fatal');
                if (level === 'warn') return line.toLowerCase().includes('warn');
                if (level === 'info') return line.toLowerCase().includes('info');
                if (level === 'debug') return line.toLowerCase().includes('debug');
                return true;
            });

            // Analyze patterns
            const patterns = {
                errorCount: 0,
                warnCount: 0,
                infoCount: 0,
                debugCount: 0,
                commonErrors: new Map<string, number>(),
                timeDistribution: new Map<string, number>(),
            };

            filteredLines.forEach(line => {
                const lowerLine = line.toLowerCase();
                const timestampMatch = line.match(/\[(\d{4}-\d{2}-\d{2})/);
                const hour = timestampMatch ? timestampMatch[1] : 'unknown';

                // Count by level
                if (lowerLine.includes('error') || lowerLine.includes('fatal')) {
                    patterns.errorCount++;
                    // Extract error type
                    const errorMatch = line.match(/(?:Error|Exception):\s*([^\n\r]+)/);
                    if (errorMatch) {
                        const errorType = errorMatch[1].trim().substring(0, 50);
                        patterns.commonErrors.set(errorType, (patterns.commonErrors.get(errorType) || 0) + 1);
                    }
                }
                if (lowerLine.includes('warn')) patterns.warnCount++;
                if (lowerLine.includes('info')) patterns.infoCount++;
                if (lowerLine.includes('debug')) patterns.debugCount++;

                // Time distribution
                patterns.timeDistribution.set(hour, (patterns.timeDistribution.get(hour) || 0) + 1);
            });

            // Sort common errors by frequency
            const sortedErrors = Array.from(patterns.commonErrors.entries())
                .sort((a, b) => b[1] - a[1])
                .slice(0, 10);

            return {
                success: true,
                data: {
                    totalLines: filteredLines.length,
                    analyzedLines: recentLines.length,
                    patterns: {
                        errors: patterns.errorCount,
                        warnings: patterns.warnCount,
                        info: patterns.infoCount,
                        debug: patterns.debugCount,
                    },
                    topErrors: sortedErrors,
                    timeDistribution: Object.fromEntries(patterns.timeDistribution),
                    sampleLines: filteredLines.slice(-20), // Last 20 matching lines
                },
            };
        } catch (error: any) {
            logger.error('Failed to analyze error logs:', error);
            return {
                success: false,
                error: `Failed to analyze logs: ${error.message}`,
            };
        }
    },
};
