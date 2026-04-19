/**
 * CommandHandler - Slash Command Execution
 * Path: /public_html/ai/js/modules/handlers/CommandHandler.js
 *
 * Parses and executes slash commands
 */

export class CommandHandler {
    constructor(stateManager, chatService, config = {}) {
        this.state = stateManager;
        this.chatService = chatService;
        this.config = {
            commandPrefix: '/',
            ...config,
        };

        this.commands = this.registerCommands();
    }

    /**
     * Register available commands
     */
    registerCommands() {
        return {
            // Admin commands
            summarize: {
                name: 'summarize',
                category: 'admin',
                description: 'Summarize selected content',
                icon: 'bi-file-earmark-text',
                keywords: ['summary', 'summarise'],
                handler: this.handleSummarize.bind(this),
            },

            'analyze-logs': {
                name: 'analyze-logs',
                category: 'admin',
                description: 'Analyze error logs for issues',
                icon: 'bi-bug',
                keywords: ['logs', 'errors', 'debug'],
                handler: this.handleAnalyzeLogs.bind(this),
            },

            'generate-report': {
                name: 'generate-report',
                category: 'admin',
                description: 'Generate admin report',
                icon: 'bi-file-earmark-pdf',
                keywords: ['report', 'pdf', 'export'],
                handler: this.handleGenerateReport.bind(this),
            },

            // System commands
            'check-security': {
                name: 'check-security',
                category: 'system',
                description: 'Check system security status',
                icon: 'bi-shield-lock',
                keywords: ['security', 'safe', 'threat'],
                handler: this.handleCheckSecurity.bind(this),
            },

            'health-check': {
                name: 'health-check',
                category: 'system',
                description: 'Check system health and status',
                icon: 'bi-heart-pulse',
                keywords: ['health', 'status', 'monitor'],
                handler: this.handleHealthCheck.bind(this),
            },

            'optimize-db': {
                name: 'optimize-db',
                category: 'system',
                description: 'Optimize database performance',
                icon: 'bi-lightning',
                keywords: ['database', 'performance', 'optimize'],
                handler: this.handleOptimizeDb.bind(this),
            },

            // Content commands
            'summarize-page': {
                name: 'summarize-page',
                category: 'content',
                description: 'Summarize current page content',
                icon: 'bi-file-earmark',
                keywords: ['page', 'content', 'summary'],
                handler: this.handleSummarizePage.bind(this),
            },

            'analyze-posts': {
                name: 'analyze-posts',
                category: 'content',
                description: 'Analyze blog posts',
                icon: 'bi-chat-left-text',
                keywords: ['posts', 'articles', 'analyze'],
                handler: this.handleAnalyzePosts.bind(this),
            },

            'generate-alt-text': {
                name: 'generate-alt-text',
                category: 'content',
                description: 'Generate alt text for images',
                icon: 'bi-image',
                keywords: ['image', 'alt', 'accessibility'],
                handler: this.handleGenerateAltText.bind(this),
            },

            'extract-entities': {
                name: 'extract-entities',
                category: 'content',
                description: 'Extract named entities',
                icon: 'bi-tag',
                keywords: ['entities', 'extract', 'nlp'],
                handler: this.handleExtractEntities.bind(this),
            },

            'suggest-replies': {
                name: 'suggest-replies',
                category: 'content',
                description: 'Suggest replies to comments',
                icon: 'bi-chat-dots',
                keywords: ['replies', 'comments', 'suggest'],
                handler: this.handleSuggestReplies.bind(this),
            },

            // Web commands
            'web-search': {
                name: 'web-search',
                category: 'web',
                description: 'Search the web for information',
                icon: 'bi-search',
                keywords: ['search', 'web', 'google'],
                handler: this.handleWebSearch.bind(this),
            },

            'check-seo': {
                name: 'check-seo',
                category: 'web',
                description: 'Check SEO optimization',
                icon: 'bi-bar-chart',
                keywords: ['seo', 'optimization', 'ranking'],
                handler: this.handleCheckSeo.bind(this),
            },

            'batch-translate': {
                name: 'batch-translate',
                category: 'web',
                description: 'Translate content to multiple languages',
                icon: 'bi-globe',
                keywords: ['translate', 'language', 'international'],
                handler: this.handleBatchTranslate.bind(this),
            },

            // Knowledge commands
            'search-kb': {
                name: 'search-kb',
                category: 'knowledge',
                description: 'Search knowledge base',
                icon: 'bi-book',
                keywords: ['knowledge', 'search', 'learn'],
                handler: this.handleSearchKb.bind(this),
            },

            // Maintenance commands
            'clear-cache': {
                name: 'clear-cache',
                category: 'maintenance',
                description: 'Clear application cache',
                icon: 'bi-trash',
                keywords: ['cache', 'clear', 'reset'],
                handler: this.handleClearCache.bind(this),
            },

            'fix-permissions': {
                name: 'fix-permissions',
                category: 'maintenance',
                description: 'Fix file permissions',
                icon: 'bi-lock',
                keywords: ['permissions', 'fix', 'access'],
                handler: this.handleFixPermissions.bind(this),
            },

            'deploy-status': {
                name: 'deploy-status',
                category: 'maintenance',
                description: 'Check deployment status',
                icon: 'bi-cloud-arrow-up',
                keywords: ['deploy', 'status', 'release'],
                handler: this.handleDeployStatus.bind(this),
            },
        };
    }

