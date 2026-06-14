/**
 * Rich Text Editor - Advanced Keyboard Shortcuts Helper
 * Supports Cmd/Ctrl shortcuts with clean extensibility
 */
(function () {

    function installKeyboardHelpers(RTE) {

        RTE.prototype.setupKeyboardShortcuts = function () {
            if (!this.editor) return;

            const isMac = /MAC/i.test(navigator.platform);

            const isCommandKey = (e) => isMac ? e.metaKey : e.ctrlKey;

            const shouldIgnore = (e) => {
                const tag = e.target.tagName;
                return (
                    e.target.isContentEditable === false &&
                    (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT')
                );
            };

            // 🔑 Central shortcut map
            const shortcuts = {
                b: () => this.executeCommand('bold'),
                i: () => this.executeCommand('italic'),
                u: () => this.executeCommand('underline'),
                z: (e) => e.shiftKey ? this.redo() : this.undo(),
                y: () => this.redo()
            };

            this.editor.addEventListener('keydown', (e) => {
                if (!isCommandKey(e)) return;
                if (shouldIgnore(e)) return;

                const key = e.key.toLowerCase();
                const handler = shortcuts[key];

                if (handler) {
                    e.preventDefault();
                    e.stopPropagation();
                    handler(e);
                }
            });
        };

        if (window.RTE_debugLog) window.RTE_debugLog('keyboard', 'Advanced keyboard shortcuts installed');
    }

    if (typeof window !== 'undefined') {
        window.installKeyboardHelpers = installKeyboardHelpers;
    }

})();
