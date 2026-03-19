/**
 * Skills Manager
 * 
 * Manages AI skills from ai-skills.json:
 * - general_assistant
 * - admin_assistant
 * - content_summarizer
 * - web_scraper
 * - content_enhancer
 * - code_helper
 * - bengali_translator
 */

import { readFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

class SkillsManager {
    constructor(options = {}) {
        this.skillsFile = options.skillsFile || join(__dirname, '../../../system/prompts/ai-skills.json');
        this.promptsDir = options.promptsDir || join(__dirname, '../../../system/prompts');
        this.skills = null;
        this.fallbackStrategy = null;

        this.loadSkills();
    }

    /**
     * Load skills from JSON file
     */
    loadSkills() {
        if (!existsSync(this.skillsFile)) {
            console.warn('Skills file not found:', this.skillsFile);
            this.skills = {};
            return;
        }

        try {
            const content = readFileSync(this.skillsFile, 'utf-8');
            const data = JSON.parse(content);

            this.skills = {};
            for (const skill of data.skills || []) {
                this.skills[skill.id] = skill;
            }

            this.fallbackStrategy = data.fallback_strategy || { enabled: true, default_skill: 'general_assistant' };

            console.log(`Loaded ${Object.keys(this.skills).length} AI skills`);
        } catch (error) {
            console.error('Failed to load skills:', error.message);
            this.skills = {};
        }
    }

    /**
     * Get skill by ID
     */
    getSkill(skillId) {
        return this.skills?.[skillId] || null;
    }

    /**
     * Get all skills
     */
    getAllSkills() {
        return Object.values(this.skills || {});
    }

    /**
     * Get enabled skills
     */
    getEnabledSkills() {
        return Object.values(this.skills || {}).filter(s => s.enabled !== false);
    }

    /**
     * Get skill system prompt
     */
    getSkillPrompt(skillId) {
        const skill = this.getSkill(skillId);

        if (!skill?.context?.prompt_file) {
            return null;
        }

        const promptPath = join(this.promptsDir, skill.context.prompt_file);

        if (!existsSync(promptPath)) {
            console.warn('Prompt file not found:', promptPath);
            return null;
        }

        try {
            return readFileSync(promptPath, 'utf-8');
        } catch (error) {
            console.error('Failed to read prompt:', error.message);
            return null;
        }
    }

    /**
     * Find best skill for query
     */
    findSkillForQuery(query, userRole = 'public') {
        const queryLower = query.toLowerCase();
        const enabledSkills = this.getEnabledSkills();

        // Sort by priority
        enabledSkills.sort((a, b) => (b.priority || 50) - (a.priority || 50));

        for (const skill of enabledSkills) {
            // Check role requirement
            if (skill.triggers?.require_role) {
                if (!skill.triggers.require_role.includes(userRole)) {
                    continue;
                }
            }

            // Check keywords
            if (skill.triggers?.keywords) {
                const hasKeyword = skill.triggers.keywords.some(k => queryLower.includes(k.toLowerCase()));

                if (hasKeyword) {
                    // Check exclusions
                    if (skill.triggers.exclude) {
                        const hasExclusion = skill.triggers.exclude.some(e => queryLower.includes(e.toLowerCase()));
                        if (hasExclusion) continue;
                    }

                    return skill;
                }
            }
        }

        // Return default skill if no match
        return this.getSkill(this.fallbackStrategy?.default_skill || 'general_assistant');
    }

    /**
     * Get skill config for chat
     */
    getSkillChatConfig(skillId) {
        const skill = this.getSkill(skillId);

        if (!skill) {
            return {
                temperature: 0.7,
                maxTokens: 1000,
            };
        }

        return {
            temperature: skill.context?.temperature ?? 0.7,
            maxTokens: skill.context?.max_tokens ?? 1000,
        };
    }
}

// Export singleton
const skillsManager = new SkillsManager();

export default skillsManager;
export { SkillsManager };