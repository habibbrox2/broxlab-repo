/**
 * Enhanced Command Menu for AI Assistant
 * Path: /public_html/ai/js/modules/command-menu.js
 * 
 * Features:
 *  - Searchable command dropdown
 *  - Command categories/groups
 *  - Keyboard navigation
 *  - Command descriptions and icons
 *  - Keyboard shortcuts display
 */

export class CommandMenu {
    constructor(config = {}) {
        this.config = {
            menuId: 'adminAiSlashMenu',
            inputId: 'adminAiInput',
            containerSelector: '.brox-ai-footer',
            ...config,
        };

        this.commands = this.initializeCommands();
        this.isOpen = false;
        this.selectedIndex = 0;
        this.filteredCommands = [...this.commands];

        this.init();
    }

    /**
     * Initialize available commands
     */
    initializeCommands() {
        return [
            // Admin Tools
            {
                category: 'Admin',
                icon: 'bi-stars',
                name: '/summarize',
                description: 'Summarize current page data',
                shortcut: null,
                keywords: ['summary', 'brief', 'overview'],
            },
            {
                category: 'Admin',
                icon: 'bi-journal-text',
                name: '/analyze-logs',
                description: 'Analyze system error logs',
                shortcut: null,
                keywords: ['logs', 'errors', 'debug'],
            },
            {
                category: 'Admin',
                icon: 'bi-file-earmark-bar-graph',
                name: '/generate-report',
                description: 'Generate analytics report',
                shortcut: null,
                keywords: ['report', 'stats', 'analytics'],
            },

            // Security & System
            {
                category: 'System',
                icon: 'bi-shield-check',
                name: '/check-security',
                description: 'Run security audit',
                shortcut: null,
                keywords: ['security', 'audit', 'check'],
            },
            {
                category: 'System',
                icon: 'bi-heart-pulse',
                name: '/health-check',
                description: 'System health status',
                shortcut: null,
                keywords: ['health', 'status', 'check'],
            },
            {
                category: 'System',
                icon: 'bi-hdd',
                name: '/optimize-db',
                description: 'Optimize database',
                shortcut: null,
                keywords: ['database', 'optimize', 'db'],
            },

            // Content Tools
            {
                category: 'Content',
                icon: 'bi-body-text',
                name: '/summarize-page',
                description: 'Summarize page content',
                shortcut: null,
                keywords: ['content', 'page', 'summary'],
            },
            {
                category: 'Content',
                icon: 'bi-clipboard-data',
                name: '/analyze-posts',
                description: 'Analyze posts in view',
                shortcut: null,
                keywords: ['posts', 'analyze', 'bulk'],
            },
            {
                category: 'Content',
                icon: 'bi-image',
                name: '/generate-alt-text',
                description: 'Generate image alt text',
                shortcut: null,
                keywords: ['image', 'alt', 'text', 'accessibility'],
            },
            {
                category: 'Content',
                icon: 'bi-search',
                name: '/extract-entities',
                description: 'Extract entities (people, places, etc)',
                shortcut: null,
                keywords: ['entity', 'nlp', 'extract'],
            },
            {
                category: 'Content',
                icon: 'bi-chat-dots',
                name: '/suggest-replies',
                description: 'Suggest replies for comments',
                shortcut: null,
                keywords: ['replies', 'suggestions', 'comments'],
            },

            // SEO & Web
            {
                category: 'Web',
                icon: 'bi-globe',
                name: '/web-search',
                description: 'Search the web',
                shortcut: null,
                keywords: ['web', 'search', 'internet'],
            },
            {
                category: 'Web',
                icon: 'bi-binoculars',
                name: '/check-seo',
                description: 'SEO analyzer for content',
                shortcut: null,
                keywords: ['seo', 'search', 'optimize'],
            },
            {
                category: 'Web',
                icon: 'bi-translate',
                name: '/batch-translate',
                description: 'Translate content to multiple languages',
                shortcut: null,
                keywords: ['translate', 'language', 'multi'],
            },

            // Maintenance
            {
                category: 'Maintenance',
                icon: 'bi-trash',
                name: '/clear-cache',
                description: 'Clear system cache',
                shortcut: null,
                keywords: ['cache', 'clear', 'reset'],
            },
            {
                category: 'Maintenance',
                icon: 'bi-person-check',
                name: '/fix-permissions',
                description: 'Fix user permissions',
                shortcut: null,
                keywords: ['permissions', 'users', 'access'],
            },
            {
                category: 'Maintenance',
                icon: 'bi-cloud-upload',
                name: '/deploy-status',
                description: 'Check deployment status',
                shortcut: null,
                keywords: ['deploy', 'status', 'release'],
            },

            // Knowledge Base
            {
                category: 'Knowledge',
                icon: 'bi-book',
                name: '/search-kb',
                description: 'Search knowledge base',
                shortcut: null,
                keywords: ['kb', 'knowledge', 'search'],
            },
        ];
    }

    /**
     * Initialize command menu
     */
    init() {
        this.setupInputListener();
        this.setupKeyboardNavigation();
    }

