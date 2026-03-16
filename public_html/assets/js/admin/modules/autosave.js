import { getRecordIdFromForm, getContentEditorHtml } from './core.js';

class FormAutosave {
    constructor(options = {}) {
        this.options = {
            formSelector: options.formSelector || 'form[data-autosave="true"]',
            titleSelector: options.titleSelector || '#title',
            contentSelector: options.contentSelector || '#content',
            seoSelector: options.seoSelector || '#seo',
            statusSelector: options.statusSelector || '#status',
            previewSelector: options.previewSelector || '#preview',
            inactivityMs: options.inactivityMs || 30000,
            minSaveIntervalMs: options.minSaveIntervalMs || 5000,
            checkIntervalMs: options.checkIntervalMs || 3000,
            debug: options.debug === true
        };

        this.form = document.querySelector(this.options.formSelector);
        this.titleEl = document.querySelector(this.options.titleSelector);
        this.contentEl = document.querySelector(this.options.contentSelector);
        this.seoEl = document.querySelector(this.options.seoSelector);
        this.statusEl = document.querySelector(this.options.statusSelector);
        this.previewEl = document.querySelector(this.options.previewSelector);
        this.endpoint = '';

        this.enabled = false;
        this.timers = { inactivity: null, periodic: null };
        this.state = {
            hasChanges: false,
            isUserActive: true,
            isOnline: navigator.onLine,
            isSaving: false,
            lastSaveTime: 0
        };

        this._activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        this._onFieldChangeBound = this._onFieldChange.bind(this);
        this._onActivityBound = this._onUserActivity.bind(this);
        this._onOnlineBound = this._onOnline.bind(this);
        this._onOfflineBound = this._onOffline.bind(this);
        this._onBeforeUnloadBound = this._onBeforeUnload.bind(this);
        this._onRteReadyBound = this._onRteReady.bind(this);

        this._init();
    }

    _log(message) {
        if (this.options.debug) {
            console.log(`[Autosave] ${message}`);
        }
    }

    _init() {
        if (!this.form || !this.titleEl || !this.contentEl) return;

        const action = this.form.getAttribute('action') || '';
        if (action.includes('/posts/')) {
            this.endpoint = '/api/posts/autosave';
        } else if (action.includes('/pages/')) {
            this.endpoint = '/api/pages/autosave';
        } else {
            return;
        }

        if (!getRecordIdFromForm(this.form)) {
            this._log('Autosave disabled: missing record id');
            return;
        }

        this.enabled = true;
        this._bindEvents();
        this._resetInactivityTimer();
        this._startPeriodicCheck();
        this._updatePreview();
    }

    _bindEvents() {
        this.titleEl.addEventListener('input', this._onFieldChangeBound);
        this.contentEl.addEventListener('input', this._onFieldChangeBound);
        this.contentEl.addEventListener('change', this._onFieldChangeBound);
        this.seoEl?.addEventListener('input', this._onFieldChangeBound);
        this.statusEl?.addEventListener('input', this._onFieldChangeBound);
        this.statusEl?.addEventListener('change', this._onFieldChangeBound);

        this._activityEvents.forEach((eventName) => {
            document.addEventListener(eventName, this._onActivityBound, { passive: true });
        });

        document.addEventListener('rte:ready', this._onRteReadyBound);
        window.addEventListener('online', this._onOnlineBound);
        window.addEventListener('offline', this._onOfflineBound);
        window.addEventListener('beforeunload', this._onBeforeUnloadBound);
    }

    _onRteReady(event) {
        if (!this.contentEl?.id) return;
        if (event?.detail?.editorId !== this.contentEl.id) return;
        this._onFieldChange();
        this._updatePreview();
    }

    _onUserActivity() {
        if (!this.enabled) return;
        this.state.isUserActive = true;
        this._resetInactivityTimer();
    }

    _onFieldChange() {
        if (!this.enabled) return;
        this.state.hasChanges = true;
        this._resetInactivityTimer();
        this._updatePreview();
    }

    _resetInactivityTimer() {
        clearTimeout(this.timers.inactivity);
        this.timers.inactivity = setTimeout(() => {
            this.state.isUserActive = false;
            if (this.state.hasChanges) this.save();
        }, this.options.inactivityMs);
    }

