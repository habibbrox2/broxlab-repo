import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index.js';
import { readdir, stat } from 'fs/promises';
import { join } from 'path';
import logger from '../../utils/logger.js';

const listStorageSchema = z.object({
    directory: z.string().optional().default('storage'),
    recursive: z.boolean().optional().default(false),
    filter: z.string().optional(),
});

export const listStorageFilesTool: ToolDefinition = {
    name: 'list_storage_files',
    displayName: 'List Storage Files',
    description: 'List files in storage directory with size, dates, and metadata',
    parameters: listStorageSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 10000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { directory, recursive, filter } = args;

        try {
            const basePath = join(process.cwd(), directory);

            // Check if directory exists
            try {
                await stat(basePath);
            } catch (error: any) {
                if (error.code === 'ENOENT') {
                    return {
                        success: false,
                        error: `Directory not found: ${directory}`,
                    };
                }
                throw error;
            }

            const files: any[] = [];

            async function scanDir(dirPath: string, relativePath: string = ''): Promise<void> {
                const items = await readdir(dirPath, { withFileTypes: true });

                for (const item of items) {
                    const fullPath = join(dirPath, item.name);
                    const itemRelativePath = join(relativePath, item.name);

                    // Apply filter if provided
                    if (filter && !item.name.toLowerCase().includes(filter.toLowerCase())) {
                        continue;
                    }

                    const stats = await stat(fullPath);

                    const fileInfo = {
                        name: item.name,
                        path: itemRelativePath,
                        size: stats.size,
                        isDirectory: item.isDirectory(),
                        modified: stats.mtime.toISOString(),
                        created: stats.birthtime.toISOString(),
                    };

                    files.push(fileInfo);

                    // Recursively scan subdirectories if requested
                    if (recursive && item.isDirectory()) {
                        await scanDir(fullPath, itemRelativePath);
                    }
                }
            }

            await scanDir(basePath);

            // Calculate totals
            const totalFiles = files.length;
            const totalSize = files.reduce((sum, file) => sum + file.size, 0);
            const directories = files.filter(f => f.isDirectory).length;
            const regularFiles = totalFiles - directories;

            // Sort by size (largest first)
            files.sort((a, b) => b.size - a.size);

            return {
                success: true,
                data: {
                    directory,
                    files: files.slice(0, 100), // Limit to 100 files
                    summary: {
                        totalFiles,
                        regularFiles,
                        directories,
                        totalSize,
                        totalSizeFormatted: formatBytes(totalSize),
                    },
                },
            };
        } catch (error: any) {
            logger.error('Failed to list storage files:', error);
            return {
                success: false,
                error: `Failed to list storage files: ${error.message}`,
            };
        }
    },
};

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
