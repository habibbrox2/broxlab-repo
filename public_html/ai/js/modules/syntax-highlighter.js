/**
 * Syntax Highlighter for Code Blocks
 * Path: /public_html/ai/js/modules/syntax-highlighter.js
 * 
 * Provides basic syntax highlighting for common languages
 * without external dependencies
 */

export class SyntaxHighlighter {
    constructor() {
        this.keywords = {
            php: ['class', 'function', 'if', 'else', 'return', 'new', 'public', 'private', 'protected', 'static', 'namespace', 'use', 'require', 'include', 'echo', 'print', 'array', 'foreach', 'while', 'for', 'switch', 'case', 'break', 'continue'],
            javascript: ['const', 'let', 'var', 'function', 'class', 'if', 'else', 'return', 'new', 'this', 'async', 'await', 'try', 'catch', 'throw', 'import', 'export', 'default', 'yield', 'for', 'while', 'switch', 'case'],
            python: ['def', 'class', 'if', 'else', 'elif', 'return', 'import', 'from', 'as', 'try', 'except', 'finally', 'with', 'for', 'while', 'break', 'continue', 'pass', 'raise', 'lambda', 'yield'],
            sql: ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'WHERE', 'FROM', 'JOIN', 'ON', 'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'LIKE', 'BETWEEN', 'ORDER', 'BY', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET'],
            html: ['<!DOCTYPE', 'html', 'head', 'body', 'div', 'p', 'span', 'a', 'img', 'script', 'link', 'meta', 'title', 'class', 'id', 'href', 'src'],
        };
    }

    /**
     * Highlight code block content
     */
    highlight(code, language = 'auto') {
        if (language === 'auto') {
            language = this.detectLanguage(code);
        }

        language = language.toLowerCase();

        // Basic HTML/XML highlighting
        if (language === 'html' || language === 'xml') {
            return this.highlightHtml(code);
        }

        // JSON highlighting
        if (language === 'json') {
            return this.highlightJson(code);
        }

        // Generic code highlighting
        return this.highlightGeneric(code, language);
    }

    /**
     * Detect language from code
     */
    detectLanguage(code) {
        if (code.includes('<?php') || code.includes('namespace') || code.includes('public function')) {
            return 'php';
        }
        if (code.includes('import ') || code.includes('from ')) {
            return 'python';
        }
        if (code.includes('function ') || code.includes('const ') || code.includes('=>')) {
            return 'javascript';
        }
        if (code.includes('SELECT') || code.includes('INSERT') || code.includes('UPDATE')) {
            return 'sql';
        }
        if (code.includes('<!DOCTYPE') || code.includes('<html')) {
            return 'html';
        }
        if (code.trim().startsWith('{') && code.trim().endsWith('}')) {
            return 'json';
        }
        return 'text';
    }

    /**
     * Highlight HTML/XML
     */
    highlightHtml(code) {
        return code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            // Tags
            .replace(/(&lt;\/?\w+[^&]*?&gt;)/g, '<span style="color: #0ea5e9;">$1</span>')
            // Attributes
            .replace(/(\w+)=/g, '<span style="color: #22c55e;">$1</span>=')
            // Attribute values
            .replace(/="([^"]*?)"/g, '="<span style="color: #f59e0b;">$1</span>"')
            // Comments
            .replace(/(&lt;!--[\s\S]*?--&gt;)/g, '<span style="color: #64748b;">$1</span>');
    }

    /**
     * Highlight JSON
     */
    highlightJson(code) {
        return code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            // Keys
            .replace(/"([^"]+)":/g, '<span style="color: #22c55e;">"$1"</span>:')
            // String values
            .replace(/: "([^"]*)"/g, ': "<span style="color: #f59e0b;">$1</span>"')
            // Numbers
            .replace(/: (\d+)/g, ': <span style="color: #0ea5e9;">$1</span>')
            // Booleans
            .replace(/: (true|false|null)/g, ': <span style="color: #a78bfa;">$1</span>');
    }

    /**
     * Generic highlighting for any language
     */
    highlightGeneric(code, language) {
        let highlighted = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Get keywords for language
        const keywords = this.keywords[language] || [];

        // Highlight keywords
        keywords.forEach(keyword => {
            const regex = new RegExp(`\\b${keyword}\\b`, 'gi');
            highlighted = highlighted.replace(
                regex,
                `<span style="color: #0ea5e9; font-weight: 600;">$&</span>`
            );
        });

        // Highlight strings
        highlighted = highlighted
            .replace(/'([^']*)'/g, `<span style="color: #f59e0b;">'$1'</span>`)
            .replace(/"([^"]*)"/g, `<span style="color: #f59e0b;">"$1"</span>`);

        // Highlight numbers
        highlighted = highlighted.replace(/\b(\d+)\b/g, `<span style="color: #22c55e;">$1</span>`);

        // Highlight comments
        highlighted = highlighted
            .replace(/\/\/(.*)$/gm, `<span style="color: #64748b; font-style: italic;">\/\/$1</span>`)
            .replace(/#(.*)$/gm, `<span style="color: #64748b; font-style: italic;">#$1</span>`)
            .replace(/\/\*[\s\S]*?\*\//g, `<span style="color: #64748b; font-style: italic;">$&</span>`);

        return highlighted;
    }

    /**
     * Create enhanced code block with syntax highlighting
     */
    createEnhancedCodeBlock(code, language = 'auto') {
        const container = document.createElement('div');
        container.className = 'code-block-container';

        const header = document.createElement('div');
        header.className = 'code-block-header';

        const lang = language === 'auto' ? this.detectLanguage(code) : language;
        const label = document.createElement('span');
        label.className = 'code-block-header-label';
        label.textContent = lang.toUpperCase();

        const copyBtn = document.createElement('button');
        copyBtn.className = 'code-copy-btn';
        copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
        copyBtn.addEventListener('click', () => this.copyToClipboard(code, copyBtn));

        header.appendChild(label);
        header.appendChild(copyBtn);

        const preEl = document.createElement('pre');
        const codeEl = document.createElement('code');
        codeEl.innerHTML = this.highlight(code, lang);

        preEl.appendChild(codeEl);

        container.appendChild(header);
        container.appendChild(preEl);

        return container;
    }

    /**
     * Copy code to clipboard
     */
    async copyToClipboard(code, button) {
        try {
            await navigator.clipboard.writeText(code);

            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check2"></i> Copied!';
            button.classList.add('copied');

            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.classList.remove('copied');
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }

    /**
     * Process code blocks in a container
     */
    processCodeBlocks(container) {
        const codeBlocks = container.querySelectorAll('pre, code');

        codeBlocks.forEach((block) => {
            if (block.classList.contains('highlighted')) return;

            const code = block.textContent;
            const language = block.getAttribute('data-language') || 'auto';
            const highlighted = this.highlight(code, language);

            block.innerHTML = highlighted;
            block.classList.add('highlighted');
        });
    }
}

export default SyntaxHighlighter;
