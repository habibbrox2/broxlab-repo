export const byId = (id) => document.getElementById(id);

export function resolveNumericId(...candidates) {
    for (const candidate of candidates) {
        const raw = typeof candidate === 'string' ? candidate : (candidate?.value ?? candidate?.textContent ?? '');
        if (raw === null || raw === undefined) continue;
        const parsed = parseInt(String(raw).trim(), 10);
        if (Number.isFinite(parsed) && parsed > 0) return parsed;
    }
    return null;
}

export function getRecordIdFromForm(form) {
    if (!form) return null;
    return resolveNumericId(
        form.querySelector('input[name="id"]'),
        byId('post_id'),
        byId('page_id')
    );
}

export function getContentEditorHtml(contentId = 'content') {
    const editorInstance = window[`editor_${contentId}`];
    if (editorInstance && typeof editorInstance.getContent === 'function') {
        return editorInstance.getContent() || '';
    }

    const hiddenInput = byId(`${contentId}-input`);
    if (hiddenInput) return hiddenInput.value || '';

    const el = byId(contentId);
    if (!el) return '';
    if (typeof el.value === 'string') return el.value || '';
    return el.innerHTML || '';
}

export function setContentEditorHtml(html, contentId = 'content') {
    const safeHtml = String(html || '');
    const editorInstance = window[`editor_${contentId}`];
    if (editorInstance && typeof editorInstance.setContent === 'function') {
        editorInstance.setContent(safeHtml);
        return;
    }

    const hiddenInput = byId(`${contentId}-input`);
    if (hiddenInput) hiddenInput.value = safeHtml;

    const el = byId(contentId);
    if (!el) return;

    if (typeof el.value === 'string') {
        el.value = safeHtml;
    } else {
        el.innerHTML = safeHtml;
    }
}

export function initContentPreviewSync(contentId = 'content', previewId = 'preview') {
    const contentEl = byId(contentId);
    const previewEl = byId(previewId);
    if (!contentEl || !previewEl) return;

    const syncPreview = () => {
        previewEl.innerHTML = getContentEditorHtml(contentId);
    };

    contentEl.addEventListener('input', syncPreview);
    document.addEventListener('rte:ready', (event) => {
        if (!event?.detail?.editorId || event.detail.editorId !== contentId) return;
        syncPreview();
    });

    syncPreview();
}

// merged from dom-utils.js
export function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };
    return String(text ?? '').replace(/[&<>"']/g, (char) => map[char]);
}

export function setText(el, text) {
    if (!el) return;
    el.textContent = String(text ?? '');
}

export function toSafeId(value) {
    return String(value ?? '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^a-zA-Z0-9_-]/g, '');
}