/**
 * Theme Manager - Advanced Dark/Light Mode System
 * Handles theme persistence, system preference detection, and smooth transitions
 */

class ThemeManager {
    constructor(options = {}) {
        this.storageKey = options.storageKey || 'app-theme';
        this.defaultTheme = options.defaultTheme || 'light';
        this.transitionDuration = options.transitionDuration || 300;
        this.rootElement = options.rootElement || document.documentElement;

        this.themes = {
            light: 'light',
            dark: 'dark'
        };

        this.init();
    }

    /**
     * Initialize theme manager
     */
    init() {
        // Load saved theme or use system preference
        const savedTheme = this.getSavedTheme();
        const themeToUse = savedTheme || this.getSystemPreference() || this.defaultTheme;

        // Apply theme without transition on initial load
        this.setTheme(themeToUse, false);

        // Listen for system theme changes
        this.watchSystemPreference();

        // Setup keyboard shortcut (Ctrl/Cmd + Shift + T)
        this.setupKeyboardShortcut();
    }

    /**
     * Get saved theme from storage
     */
    getSavedTheme() {
        try {
            return localStorage.getItem(this.storageKey);
        } catch (error) {
            return null;
        }
    }

    /**
     * Get system preference
     */
    getSystemPreference() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return this.themes.dark;
        }
        return this.themes.light;
    }

    /**
     * Watch for system theme changes
     */
    watchSystemPreference() {
        if (!window.matchMedia) return;

        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

        darkModeQuery.addEventListener('change', (e) => {
            // Only apply if user hasn't manually selected a theme
            if (!this.getSavedTheme()) {
                const newTheme = e.matches ? this.themes.dark : this.themes.light;
                this.setTheme(newTheme);
            }
        });
    }

    /**
     * Set theme
     */
    setTheme(theme, withTransition = true) {
        if (!Object.values(this.themes).includes(theme)) {
            console.warn(`Invalid theme: ${theme}`);
            return;
        }

        // Add transition class if requested
        if (withTransition) {
            this.rootElement.classList.add('theme-transition');
            setTimeout(() => {
                this.rootElement.classList.remove('theme-transition');
            }, this.transitionDuration);
        }

        // Apply theme
        this.rootElement.setAttribute('data-theme', theme);
        this.rootElement.style.colorScheme = theme;
        try {
            localStorage.setItem(this.storageKey, theme);
        } catch (error) {
            // Storage may be blocked in private contexts; theme still applies for current session.
        }

        // Update favicon
        this.updateFavicon(theme);

        // Dispatch custom event for other components
        const event = new CustomEvent('themechange', {
            detail: { theme, timestamp: Date.now() }
        });
        window.dispatchEvent(event);
    }

    /**
     * Toggle between themes
     */
    toggleTheme(withTransition = true) {
        const currentTheme = this.getCurrentTheme();
        const newTheme = currentTheme === this.themes.light ? this.themes.dark : this.themes.light;
        this.setTheme(newTheme, withTransition);
        return newTheme;
    }

    /**
     * Get current theme
     */
    getCurrentTheme() {
        return this.rootElement.getAttribute('data-theme') || this.defaultTheme;
    }

    /**
     * Update favicon based on theme
     */
    updateFavicon(theme) {
        const favicon = document.querySelector('link[rel="icon"]');
        if (!favicon) return;

        // You can use different favicons for different themes
        // For now, we'll keep the same favicon
        // Example: favicon.href = theme === 'dark' ? '/favicon-dark.ico' : '/favicon-light.ico';
    }

    /**
     * Setup keyboard shortcut for theme toggle
     */
    setupKeyboardShortcut() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + T
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }
}

/**
 * Initialize Theme Manager on DOM Ready
 */
document.addEventListener('DOMContentLoaded', () => {
    const globalThemeConfig = window.__APP_JS_CONFIG?.ui?.theme || window.__APP_CONFIG?.ui?.theme || {};

    // Create global instance
    window.themeManager = new ThemeManager({
        storageKey: globalThemeConfig.storageKey || 'broxbhai-theme',
        defaultTheme: globalThemeConfig.defaultTheme || 'light',
        transitionDuration: Number(globalThemeConfig.transitionDuration || 300)
    });

    // Setup theme toggle button
    const themeToggleBtn = document.getElementById('broxThemeToggle') || document.getElementById('themeToggle');
    if (themeToggleBtn) {
        const updateButtonState = () => {
            const currentTheme = window.themeManager.getCurrentTheme();
            const isDark = currentTheme === 'dark';
            const icon = themeToggleBtn.querySelector('i');

            themeToggleBtn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            themeToggleBtn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            themeToggleBtn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');

            if (icon) {
                icon.classList.remove('bi-circle-half', 'bi-moon-stars-fill', 'bi-sun-fill');
                icon.classList.add(isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill');
            }
        };

        themeToggleBtn.addEventListener('click', () => {
            window.themeManager.toggleTheme(true);
            updateButtonState();
        });

        updateButtonState();
        window.addEventListener('themechange', updateButtonState);
    }

    // Add CSS transition class
    const style = document.createElement('style');
    style.textContent = `
        .theme-transition,
        .theme-transition *,
        .theme-transition *:before,
        .theme-transition *:after {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease !important;
        }
    `;
    document.head.appendChild(style);
});

// ESM export for use in other modules
export default ThemeManager;
