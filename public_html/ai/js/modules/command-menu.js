/**
 * Command Menu Module
 * Handles slash command overlay menu for quick actions
 */
export default class CommandMenu {
    constructor() {
        this.visible = false;
        this.commands = [
            { name: 'help', description: 'Show help menu' },
            { name: 'clear', description: 'Clear chat history' },
            { name: 'settings', description: 'Open settings' },
            { name: 'export', description: 'Export conversation' },
        ];
    }

    show() {
        this.visible = true;
    }

    hide() {
        this.visible = false;
    }

    toggle() {
        this.visible = !this.visible;
    }

    getCommands() {
        return this.commands;
    }

    addCommand(name, description, handler) {
        this.commands.push({ name, description, handler });
    }
}
