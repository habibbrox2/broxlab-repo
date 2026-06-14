/**
 * Command Menu Module
 * Handles slash command overlay menu for quick actions
 * Features: keyboard navigation, fuzzy filtering, click-to-select
 */
export default class CommandMenu {
  constructor(options = {}) {
    this.visible = false;
    this.menuEl = options.menuEl || null;
    this.inputEl = options.inputEl || null;
    this.onSelect = options.onSelect || null;

    this.commands = [
      { name: 'help', description: 'Show help menu', keywords: ['help', 'commands'] },
      { name: 'clear', description: 'Clear chat history', keywords: ['clear', 'reset', 'delete'] },
      { name: 'settings', description: 'Open settings', keywords: ['settings', 'preferences', 'config'] },
      { name: 'export', description: 'Export conversation', keywords: ['export', 'download', 'save'] },
      { name: 'theme', description: 'Toggle theme (dark/light)', keywords: ['theme', 'dark', 'light', 'mode'] },
    ];

    this.selectedIndex = -1;
    this.bindEvents();
  }

  bindEvents() {
    if (this.inputEl) {
      this.inputEl.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        if (val.startsWith('/')) {
          const query = val.slice(1).toLowerCase();
          this.filterCommands(query);
          if (this.getFilteredCommands(query).length > 0) {
            this.show();
          } else {
            this.hide();
          }
        } else {
          this.hide();
        }
      });

      this.inputEl.addEventListener('keydown', (e) => {
        if (!this.visible) return;

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          this.navigate(1);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          this.navigate(-1);
        } else if (e.key === 'Enter' || e.key === 'Tab') {
          const selected = this.getSelectedCommand();
          if (selected) {
            e.preventDefault();
            this.selectCommand(selected);
          }
        } else if (e.key === 'Escape') {
          this.hide();
        }
      });
    }
  }

  filterCommands(query) {
    const filtered = this.getFilteredCommands(query);

    if (this.menuEl) {
      if (!filtered.length) {
        this.menuEl.innerHTML = '<div class="px-3 py-2 text-muted small">No matching commands</div>';
        return;
      }
      this.menuEl.innerHTML = filtered
        .map(
          (cmd, i) =>
            `<div class="command-menu-item${i === 0 ? ' active' : ''}" data-command="${cmd.name}">
              <span class="command-name">/${cmd.name}</span>
              <span class="command-desc text-muted small">${cmd.description}</span>
            </div>`
        )
        .join('');
      this.selectedIndex = filtered.length > 0 ? 0 : -1;
    }
  }

  getFilteredCommands(query) {
    if (!query) return this.commands;
    const q = query.toLowerCase();
    return this.commands.filter(
      (cmd) =>
        cmd.name.toLowerCase().includes(q) ||
        cmd.description.toLowerCase().includes(q) ||
        (cmd.keywords || []).some((kw) => kw.includes(q))
    );
  }

  navigate(direction) {
    const items = this.menuEl?.querySelectorAll('.command-menu-item');
    if (!items?.length) return;

    items.forEach((el) => el.classList.remove('active'));

    this.selectedIndex = (this.selectedIndex + direction + items.length) % items.length;
    items[this.selectedIndex]?.classList.add('active');
    items[this.selectedIndex]?.scrollIntoView({ block: 'nearest' });
  }

  getSelectedCommand() {
    if (this.selectedIndex < 0) return null;
    const filtered = this.getFilteredCommands(
      (this.inputEl?.value || '').slice(1).toLowerCase()
    );
    return filtered[this.selectedIndex] || null;
  }

  selectCommand(cmd) {
    if (this.onSelect) {
      this.onSelect(cmd);
    } else if (this.inputEl) {
      this.inputEl.value = `/${cmd.name} `;
      this.inputEl.focus();
    }
    this.hide();
  }

  show() {
    this.visible = true;
    if (this.menuEl) {
      this.menuEl.classList.remove('brox-ai-hidden', 'd-none');
    }
  }

  hide() {
    this.visible = false;
    this.selectedIndex = -1;
    if (this.menuEl) {
      this.menuEl.classList.add('brox-ai-hidden', 'd-none');
    }
  }

  toggle() {
    if (this.visible) {
      this.hide();
    } else {
      this.show();
    }
  }

  getCommands() {
    return [...this.commands];
  }

  addCommand(name, description, handler) {
    this.commands.push({ name, description, handler, keywords: [name] });
  }

  removeCommand(name) {
    this.commands = this.commands.filter((cmd) => cmd.name !== name);
  }
}
