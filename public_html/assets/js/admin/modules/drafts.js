import { byId, getRecordIdFromForm, getContentEditorHtml, setContentEditorHtml } from './core.js';

const DraftStore = {
    dbName: 'broxbhai_offline_drafts_v1',
    storeName: 'drafts',
    open() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(this.dbName, 1);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(this.storeName)) {
                    db.createObjectStore(this.storeName, { keyPath: 'key' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    },
    async tx(mode = 'readonly') {
        const db = await this.open();
        return db.transaction(this.storeName, mode).objectStore(this.storeName);
    },
    async get(key) {
        const store = await this.tx('readonly');
        return new Promise((resolve, reject) => {
            const req = store.get(key);
            req.onsuccess = () => resolve(req.result ? req.result.value : null);
            req.onerror = () => reject(req.error);
        });
    },
    async set(key, value) {
        const store = await this.tx('readwrite');
        return new Promise((resolve, reject) => {
            const req = store.put({ key, value });
            req.onsuccess = () => resolve(true);
            req.onerror = () => reject(req.error);
        });
    },
    async del(key) {
        const store = await this.tx('readwrite');
        return new Promise((resolve, reject) => {
            const req = store.delete(key);
            req.onsuccess = () => resolve(true);
            req.onerror = () => reject(req.error);
        });
    }
};

const draftNowIso = () => new Date().toISOString();
const draftClamp = (value, min, max) => Math.max(min, Math.min(max, value));
const draftHash = (value) => {
    const raw = String(value || '');
    let hash = 2166136261 >>> 0;
    for (let i = 0; i < raw.length; i += 1) {
        hash ^= raw.charCodeAt(i);
        hash = Math.imul(hash, 16777619) >>> 0;
    }
    return (hash >>> 0).toString(36);
};

class OfflineDraftManager {
    constructor(options = {}) {
        this.id = options.id || null;
        this.type = options.type || 'post';
        this.selectors = options.selectors || {
            title: '#title',
            content: '#content',
            seo: '#seo',
            status: '#status'
        };
        this.endpoints = options.endpoints || {
            post: '/api/posts/autosave',
            page: '/api/pages/autosave'
        };
        this.debounce = draftClamp(options.debounce || 800, 200, 3000);
        this.syncIntervalMs = options.syncIntervalMs || 3000;
        this.maxBatch = options.maxBatch || 3;
        this.maxRetries = options.maxRetries || 8;

        this.syncQueueKey = 'drafts:queue';
        this.localKey = `draft:${this.type}:${this.id || 'new'}`;
        this.elements = {};

        this._debounceTimer = null;
        this._syncTimer = null;
        this._isSyncing = false;
        this._concurrentSyncs = 0;
        this._lastLocalHash = null;

        this._onInputBound = this._onInput.bind(this);
        this._onBeforeUnloadBound = this._onBeforeUnload.bind(this);
        this._onOnlineBound = this._onOnline.bind(this);
    }

    async init() {
        this.elements.title = document.querySelector(this.selectors.title);
        this.elements.content = document.querySelector(this.selectors.content);
        this.elements.seo = document.querySelector(this.selectors.seo);
        this.elements.status = document.querySelector(this.selectors.status);

        if (!this.elements.title || !this.elements.content) return;

        ['input', 'change'].forEach((eventName) => {
            document.addEventListener(eventName, this._onInputBound, { passive: true });
        });
        window.addEventListener('beforeunload', this._onBeforeUnloadBound);
        window.addEventListener('online', this._onOnlineBound);

        this._syncTimer = setInterval(() => this.processQueue(), this.syncIntervalMs);

        const local = await DraftStore.get(this.localKey);
        if (local && local.payload) {
            this._renderRecoveryUi(local);
        }

        const payload = this._gatherPayload();
        this._lastLocalHash = draftHash(JSON.stringify(payload));

        const queue = await DraftStore.get(this.syncQueueKey);
        if (!queue) await DraftStore.set(this.syncQueueKey, { items: [] });
    }

    _resolveRecordId() {
        return getRecordIdFromForm(document.querySelector('form[data-autosave="true"]'));
    }

    _getContentValue() {
        const contentEl = this.elements.content;
        if (!contentEl) return '';
        if (contentEl.id) return getContentEditorHtml(contentEl.id);
        if (typeof contentEl.value === 'string') return contentEl.value || '';
        return contentEl.innerHTML || '';
    }

    _setContentValue(html) {
        const contentEl = this.elements.content;
        if (!contentEl) return;
        if (contentEl.id) {
            setContentEditorHtml(html, contentEl.id);
            return;
        }
        if (typeof contentEl.value === 'string') contentEl.value = html || '';
        else contentEl.innerHTML = html || '';
    }

    _gatherPayload() {
        return {
            id: this._resolveRecordId(),
            title: this.elements.title?.value || '',
            content: this._getContentValue(),
            slug: this.elements.seo?.value || '',
            status: this.elements.status?.value || ''
        };
    }

    _onInput(event) {
        const source = event.target;
        if (!source?.matches) return;
        const selectors = [this.selectors.title, this.selectors.content, this.selectors.seo, this.selectors.status]
            .filter(Boolean)
            .join(',');
        if (!selectors || !source.matches(selectors)) return;

        clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            this.saveLocal().catch(() => { });
        }, this.debounce);
    }

    async saveLocal() {
        const payload = this._gatherPayload();
        const stamp = draftNowIso();
        const hash = draftHash(JSON.stringify(payload));
        if (this._lastLocalHash && this._lastLocalHash === hash) return;
        this._lastLocalHash = hash;

        const record = {
            payload,
            updatedAt: stamp,
            pendingSync: true,
            retries: 0,
            lastError: null,
            nextAttemptAt: null,
            paused: false
        };
        await DraftStore.set(this.localKey, record);
        await this._enqueue(record);
        if (navigator.onLine) this.processQueue();
    }

    async _enqueue(record) {
        const queue = (await DraftStore.get(this.syncQueueKey)) || { items: [] };
        queue.items = queue.items.filter((item) => item.key !== this.localKey);
        queue.items.unshift({ key: this.localKey, updatedAt: record.updatedAt });
        await DraftStore.set(this.syncQueueKey, queue);
    }

    async processQueue() {
        if (this._isSyncing || !navigator.onLine) return;
        this._isSyncing = true;
        try {
            const queue = (await DraftStore.get(this.syncQueueKey)) || { items: [] };
            if (!Array.isArray(queue.items) || queue.items.length === 0) return;
            let processed = 0;
            for (const item of queue.items) {
                if (!navigator.onLine || processed >= this.maxBatch) break;

                const local = await DraftStore.get(item.key);
                if (!local || !local.payload || !local.pendingSync) {
                    await this._removeFromQueue(item.key);
                    continue;
                }
                if (local.paused === true) {
                    await this._removeFromQueue(item.key);
                    continue;
                }

                const nextAttemptAt = Date.parse(local.nextAttemptAt || '');
                if (Number.isFinite(nextAttemptAt) && nextAttemptAt > Date.now()) {
                    continue;
                }

                await this._syncItem(item.key, local);
                processed += 1;
            }
        } finally {
            this._isSyncing = false;
        }
    }

    async _syncItem(key, localRecord = null) {
        const local = localRecord || await DraftStore.get(key);
        if (!local || !local.payload || !local.pendingSync) {
            await this._removeFromQueue(key);
            return;
        }
        if (local.paused === true) {
            await this._removeFromQueue(key);
            return;
        }
        const nextAttemptAt = Date.parse(local.nextAttemptAt || '');
        if (Number.isFinite(nextAttemptAt) && nextAttemptAt > Date.now()) {
            return;
        }
        if (this._concurrentSyncs >= this.maxBatch) return;

        this._concurrentSyncs += 1;
        try {
            const endpoint = this.type === 'post' ? this.endpoints.post : this.endpoints.page;
            const payload = {
                id: local.payload.id || this._resolveRecordId(),
                title: local.payload.title || '',
                content: local.payload.content || '',
                slug: local.payload.slug || '',
                status: local.payload.status || '',
                csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
            };

            if (!payload.id) {
                await this._removeFromQueue(key);
                return;
            }

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(payload)
            });

            if (response.ok) {
                local.pendingSync = false;
                local.serverSavedAt = draftNowIso();
                local.lastError = null;
                local.retries = 0;
                local.nextAttemptAt = null;
                local.paused = false;
                await DraftStore.set(key, local);
                await this._removeFromQueue(key);
            } else {
                const text = await response.text().catch(() => '');
                await this._handleSyncFailure(key, local, `HTTP ${response.status}: ${text.slice(0, 120)}`);
            }
        } catch (error) {
            await this._handleSyncFailure(key, local, error?.message || String(error));
        } finally {
            this._concurrentSyncs = Math.max(0, this._concurrentSyncs - 1);
        }
    }

    async _handleSyncFailure(key, local, message) {
        local.lastError = message;
        local.retries = (local.retries || 0) + 1;
        local.pendingSync = true;

        if (local.retries >= this.maxRetries) {
            local.paused = true;
            local.nextAttemptAt = null;
            await DraftStore.set(key, local);
            await this._removeFromQueue(key);
            return;
        }

        local.paused = false;
        local.nextAttemptAt = new Date(Date.now() + Math.min(60000, 1500 * (2 ** local.retries))).toISOString();
        await DraftStore.set(key, local);

        const queue = (await DraftStore.get(this.syncQueueKey)) || { items: [] };
        if (!queue.items.find((item) => item.key === key)) {
            queue.items.push({ key, updatedAt: local.updatedAt });
            await DraftStore.set(this.syncQueueKey, queue);
        }
    }

    async _removeFromQueue(key) {
        const queue = (await DraftStore.get(this.syncQueueKey)) || { items: [] };
        queue.items = (queue.items || []).filter((item) => item.key !== key);
        await DraftStore.set(this.syncQueueKey, queue);
    }

    _renderRecoveryUi(local) {
        const placeholder = byId('offline-draft-recover-placeholder');
        if (!placeholder) return;

        placeholder.classList.remove('d-none');
        const stampEl = placeholder.querySelector('.draft-saved-at');
        if (stampEl) stampEl.textContent = local.updatedAt || '';

        const restoreBtn = placeholder.querySelector('.restore-draft-btn');
        if (restoreBtn) {
            restoreBtn.onclick = async () => {
                await this.restoreLocalToForm(local.payload);
                placeholder.classList.add('d-none');
            };
        }

        const dismissBtn = placeholder.querySelector('.dismiss-draft-btn');
        if (dismissBtn) {
            dismissBtn.onclick = async () => {
                await DraftStore.del(this.localKey);
                await this._removeFromQueue(this.localKey);
                placeholder.classList.add('d-none');
            };
        }
    }

    async restoreLocalToForm(payload) {
        if (!payload) return;
        if (payload.title && this.elements.title) this.elements.title.value = payload.title;
        if (payload.slug && this.elements.seo) this.elements.seo.value = payload.slug;
        if (payload.status && this.elements.status) this.elements.status.value = payload.status;
        if (payload.content) this._setContentValue(payload.content);
        await this.saveLocal();
    }

    _onOnline() {
        this.processQueue();
    }

    async _onBeforeUnload() {
        clearTimeout(this._debounceTimer);
        try {
            await this.saveLocal();
        } catch (error) {
            // noop
        }
    }

    async clearLocal() {
        await DraftStore.del(this.localKey);
        await this._removeFromQueue(this.localKey);
    }

    destroy() {
        clearTimeout(this._debounceTimer);
        clearInterval(this._syncTimer);

        ['input', 'change'].forEach((eventName) => {
            document.removeEventListener(eventName, this._onInputBound);
        });
        window.removeEventListener('beforeunload', this._onBeforeUnloadBound);
        window.removeEventListener('online', this._onOnlineBound);
    }
}

