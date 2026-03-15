const DEFAULT_OPTIONS = {
    allowCreate: true,
    searchMode: 'client',
    sourceEndpoint: '/api/tags/list-json',
    createEndpoint: '/api/tags/create',
    maxResults: 50
};

const tagCache = {
    byId: new Map(),
    byNameKey: new Map(),
    orderedIds: [],
    loadingPromise: null
};

const comboboxInstances = new Map();

function resolveSelect(selectOrSelector) {
    if (!selectOrSelector) return null;
    if (typeof selectOrSelector === 'string') {
        return document.querySelector(selectOrSelector);
    }
    if (selectOrSelector instanceof HTMLSelectElement) {
        return selectOrSelector;
    }
    return null;
}

function normalizeOptions(options = {}) {
    return {
        ...DEFAULT_OPTIONS,
        ...(options || {})
    };
}

function normalizeId(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const parsed = Number.parseInt(raw, 10);
    if (!Number.isFinite(parsed) || parsed <= 0) return '';
    return String(parsed);
}

function normalizeName(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ');
}

function normalizeNameKey(value) {
    return normalizeName(value).toLowerCase();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function toSelectedIdSet(select, selectedIds = []) {
    const fromInput = Array.isArray(selectedIds) ? selectedIds : [];
    const selectedIdSet = new Set(
        fromInput
            .map((id) => normalizeId(id))
            .filter(Boolean)
    );

    const selectedOptions = Array.from(select.options).filter((opt) => opt.selected);
    selectedOptions.forEach((opt) => {
        const normalizedId = normalizeId(opt.value);
        if (normalizedId) {
            selectedIdSet.add(normalizedId);
            return;
        }

        const byName = tagCache.byNameKey.get(normalizeNameKey(opt.textContent || opt.value));
        if (byName?.id) {
            selectedIdSet.add(String(byName.id));
        }
    });

    return selectedIdSet;
}

function mergeTags(rows = []) {
    const merged = [];
    rows.forEach((row) => {
        const id = normalizeId(row?.id);
        const name = normalizeName(row?.name || row?.text || row?.label || '');
        if (!id || !name) return;
        merged.push({ id, name });
    });

    merged.sort((a, b) => a.name.localeCompare(b.name));

    tagCache.byId.clear();
    tagCache.byNameKey.clear();
    tagCache.orderedIds = [];

    merged.forEach((tag) => {
        tagCache.byId.set(tag.id, tag);
        tagCache.byNameKey.set(normalizeNameKey(tag.name), tag);
        tagCache.orderedIds.push(tag.id);
    });
}

async function ensureTagsLoaded(options = {}) {
    const settings = normalizeOptions(options);
    if (tagCache.orderedIds.length > 0) {
        return tagCache.orderedIds.map((id) => tagCache.byId.get(id)).filter(Boolean);
    }

    if (tagCache.loadingPromise) {
        return tagCache.loadingPromise;
    }

    tagCache.loadingPromise = fetch(settings.sourceEndpoint, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`Tag list request failed (${response.status})`);
            }
            return response.json();
        })
        .then((payload) => {
            const rows = Array.isArray(payload) ? payload : [];
            mergeTags(rows);
            return tagCache.orderedIds.map((id) => tagCache.byId.get(id)).filter(Boolean);
        })
        .finally(() => {
            tagCache.loadingPromise = null;
        });

    return tagCache.loadingPromise;
}

function appendOption(select, tag, selected = false) {
    const option = new Option(tag.name, tag.id, selected, selected);
    select.appendChild(option);
}

function syncSelectOptions(select, selectedIds = []) {
    const selectedSet = toSelectedIdSet(select, selectedIds);
    const existingSelectedByName = Array.from(select.options)
        .filter((opt) => opt.selected && !normalizeId(opt.value))
        .map((opt) => normalizeName(opt.textContent || opt.value))
        .filter(Boolean);

    select.innerHTML = '';

    tagCache.orderedIds.forEach((id) => {
        const tag = tagCache.byId.get(id);
        if (!tag) return;
        appendOption(select, tag, selectedSet.has(tag.id));
    });

    existingSelectedByName.forEach((name) => {
        const knownTag = tagCache.byNameKey.get(normalizeNameKey(name));
        if (knownTag) {
            const option = Array.from(select.options).find((opt) => opt.value === knownTag.id);
            if (option) option.selected = true;
            return;
        }
        const fallbackValue = normalizeId(name) || name;
        const option = new Option(name, fallbackValue, true, true);
        select.appendChild(option);
    });

    select.dispatchEvent(new Event('change', { bubbles: true }));
}

