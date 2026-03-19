/**
 * AI Service - Main Export
 * 
 * Unified exports for all AI services
 */

export { aiRouter, defaultAIRouter } from './AIRouter.js';
export { RAGEngine, defaultRAGEngine } from './RAGEngine.js';
export { knowledgeBase, defaultKnowledgeBase } from './services/KnowledgeBase.js';
export { cvEnhancer, CVEnhancer } from './services/CVEnhancer.js';
export { selfHealingKB, SelfHealingKnowledgeBase } from './services/SelfHealingKnowledgeBase.js';
export { phpBridge } from './services/PHPBridge.js';
export { skillsManager } from './services/SkillsManager.js';
export { aiWorker } from './services/AIWorker.js';

import config, { FEATURE_FLAGS, PROVIDER_CONFIGS } from './config.js';

export { config, FEATURE_FLAGS, PROVIDER_CONFIGS };

// Export providers
export { GoogleProvider } from './providers/GoogleProvider.js';
export { OpenAIProvider } from './providers/OpenAIProvider.js';
export { AnthropicProvider } from './providers/AnthropicProvider.js';
export { OpenAICompatibleProvider } from './providers/OpenAICompatibleProvider.js';

// Export utilities
export { default as Logger } from './utils/Logger.js';
export { default as Cache } from './utils/Cache.js';

// Re-export for convenience
export { FEATURE_FLAGS as flags, PROVIDER_CONFIGS as providers } from './config.js';