export function initOfflineDraftForContentForms() {
    if (!window.indexedDB) return null;

    const form = document.querySelector('form[data-autosave="true"]');
    const titleEl = byId('title');
    const contentEl = byId('content');
    if (!form || !titleEl || !contentEl) return null;

    const action = form.getAttribute('action') || '';
    const type = action.includes('/pages/') ? 'page' : action.includes('/posts/') ? 'post' : null;
    if (!type) return null;

    const recordId = getRecordIdFromForm(form) || 'new';

    if (window.draftMgr && typeof window.draftMgr.destroy === 'function') {
        window.draftMgr.destroy();
    }

    const manager = new OfflineDraftManager({
        id: recordId,
        type,
        selectors: { title: '#title', content: '#content', seo: '#seo', status: '#status' },
        endpoints: { post: '/api/posts/autosave', page: '/api/pages/autosave' },
        debounce: 800,
        maxRetries: 8
    });

    window.draftMgr = manager;
    manager.init().catch(() => { });

    form.addEventListener('submit', () => {
        if (window.draftMgr && typeof window.draftMgr.clearLocal === 'function') {
            window.draftMgr.clearLocal().catch(() => { });
        }
        byId('offline-draft-recover-placeholder')?.classList.add('d-none');
        document.querySelector('.offline-draft-banner')?.remove();
    });

    return manager;
}

export { OfflineDraftManager };