function getAllTags() {
    return tagCache.orderedIds
        .map((id) => tagCache.byId.get(id))
        .filter(Boolean);
}

function getSelectedTagIds(select) {
    return Array.from(select.options)
        .filter((opt) => opt.selected)
        .map((opt) => normalizeId(opt.value))
        .filter(Boolean);
}

function getSelectedTags(select) {
    const ids = new Set(getSelectedTagIds(select));
    const selected = [];
    ids.forEach((id) => {
        const row = tagCache.byId.get(id);
        if (row) selected.push(row);
    });
    return selected;
}

function upsertTag(tagInput) {
    const id = normalizeId(tagInput?.id);
    const name = normalizeName(tagInput?.name || tagInput?.text || '');
    if (!id || !name) return null;

    const tag = { id, name };
    tagCache.byId.set(id, tag);
    tagCache.byNameKey.set(normalizeNameKey(name), tag);

    if (!tagCache.orderedIds.includes(id)) {
        tagCache.orderedIds.push(id);
        tagCache.orderedIds.sort((a, b) => {
            const tagA = tagCache.byId.get(a);
            const tagB = tagCache.byId.get(b);
            return String(tagA?.name || '').localeCompare(String(tagB?.name || ''));
        });
    }

    return tag;
}

class TagCombobox {
    constructor(select, options = {}) {
        this.select = select;
        this.options = normalizeOptions(options);
        this.uid = `tag-combobox-${Math.random().toString(36).slice(2, 8)}`;
        this.root = null;
        this.control = null;
        this.input = null;
        this.chipsWrap = null;
        this.dropdown = null;
        this.emptyState = null;
        this.activeIndex = -1;
        this.filtered = [];
        this.hasOpenedOnce = false;
        this.boundOnDocumentClick = this.onDocumentClick.bind(this);
        this.boundOnSelectChange = this.refresh.bind(this);
    }

    init() {
        if (this.root) return;

        this.select.classList.add('tag-combobox-source');
        this.select.setAttribute('aria-hidden', 'true');

        this.root = document.createElement('div');
        this.root.className = 'tag-combobox';

        this.control = document.createElement('div');
        this.control.className = 'tag-combobox-control';
        this.control.addEventListener('click', () => {
            this.input?.focus();
            this.open();
        });

        this.chipsWrap = document.createElement('div');
        this.chipsWrap.className = 'tag-combobox-chips';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'tag-combobox-input';
        this.input.placeholder = 'Search or create tags...';
        this.input.setAttribute('autocomplete', 'off');
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-haspopup', 'listbox');
        this.input.setAttribute('aria-label', 'Search tags');

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'tag-combobox-dropdown';
        this.dropdown.id = `${this.uid}-listbox`;
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.hidden = true;
        this.input.setAttribute('aria-controls', this.dropdown.id);

        this.emptyState = document.createElement('div');
        this.emptyState.className = 'tag-combobox-empty';
        this.emptyState.textContent = 'No matching tags found';
        this.emptyState.hidden = true;
        this.dropdown.appendChild(this.emptyState);

        this.control.appendChild(this.chipsWrap);
        this.control.appendChild(this.input);
        this.root.appendChild(this.control);
        this.root.appendChild(this.dropdown);
        this.select.insertAdjacentElement('afterend', this.root);

        this.select.addEventListener('change', this.boundOnSelectChange);
        this.input.addEventListener('focus', () => this.open());
        this.input.addEventListener('input', () => this.renderResults());
        this.input.addEventListener('keydown', (event) => this.onKeyDown(event));

        document.addEventListener('click', this.boundOnDocumentClick);

        this.refresh();
    }

    destroy() {
        this.select.classList.remove('tag-combobox-source');
        this.select.removeAttribute('aria-hidden');
        this.select.removeEventListener('change', this.boundOnSelectChange);
        document.removeEventListener('click', this.boundOnDocumentClick);
        this.root?.remove();
        this.root = null;
    }

    onDocumentClick(event) {
        if (!this.root) return;
        if (this.root.contains(event.target) || this.select.contains(event.target)) return;
        this.close();
    }

    onKeyDown(event) {
        if (event.key === 'Escape') {
            this.close();
            return;
        }

        if (event.key === 'Backspace' && !this.input.value.trim()) {
            this.removeLastSelected();
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!this.isOpen()) this.open();
            this.moveActive(1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (!this.isOpen()) this.open();
            this.moveActive(-1);
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            this.applyActiveOrCreate();
        }
    }

