/**
 * UI Enhancements Module
 * Handles dark mode, theme management, and UI utilities
 */
export default class UIEnhancements {
    constructor() {
        this.darkMode = localStorage.getItem('brox.admin.darkMode') === 'true';
    }

    applyInitialTheme() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('brox.admin.darkMode', this.darkMode ? 'true' : 'false');
        this.applyInitialTheme();
        return this.darkMode;
    }

    setTheme(theme) {
        this.darkMode = theme === 'dark';
        localStorage.setItem('brox.admin.darkMode', this.darkMode ? 'true' : 'false');
        this.applyInitialTheme();
    }

    addMessageTimestamp(msgElement, date) {
        const timeEl = document.createElement('span');
        timeEl.className = 'msg-time';
        timeEl.textContent = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

        const msgContent = msgElement.querySelector('.msg-content');
        if (msgContent) {
            msgContent.appendChild(timeEl);
        }
    }
}
