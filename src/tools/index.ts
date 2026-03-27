import { ToolRegistry } from './registry.js';

// Database tools
import { queryDatabaseTool } from './database/query.tool.js';
import { getTableStatsTool } from './database/table-stats.tool.js';
import { getUserStatsTool } from './database/user-stats.tool.js';

// System tools
import { getSystemHealthTool } from './system/health.tool.js';
import { getCacheStatsTool } from './system/cache.tool.js';
import { clearCacheTool } from './system/clear-cache.tool.js';
import { analyzeErrorLogsTool } from './system/logs.tool.js';
import { listStorageFilesTool } from './system/storage.tool.js';
import { getAppSettingsTool } from './system/settings.tool.js';

// Content tools
import { summarizeTextTool } from './content/summarize.tool.js';
import { getContentStatsTool } from './content/content-stats.tool.js';

// Knowledge base tools
import { searchKnowledgeBaseTool } from './knowledge/search.tool.js';
import { reindexKnowledgeBaseTool } from './knowledge/reindex.tool.js';

// Utility tools
import { listToolsTool } from './utils/list-tools.tool.js';

export { ToolRegistry };
export type { ToolDefinition, ToolContext, ToolResult } from '../types/index.js';

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

    // Knowledge base tools
    registry.register(searchKnowledgeBaseTool);
    registry.register(reindexKnowledgeBaseTool);

    // Utility tools
    registry.register(listToolsTool);
}