    /**
     * Setup input listener for slash commands
     */
    setupInputListener() {
        const input = document.getElementById(this.config.inputId);
        if (!input) return;

        input.addEventListener('input', (e) => {
            const value = e.target.value;

            // Check if user typed '/'
            if (value.endsWith('/') && !value.includes(' /')) {
                this.openMenu();
            } else if (value.includes('/')) {
                const lastSlash = value.lastIndexOf('/');
                const afterSlash = value.substring(lastSlash + 1);

                if (afterSlash.trim() !== '' && !afterSlash.includes(' ')) {
                    this.filterCommands(afterSlash);
                    this.openMenu();
                } else if (afterSlash === '') {
                    this.showAllCommands();
                    this.openMenu();
                }
            } else {
                this.closeMenu();
            }
        });

        // Close menu on enter or escape
        input.addEventListener('keydown', (e) => {
            if (!this.isOpen) return;

            if (e.key === 'Enter') {
                e.preventDefault();
                this.selectCommand(this.filteredCommands[this.selectedIndex]);
            } else if (e.key === 'Escape') {
                this.closeMenu();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.selectedIndex = (this.selectedIndex + 1) % this.filteredCommands.length;
                this.updateMenuSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.selectedIndex =
                    (this.selectedIndex - 1 + this.filteredCommands.length) % this.filteredCommands.length;
                this.updateMenuSelection();
            }
        });
    }

    /**
     * Setup keyboard navigation for menu items
     */
    setupKeyboardNavigation() {
        const menu = document.getElementById(this.config.menuId);
        if (!menu) return;

        menu.addEventListener('click', (e) => {
            const item = e.target.closest('[data-cmd]');
            if (item) {
                const cmd = item.getAttribute('data-cmd');
                const command = this.commands.find((c) => c.name === cmd);
                if (command) {
                    this.selectCommand(command);
                }
            }
        });
    }

    /**
     * Filter commands by search term
     */
    filterCommands(searchTerm) {
        const term = searchTerm.toLowerCase().trim();

        this.filteredCommands = this.commands.filter((cmd) => {
            return (
                cmd.name.includes(term) ||
                cmd.description.toLowerCase().includes(term) ||
                cmd.keywords.some((kw) => kw.includes(term))
            );
        });

        this.selectedIndex = 0;
    }

    /**
     * Show all commands
     */
    showAllCommands() {
        this.filteredCommands = [...this.commands];
        this.selectedIndex = 0;
    }

    /**
     * Open command menu
     */
    openMenu() {
        const menu = document.getElementById(this.config.menuId);
        if (!menu) return;

        this.isOpen = true;
        menu.classList.remove('brox-ai-hidden');

        this.renderMenu();
    }

    /**
     * Close command menu
     */
    closeMenu() {
        const menu = document.getElementById(this.config.menuId);
        if (!menu) return;

        this.isOpen = false;
        menu.classList.add('brox-ai-hidden');
    }

    /**
     * Render command menu
     */
    renderMenu() {
        const menu = document.getElementById(this.config.menuId);
        const list = menu?.querySelector('.brox-ai-slash-list');

        if (!list) return;

        // Group commands by category
        const grouped = this.groupByCategory(this.filteredCommands);

        list.innerHTML = '';

        Object.entries(grouped).forEach(([category, commands]) => {
            // Add category header
            const header = document.createElement('li');
            header.className = 'brox-ai-slash-category-header';
            header.style.cssText = `
        padding: 8px 12px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--assistant-muted);
        opacity: 0.7;
        border-bottom: 1px solid var(--assistant-border);
        margin-top: 4px;
      `;
            header.textContent = category;
            list.appendChild(header);

            // Add commands in category
            commands.forEach((cmd, idx) => {
                const item = document.createElement('li');
                item.className = `brox-ai-slash-item ${this.isCommandSelected(cmd) ? 'selected' : ''
                    }`;
                item.setAttribute('data-cmd', cmd.name);
                item.setAttribute('role', 'menuitem');
                item.style.cssText = this.isCommandSelected(cmd)
                    ? `background: var(--assistant-accent); color: white;`
                    : '';

                item.innerHTML = `
          <div class="brox-ai-slash-icon"><i class="bi ${cmd.icon}"></i></div>
          <div class="brox-ai-slash-info">
            <strong>${cmd.name}</strong>
            <small>${cmd.description}</small>
          </div>
        `;

                item.addEventListener('click', () => this.selectCommand(cmd));
                item.addEventListener('mouseenter', () => {
                    this.selectedIndex = this.filteredCommands.indexOf(cmd);
                    this.updateMenuSelection();
                });

                list.appendChild(item);
            });
        });
    }

    /**
     * Group commands by category
     */
    groupByCategory(commands) {
        return commands.reduce((grouped, cmd) => {
            if (!grouped[cmd.category]) {
                grouped[cmd.category] = [];
            }
            grouped[cmd.category].push(cmd);
            return grouped;
        }, {});
    }

    /**
     * Check if command is selected
     */
    isCommandSelected(cmd) {
        return this.filteredCommands[this.selectedIndex]?.name === cmd.name;
    }

    /**
     * Update menu selection highlight
     */
    updateMenuSelection() {
        const items = document.querySelectorAll('.brox-ai-slash-item');
        items.forEach((item, idx) => {
            if (idx === this.selectedIndex) {
                item.classList.add('selected');
                item.style.background = 'var(--assistant-accent)';
                item.style.color = 'white';
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
                item.style.background = '';
                item.style.color = '';
            }
        });
    }

    /**
     * Select a command
     */
    selectCommand(command) {
        const input = document.getElementById(this.config.inputId);
        if (!input) return;

        // Replace the current word with the command
        const value = input.value;
        const lastSlash = value.lastIndexOf('/');
        const before = value.substring(0, lastSlash);

        input.value = before + command.name + ' ';
        input.focus();

        // Trigger input event for listeners
        input.dispatchEvent(new Event('input', { bubbles: true }));

        this.closeMenu();
    }
}

export default CommandMenu;