    /**
     * Parse command from input text
     */
    parseCommand(text) {
        const trimmed = text.trim();
        if (!trimmed.startsWith(this.config.commandPrefix)) {
            return null;
        }

        const parts = trimmed.substring(1).split(/\s+/);
        const commandName = parts[0];
        const args = parts.slice(1).join(' ');

        return { commandName, args, fullText: trimmed };
    }

    /**
     * Is text a command?
     */
    isCommand(text) {
        return text.trim().startsWith(this.config.commandPrefix);
    }

    /**
     * Get command by name or alias
     */
    getCommand(name) {
        const cmd = this.commands[name];
        if (cmd) return cmd;

        // Check aliases
        for (const command of Object.values(this.commands)) {
            if (command.keywords && command.keywords.includes(name)) {
                return command;
            }
        }

        return null;
    }

    /**
     * Search commands by query
     */
    searchCommands(query) {
        const q = query.toLowerCase();
        return Object.values(this.commands).filter(
            (cmd) =>
                cmd.name.includes(q) ||
                cmd.description.toLowerCase().includes(q) ||
                (cmd.keywords && cmd.keywords.some((k) => k.includes(q)))
        );
    }

    /**
     * Get all commands grouped by category
     */
    getCommandsByCategory() {
        const grouped = {};
        for (const command of Object.values(this.commands)) {
            const cat = command.category || 'other';
            if (!grouped[cat]) grouped[cat] = [];
            grouped[cat].push(command);
        }
        return grouped;
    }

    /**
     * Execute command
     */
    async executeCommand(commandName, args = '') {
        const command = this.getCommand(commandName);
        if (!command) {
            throw new Error(`Unknown command: ${commandName}`);
        }

        console.log('[CommandHandler] Executing command:', { commandName, args });
        return await command.handler(args);
    }

    /**
     * Command handlers
     */

    async handleSummarize(args) {
        return await this.chatService.executeCommand('summarize', { content: args });
    }

    async handleAnalyzeLogs(args) {
        return await this.chatService.executeCommand('analyze-logs', { context: args });
    }

    async handleGenerateReport(args) {
        return await this.chatService.executeCommand('generate-report', { options: args });
    }

    async handleCheckSecurity(args) {
        return await this.chatService.executeCommand('check-security', {});
    }

    async handleHealthCheck(args) {
        return await this.chatService.executeCommand('health-check', {});
    }

    async handleOptimizeDb(args) {
        return await this.chatService.executeCommand('optimize-db', {});
    }

    async handleSummarizePage(args) {
        const pageContent = this.extractPageContent();
        return await this.chatService.executeCommand('summarize-page', { content: pageContent });
    }

    async handleAnalyzePosts(args) {
        return await this.chatService.executeCommand('analyze-posts', { selector: args });
    }

    async handleGenerateAltText(args) {
        return await this.chatService.executeCommand('generate-alt-text', { context: args });
    }

    async handleExtractEntities(args) {
        return await this.chatService.executeCommand('extract-entities', { text: args });
    }

    async handleSuggestReplies(args) {
        return await this.chatService.executeCommand('suggest-replies', { context: args });
    }

    async handleWebSearch(args) {
        return await this.chatService.executeCommand('web-search', { query: args });
    }

    async handleCheckSeo(args) {
        return await this.chatService.executeCommand('check-seo', { url: args });
    }

    async handleBatchTranslate(args) {
        return await this.chatService.executeCommand('batch-translate', { text: args });
    }

    async handleSearchKb(args) {
        return await this.chatService.executeCommand('search-kb', { query: args });
    }

    async handleClearCache(args) {
        return await this.chatService.executeCommand('clear-cache', {});
    }

    async handleFixPermissions(args) {
        return await this.chatService.executeCommand('fix-permissions', {});
    }

    async handleDeployStatus(args) {
        return await this.chatService.executeCommand('deploy-status', {});
    }

    /**
     * Extract main content from page
     */
    extractPageContent() {
        const main = document.querySelector('main') || document.querySelector('article') || document.body;
        const text = main.innerText || '';
        const title = document.querySelector('h1')?.textContent || document.title || '';
        return { title, content: text.substring(0, 5000) }; // Limit to 5000 chars
    }
}

export default CommandHandler;
