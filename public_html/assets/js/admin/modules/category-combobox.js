const DEFAULT_OPTIONS = {
    allowCreate: true,
    searchMode: 'client',
    sourceEndpoint: '/api/categories/list-json',
    createEndpoint: '/api/categories/create',
    maxResults: 50
};

const categoryCache = {
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

        const byName = categoryCache.byNameKey.get(normalizeNameKey(opt.textContent || opt.value));
        if (byName?.id) {
            selectedIdSet.add(String(byName.id));
        }
    });

    return selectedIdSet;
}

function mergeCategories(rows = []) {
    const merged = [];
    rows.forEach((row) => {
        const id = normalizeId(row?.id);
        const name = normalizeName(row?.name || row?.text || row?.label || '');
        if (!id || !name) return;
        merged.push({ id, name });
    });

    merged.sort((a, b) => a.name.localeCompare(b.name));

    categoryCache.byId.clear();
    categoryCache.byNameKey.clear();
    categoryCache.orderedIds = [];

    merged.forEach((category) => {
        categoryCache.byId.set(category.id, category);
        categoryCache.byNameKey.set(normalizeNameKey(category.name), category);
        categoryCache.orderedIds.push(category.id);
    });
}

async function ensureCategoriesLoaded(options = {}) {
    const settings = normalizeOptions(options);
    if (categoryCache.orderedIds.length > 0) {
        return categoryCache.orderedIds.map((id) => categoryCache.byId.get(id)).filter(Boolean);
    }

    if (categoryCache.loadingPromise) {
        return categoryCache.loadingPromise;
    }

    categoryCache.loadingPromise = fetch(settings.sourceEndpoint, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`Category list request failed (${response.status})`);
            }
            return response.json();
        })
        .then((payload) => {
            const rows = Array.isArray(payload) ? payload : [];
            mergeCategories(rows);
            return categoryCache.orderedIds.map((id) => categoryCache.byId.get(id)).filter(Boolean);
        })
        .finally(() => {
            categoryCache.loadingPromise = null;
        });

    return categoryCache.loadingPromise;
}

function appendOption(select, category, selected = false) {
    const option = new Option(category.name, category.id, selected, selected);
    select.appendChild(option);
}

function syncSelectOptions(select, selectedIds = []) {
    const selectedSet = toSelectedIdSet(select, selectedIds);
    const existingSelectedByName = Array.from(select.options)
        .filter((opt) => opt.selected && !normalizeId(opt.value))
        .map((opt) => normalizeName(opt.textContent || opt.value))
        .filter(Boolean);

    select.innerHTML = '';

    categoryCache.orderedIds.forEach((id) => {
        const category = categoryCache.byId.get(id);
        if (!category) return;
        appendOption(select, category, selectedSet.has(category.id));
    });

    existingSelectedByName.forEach((name) => {
        const knownCategory = categoryCache.byNameKey.get(normalizeNameKey(name));
        if (knownCategory) {
            const option = Array.from(select.options).find((opt) => opt.value === knownCategory.id);
            if (option) option.selected = true;
            return;
        }
        const fallbackValue = normalizeId(name) || name;
        const option = new Option(name, fallbackValue, true, true);
        select.appendChild(option);
    });

    select.dispatchEvent(new Event('change', { bubbles: true }));
}

function getAllCategories() {
    return categoryCache.orderedIds
        .map((id) => categoryCache.byId.get(id))
        .filter(Boolean);
}

function getSelectedCategoryIds(select) {
    return Array.from(select.options)
        .filter((opt) => opt.selected)
        .map((opt) => normalizeId(opt.value))
        .filter(Boolean);
}

function getSelectedCategories(select) {
    const ids = new Set(getSelectedCategoryIds(select));
    const selected = [];
    ids.forEach((id) => {
        const row = categoryCache.byId.get(id);
        if (row) selected.push(row);
    });
    return selected;
}

