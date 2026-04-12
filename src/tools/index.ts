import { ToolRegistry } from './registry';

// Database tools
import { queryDatabaseTool } from './database/query.tool';
import { getTableStatsTool } from './database/table-stats.tool';
import { getUserStatsTool } from './database/user-stats.tool';

// System tools
import { getSystemHealthTool } from './system/health.tool';
import { getCacheStatsTool } from './system/cache.tool';
import { clearCacheTool } from './system/clear-cache.tool';
import { analyzeErrorLogsTool } from './system/logs.tool';
import { listStorageFilesTool } from './system/storage.tool';
import { getAppSettingsTool } from './system/settings.tool';

// Content tools
import { summarizeTextTool } from './content/summarize.tool';
import { getContentStatsTool } from './content/content-stats.tool';
import { fetchUrlContentTool } from './content/fetch-url.tool';

// Knowledge base tools
import { searchKnowledgeBaseTool } from './knowledge/search.tool';
import { reindexKnowledgeBaseTool } from './knowledge/reindex.tool';

// Utility tools
import { listToolsTool } from './utils/list-tools.tool';

export { ToolRegistry };
export type { ToolDefinition, ToolContext, ToolResult } from '../types/index';

// Re-export all tools
export {
    queryDatabaseTool,
    getTableStatsTool,
    getUserStatsTool,
    getSystemHealthTool,
    getCacheStatsTool,
    clearCacheTool,
    analyzeErrorLogsTool,
    listStorageFilesTool,
    getAppSettingsTool,
    summarizeTextTool,
    getContentStatsTool,
    fetchUrlContentTool,
    searchKnowledgeBaseTool,
    reindexKnowledgeBaseTool,
    listToolsTool,
};

/**
 * Register all tools with the registry
 */
export function registerAllTools(registry: ToolRegistry): void {
    // Database tools
    registry.register(queryDatabaseTool);
    registry.register(getTableStatsTool);
    registry.register(getUserStatsTool);

    // System tools
    registry.register(getSystemHealthTool);
    registry.register(getCacheStatsTool);
    registry.register(clearCacheTool);
    registry.register(analyzeErrorLogsTool);
    registry.register(listStorageFilesTool);
    registry.register(getAppSettingsTool);

    // Content tools
    registry.register(summarizeTextTool);
    registry.register(getContentStatsTool);
    registry.register(fetchUrlContentTool);

    // Knowledge base tools
    registry.register(searchKnowledgeBaseTool);
    registry.register(reindexKnowledgeBaseTool);

    // Utility tools
    registry.register(listToolsTool);
}