    refresh() {
        this.renderChips();
        if (this.hasOpenedOnce || this.isOpen()) {
            this.renderResults();
        }
    }

    renderChips() {
        if (!this.chipsWrap) return;
        const selected = getSelectedTags(this.select);
        this.chipsWrap.innerHTML = '';

        selected.forEach((tag) => {
            const chip = document.createElement('span');
            chip.className = 'tag-combobox-chip';
            chip.innerHTML = `
                <span>${escapeHtml(tag.name)}</span>
                <button type="button" class="tag-combobox-chip-remove" aria-label="Remove tag ${escapeHtml(tag.name)}">&times;</button>
            `;

            chip.querySelector('.tag-combobox-chip-remove')?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggleSelection(tag.id, false);
            });

            this.chipsWrap.appendChild(chip);
        });
    }

    renderResults() {
        if (!this.dropdown || !this.input) return;

        const query = normalizeName(this.input.value);
        const selectedIds = new Set(getSelectedTagIds(this.select));
        const allTags = getAllTags();

        const filtered = allTags
            .filter((tag) => !selectedIds.has(tag.id))
            .filter((tag) => {
                if (!query) return true;
                return normalizeNameKey(tag.name).includes(normalizeNameKey(query));
            })
            .slice(0, Math.max(1, Number(this.options.maxResults) || 50));

        this.filtered = filtered;
        this.activeIndex = filtered.length ? 0 : -1;

        this.dropdown.querySelectorAll('.tag-combobox-option, .tag-combobox-create').forEach((el) => el.remove());

        filtered.forEach((tag, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'tag-combobox-option';
            if (index === this.activeIndex) option.classList.add('is-active');
            option.id = `${this.uid}-opt-${tag.id}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', index === this.activeIndex ? 'true' : 'false');
            option.dataset.id = tag.id;
            option.dataset.name = tag.name;
            option.textContent = tag.name;
            option.addEventListener('click', () => {
                this.toggleSelection(tag.id, true);
                this.input.value = '';
                this.renderResults();
                this.input.focus();
            });
            this.dropdown.appendChild(option);
        });

        const exactMatch = query ? tagCache.byNameKey.get(normalizeNameKey(query)) : null;
        const canCreate = Boolean(this.options.allowCreate) && Boolean(query) && !exactMatch;

        if (canCreate) {
            const createBtn = document.createElement('button');
            createBtn.type = 'button';
            createBtn.className = 'tag-combobox-create';
            createBtn.innerHTML = `Create tag: <strong>${escapeHtml(query)}</strong>`;
            createBtn.addEventListener('click', () => {
                this.createAndSelect(query);
            });
            this.dropdown.appendChild(createBtn);
        }

        const hasRenderableResults = filtered.length > 0 || canCreate;
        this.emptyState.hidden = hasRenderableResults;
        this.updateActiveDescendant();
    }

    updateActiveDescendant() {
        if (!this.input || !this.dropdown) return;
        const options = this.dropdown.querySelectorAll('.tag-combobox-option');
        options.forEach((option, index) => {
            option.classList.toggle('is-active', index === this.activeIndex);
            option.setAttribute('aria-selected', index === this.activeIndex ? 'true' : 'false');
        });

        const active = options[this.activeIndex] || null;
        if (active) {
            this.input.setAttribute('aria-activedescendant', active.id);
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    moveActive(delta) {
        const options = this.dropdown?.querySelectorAll('.tag-combobox-option') || [];
        if (!options.length) {
            this.activeIndex = -1;
            this.updateActiveDescendant();
            return;
        }

        if (this.activeIndex < 0) {
            this.activeIndex = 0;
        } else {
            this.activeIndex = (this.activeIndex + delta + options.length) % options.length;
        }

        this.updateActiveDescendant();
        const activeEl = options[this.activeIndex];
        activeEl?.scrollIntoView({ block: 'nearest' });
    }

    applyActiveOrCreate() {
        if (!this.isOpen()) this.open();

        const options = this.dropdown?.querySelectorAll('.tag-combobox-option') || [];
        const activeOption = options[this.activeIndex];
        if (activeOption?.dataset?.id) {
            this.toggleSelection(activeOption.dataset.id, true);
            this.input.value = '';
            this.renderResults();
            return;
        }

        const query = normalizeName(this.input?.value || '');
        if (!query || !this.options.allowCreate) return;

        const existing = tagCache.byNameKey.get(normalizeNameKey(query));
        if (existing?.id) {
            this.toggleSelection(existing.id, true);
            this.input.value = '';
            this.renderResults();
            return;
        }

        this.createAndSelect(query);
    }

    toggleSelection(id, shouldSelect) {
        const normalized = normalizeId(id);
        if (!normalized) return;

        const option = Array.from(this.select.options).find((opt) => normalizeId(opt.value) === normalized);
        if (option) {
            option.selected = Boolean(shouldSelect);
        } else if (shouldSelect) {
            const tag = tagCache.byId.get(normalized);
            if (tag) {
                appendOption(this.select, tag, true);
            }
        }

        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    removeLastSelected() {
        const selected = getSelectedTagIds(this.select);
        const last = selected[selected.length - 1];
        if (!last) return;
        this.toggleSelection(last, false);
        if (this.isOpen()) this.renderResults();
    }

    async createAndSelect(rawName) {
        const name = normalizeName(rawName);
        if (!name) return;

        try {
            const tag = await createTag(name, this.options);
            if (!tag?.id) return;
            syncSelectOptions(this.select, [...getSelectedTagIds(this.select), tag.id]);
            this.toggleSelection(tag.id, true);
            this.input.value = '';
            this.renderResults();
            this.open();
            window.showMessage?.('Tag created successfully', 'success', 3000);
        } catch (error) {
            window.showMessage?.(error?.message || 'Failed to create tag', 'danger', 5000);
        }
    }

    isOpen() {
        return Boolean(this.dropdown && this.dropdown.hidden === false);
    }

    open() {
        if (!this.dropdown || !this.input) return;
        this.hasOpenedOnce = true;
        this.dropdown.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
        this.renderResults();
    }

    close() {
        if (!this.dropdown || !this.input) return;
        this.dropdown.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.activeIndex = -1;
        this.updateActiveDescendant();
    }
}

export async function loadTagOptions(selectOrSelector, selectedIds = [], options = {}) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return [];

    const settings = normalizeOptions(options);
    const tags = await ensureTagsLoaded(settings);
    syncSelectOptions(select, selectedIds);

    const instance = comboboxInstances.get(select);
    if (instance) instance.refresh();

    return tags;
}

export async function createTag(name, options = {}) {
    const settings = normalizeOptions(options);
    const normalizedName = normalizeName(name);
    if (!normalizedName) {
        throw new Error('Tag name is required');
    }

    await ensureTagsLoaded(settings);
    const existing = tagCache.byNameKey.get(normalizeNameKey(normalizedName));
    if (existing?.id) {
        return existing;
    }

    const response = await fetch(settings.createEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ name: normalizedName }).toString()
    });

    if (!response.ok) {
        throw new Error(`Tag create request failed (${response.status})`);
    }

    const payload = await response.json();
    if (!payload?.success) {
        throw new Error(payload?.error || payload?.message || 'Failed to create tag');
    }

    const tag = upsertTag({ id: payload.id, name: payload.name || normalizedName });
    if (!tag) {
        throw new Error('Invalid tag response from server');
    }

    comboboxInstances.forEach((instance) => {
        syncSelectOptions(instance.select, getSelectedTagIds(instance.select));
    });

    return tag;
}

export async function createTagAndSelect(selectOrSelector, name, options = {}) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return null;

    const tag = await createTag(name, options);
    if (!tag?.id) return null;

    const selected = getSelectedTagIds(select);
    selected.push(tag.id);
    syncSelectOptions(select, selected);

    return tag;
}

export function initTagCombobox(selectOrSelector, options = {}) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return null;

    const settings = normalizeOptions(options);
    let instance = comboboxInstances.get(select);
    if (!instance) {
        instance = new TagCombobox(select, settings);
        comboboxInstances.set(select, instance);
        instance.init();
    } else {
        instance.options = settings;
    }

    ensureTagsLoaded(settings)
        .then(() => {
            syncSelectOptions(select, getSelectedTagIds(select));
            instance.refresh();
        })
        .catch((error) => {
            console.error('[TagCombobox] Failed to load tags', error);
            window.showMessage?.('Failed to load tags', 'danger', 5000);
        });

    return instance;
}

export function destroyTagCombobox(selectOrSelector) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return;
    const instance = comboboxInstances.get(select);
    if (!instance) return;
    instance.destroy();
    comboboxInstances.delete(select);
}