function upsertCategory(categoryInput) {
    const id = normalizeId(categoryInput?.id);
    const name = normalizeName(categoryInput?.name || categoryInput?.text || '');
    if (!id || !name) return null;

    const category = { id, name };
    categoryCache.byId.set(id, category);
    categoryCache.byNameKey.set(normalizeNameKey(name), category);

    if (!categoryCache.orderedIds.includes(id)) {
        categoryCache.orderedIds.push(id);
        categoryCache.orderedIds.sort((a, b) => {
            const categoryA = categoryCache.byId.get(a);
            const categoryB = categoryCache.byId.get(b);
            return String(categoryA?.name || '').localeCompare(String(categoryB?.name || ''));
        });
    }

    return category;
}

class CategoryCombobox {
    constructor(select, options = {}) {
        this.select = select;
        this.options = normalizeOptions(options);
        this.uid = `category-combobox-${Math.random().toString(36).slice(2, 8)}`;
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

        // Shared combobox styles for tags/categories.
        this.select.classList.add('tag-combobox-source');
        this.select.setAttribute('aria-hidden', 'true');

        this.root = document.createElement('div');
        this.root.className = 'tag-combobox category-combobox';

        this.control = document.createElement('div');
        this.control.className = 'tag-combobox-control category-combobox-control';
        this.control.addEventListener('click', () => {
            this.input?.focus();
            this.open();
        });

        this.chipsWrap = document.createElement('div');
        this.chipsWrap.className = 'tag-combobox-chips category-combobox-chips';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'tag-combobox-input category-combobox-input';
        this.input.placeholder = 'Search or create categories...';
        this.input.setAttribute('autocomplete', 'off');
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-haspopup', 'listbox');
        this.input.setAttribute('aria-label', 'Search categories');

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'tag-combobox-dropdown category-combobox-dropdown';
        this.dropdown.id = `${this.uid}-listbox`;
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.hidden = true;
        this.input.setAttribute('aria-controls', this.dropdown.id);

        this.emptyState = document.createElement('div');
        this.emptyState.className = 'tag-combobox-empty category-combobox-empty';
        this.emptyState.textContent = 'No matching categories found';
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
        const selected = getSelectedCategories(this.select);
        this.chipsWrap.innerHTML = '';

        selected.forEach((category) => {
            const chip = document.createElement('span');
            chip.className = 'tag-combobox-chip category-combobox-chip';
            chip.innerHTML = `
                <span>${escapeHtml(category.name)}</span>
                <button type="button" class="tag-combobox-chip-remove category-combobox-chip-remove" aria-label="Remove category ${escapeHtml(category.name)}">&times;</button>
            `;

            chip.querySelector('.category-combobox-chip-remove')?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggleSelection(category.id, false);
            });

            this.chipsWrap.appendChild(chip);
        });
    }

    renderResults() {
        if (!this.dropdown || !this.input) return;

        const query = normalizeName(this.input.value);
        const selectedIds = new Set(getSelectedCategoryIds(this.select));
        const allCategories = getAllCategories();

        const filtered = allCategories
            .filter((category) => !selectedIds.has(category.id))
            .filter((category) => {
                if (!query) return true;
                return normalizeNameKey(category.name).includes(normalizeNameKey(query));
            })
            .slice(0, Math.max(1, Number(this.options.maxResults) || 50));

        this.filtered = filtered;
        this.activeIndex = filtered.length ? 0 : -1;

        this.dropdown.querySelectorAll('.category-combobox-option, .category-combobox-create').forEach((el) => el.remove());

        filtered.forEach((category, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'tag-combobox-option category-combobox-option';
            if (index === this.activeIndex) option.classList.add('is-active');
            option.id = `${this.uid}-opt-${category.id}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', index === this.activeIndex ? 'true' : 'false');
            option.dataset.id = category.id;
            option.dataset.name = category.name;
            option.textContent = category.name;
            option.addEventListener('click', () => {
                this.toggleSelection(category.id, true);
                this.input.value = '';
                this.renderResults();
                this.input.focus();
            });
            this.dropdown.appendChild(option);
        });

        const exactMatch = query ? categoryCache.byNameKey.get(normalizeNameKey(query)) : null;
        const canCreate = Boolean(this.options.allowCreate) && Boolean(query) && !exactMatch;

        if (canCreate) {
            const createBtn = document.createElement('button');
            createBtn.type = 'button';
            createBtn.className = 'tag-combobox-create category-combobox-create';
            createBtn.innerHTML = `Create category: <strong>${escapeHtml(query)}</strong>`;
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
        const options = this.dropdown.querySelectorAll('.category-combobox-option');
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
        const options = this.dropdown?.querySelectorAll('.category-combobox-option') || [];
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

        const options = this.dropdown?.querySelectorAll('.category-combobox-option') || [];
        const activeOption = options[this.activeIndex];
        if (activeOption?.dataset?.id) {
            this.toggleSelection(activeOption.dataset.id, true);
            this.input.value = '';
            this.renderResults();
            return;
        }

        const query = normalizeName(this.input?.value || '');
        if (!query || !this.options.allowCreate) return;

        const existing = categoryCache.byNameKey.get(normalizeNameKey(query));
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
            const category = categoryCache.byId.get(normalized);
            if (category) {
                appendOption(this.select, category, true);
            }
        }

        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    removeLastSelected() {
        const selected = getSelectedCategoryIds(this.select);
        const last = selected[selected.length - 1];
        if (!last) return;
        this.toggleSelection(last, false);
        if (this.isOpen()) this.renderResults();
    }

    async createAndSelect(rawName) {
        const name = normalizeName(rawName);
        if (!name) return;

        try {
            const category = await createCategory(name, this.options);
            if (!category?.id) return;
            syncSelectOptions(this.select, [...getSelectedCategoryIds(this.select), category.id]);
            this.toggleSelection(category.id, true);
            this.input.value = '';
            this.renderResults();
            this.open();
            window.showMessage?.('Category created successfully', 'success', 3000);
        } catch (error) {
            window.showMessage?.(error?.message || 'Failed to create category', 'danger', 5000);
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

export async function loadCategoryOptions(selectOrSelector, selectedIds = [], options = {}) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return [];

    const settings = normalizeOptions(options);
    const categories = await ensureCategoriesLoaded(settings);
    syncSelectOptions(select, selectedIds);

    const instance = comboboxInstances.get(select);
    if (instance) instance.refresh();

    return categories;
}

export async function createCategory(name, options = {}) {
    const settings = normalizeOptions(options);
    const normalizedName = normalizeName(name);
    if (!normalizedName) {
        throw new Error('Category name is required');
    }

    await ensureCategoriesLoaded(settings);
    const existing = categoryCache.byNameKey.get(normalizeNameKey(normalizedName));
    if (existing?.id) {
        return existing;
    }

    const response = await fetch(settings.createEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ name: normalizedName }).toString()
    });

    if (!response.ok) {
        throw new Error(`Category create request failed (${response.status})`);
    }

    const payload = await response.json();
    if (!payload?.success) {
        throw new Error(payload?.error || payload?.message || 'Failed to create category');
    }

    const category = upsertCategory({ id: payload.id, name: payload.name || normalizedName });
    if (!category) {
        throw new Error('Invalid category response from server');
    }

    comboboxInstances.forEach((instance) => {
        syncSelectOptions(instance.select, getSelectedCategoryIds(instance.select));
    });

    return category;
}

export async function createCategoryAndSelect(selectOrSelector, name, options = {}) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return null;

    const category = await createCategory(name, options);
    if (!category?.id) return null;

    const selected = getSelectedCategoryIds(select);
    selected.push(category.id);
    syncSelectOptions(select, selected);

    return category;
}

export function initCategoryCombobox(selectOrSelector, options = {}) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return null;

    const settings = normalizeOptions(options);
    let instance = comboboxInstances.get(select);
    if (!instance) {
        instance = new CategoryCombobox(select, settings);
        comboboxInstances.set(select, instance);
        instance.init();
    } else {
        instance.options = settings;
    }

    ensureCategoriesLoaded(settings)
        .then(() => {
            syncSelectOptions(select, getSelectedCategoryIds(select));
            instance.refresh();
        })
        .catch((error) => {
            console.error('[CategoryCombobox] Failed to load categories', error);
            window.showMessage?.('Failed to load categories', 'danger', 5000);
        });

    return instance;
}

export function destroyCategoryCombobox(selectOrSelector) {
    const select = resolveSelect(selectOrSelector);
    if (!select) return;
    const instance = comboboxInstances.get(select);
    if (!instance) return;
    instance.destroy();
    comboboxInstances.delete(select);
}