    _startPeriodicCheck() {
        clearInterval(this.timers.periodic);
        this.timers.periodic = setInterval(() => {
            if (!this.enabled || this.state.isSaving || !this.state.isOnline) return;
            if (!this.state.isUserActive && this.state.hasChanges) this.save();
        }, this.options.checkIntervalMs);
    }

    _getContentValue() {
        if (!this.contentEl) return '';
        if (this.contentEl.id) return getContentEditorHtml(this.contentEl.id);
        if (typeof this.contentEl.value === 'string') return this.contentEl.value || '';
        return this.contentEl.innerHTML || '';
    }

    _updatePreview() {
        if (!this.previewEl) return;
        this.previewEl.innerHTML = this._getContentValue();
    }

    _buildPayload() {
        return {
            id: getRecordIdFromForm(this.form),
            title: this.titleEl?.value || '',
            content: this._getContentValue(),
            slug: this.seoEl?.value || '',
            status: this.statusEl?.value || '',
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        };
    }

    async save() {
        if (!this.enabled || this.state.isSaving || !this.state.hasChanges || !this.state.isOnline) return;

        const now = Date.now();
        if (now - this.state.lastSaveTime < this.options.minSaveIntervalMs) return;

        const payload = this._buildPayload();
        if (!payload.id) return;

        this.state.isSaving = true;
        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(payload)
            });

            if (!response.ok) return;
            const result = await response.json().catch(() => ({}));
            if (result?.success === false) return;

            this.state.hasChanges = false;
            this.state.lastSaveTime = now;
        } finally {
            this.state.isSaving = false;
        }
    }

    _onOnline() {
        this.state.isOnline = true;
        if (this.state.hasChanges) this.save();
    }

    _onOffline() {
        this.state.isOnline = false;
    }

    _saveOnPageExit() {
        const payload = this._buildPayload();
        if (!payload.id || !this.endpoint) return;

        const body = new URLSearchParams(payload).toString();
        if (!body) return;

        try {
            if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
                const blob = new Blob([body], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
                const sent = navigator.sendBeacon(this.endpoint, blob);
                if (sent) {
                    this.state.hasChanges = false;
                    this.state.lastSaveTime = Date.now();
                    return;
                }
            }
        } catch (error) {
            // Fall through to keepalive fetch
        }

        fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body,
            keepalive: true
        }).catch(() => { });
    }

    _onBeforeUnload() {
        if (!this.enabled || !this.state.hasChanges) return;
        this._saveOnPageExit();
    }

    destroy() {
        this.enabled = false;
        clearTimeout(this.timers.inactivity);
        clearInterval(this.timers.periodic);

        this.titleEl?.removeEventListener('input', this._onFieldChangeBound);
        this.contentEl?.removeEventListener('input', this._onFieldChangeBound);
        this.contentEl?.removeEventListener('change', this._onFieldChangeBound);
        this.seoEl?.removeEventListener('input', this._onFieldChangeBound);
        this.statusEl?.removeEventListener('input', this._onFieldChangeBound);
        this.statusEl?.removeEventListener('change', this._onFieldChangeBound);

        this._activityEvents.forEach((eventName) => {
            document.removeEventListener(eventName, this._onActivityBound);
        });

        document.removeEventListener('rte:ready', this._onRteReadyBound);
        window.removeEventListener('online', this._onOnlineBound);
        window.removeEventListener('offline', this._onOfflineBound);
        window.removeEventListener('beforeunload', this._onBeforeUnloadBound);
    }
}

export function initAutosaveForContentForms() {
    const form = document.querySelector('form[data-autosave="true"]');
    if (!form) return null;

    if (window.formAutosave && typeof window.formAutosave.destroy === 'function') {
        window.formAutosave.destroy();
    }

    window.formAutosave = new FormAutosave({
        formSelector: 'form[data-autosave="true"]',
        titleSelector: '#title',
        contentSelector: '#content',
        seoSelector: '#seo',
        statusSelector: '#status',
        previewSelector: '#preview',
        debug: false
    });

    return window.formAutosave;
}

export { FormAutosave };
