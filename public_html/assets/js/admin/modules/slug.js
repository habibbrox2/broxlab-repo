import { byId, resolveNumericId, getRecordIdFromForm } from './core.js';

function setSlugFeedback(feedbackEl, message = '', state = '') {
    if (!feedbackEl) return;
    feedbackEl.textContent = message;
    feedbackEl.classList.remove('text-success', 'text-danger', 'text-muted');
    if (state === 'ok') feedbackEl.classList.add('text-success');
    else if (state === 'bad') feedbackEl.classList.add('text-danger');
    else feedbackEl.classList.add('text-muted');
}

async function checkSlugAvailability({ slug, checkUrl, excludeId = null, slugParam = 'slug', signal }) {
    if (!slug || !checkUrl) {
        return { available: false, message: '' };
    }

    const query = new URLSearchParams();
    query.set(slugParam, slug);
    if (excludeId) query.set('exclude_id', String(excludeId));

    const separator = checkUrl.includes('?') ? '&' : '?';
    const response = await fetch(`${checkUrl}${separator}${query.toString()}`, {
        signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    if (!response.ok) {
        throw new Error(`Slug check failed (${response.status})`);
    }

    const data = await response.json();
    const available = Boolean(data?.available ?? data?.success);
    return {
        available,
        message: data?.message || (available ? 'Slug available' : 'Slug unavailable')
    };
}

function setupSlugSync({
    nameEl,
    slugEl,
    feedbackEl = null,
    checkUrl,
    excludeId = null,
    slugParam = 'slug',
    debounceMs = 350
}) {
    if (!nameEl || !slugEl || !checkUrl) return null;

    let manualEdit = false;
    let debounceTimer = null;
    let controller = null;

    const generateSlug = () => {
        const fn = window.transliterateAndGenerateSlug;
        if (typeof fn !== 'function') return '';
        return fn(nameEl.value || '');
    };

    const normalizeSlug = (value) => {
        const fn = window.transliterateAndGenerateSlug;
        if (typeof fn !== 'function') return String(value || '').trim().toLowerCase();
        return fn(value || '');
    };

    const queueSlugCheck = (slug) => {
        const normalized = normalizeSlug(slug);
        if (debounceTimer) clearTimeout(debounceTimer);
        if (controller) controller.abort();

        if (!normalized) {
            setSlugFeedback(feedbackEl, '', '');
            return;
        }

        debounceTimer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const result = await checkSlugAvailability({
                    slug: normalized,
                    checkUrl,
                    excludeId,
                    slugParam,
                    signal: controller.signal
                });
                setSlugFeedback(feedbackEl, result.message, result.available ? 'ok' : 'bad');
            } catch (error) {
                if (error?.name === 'AbortError') return;
                setSlugFeedback(feedbackEl, 'Could not verify slug right now', 'bad');
            }
        }, debounceMs);
    };

    const onNameInput = () => {
        if (manualEdit) return;
        const generated = generateSlug();
        slugEl.value = generated;
        queueSlugCheck(generated);
    };

    const onSlugInput = () => {
        manualEdit = true;
        const normalized = normalizeSlug(slugEl.value || '');
        if (slugEl.value !== normalized) slugEl.value = normalized;
        queueSlugCheck(normalized);
    };

    nameEl.addEventListener('input', onNameInput);
    slugEl.addEventListener('input', onSlugInput);

    if (slugEl.value) {
        const normalized = normalizeSlug(slugEl.value);
        if (slugEl.value !== normalized) slugEl.value = normalized;
        queueSlugCheck(normalized);
    } else {
        onNameInput();
    }

    return {
        destroy() {
            nameEl.removeEventListener('input', onNameInput);
            slugEl.removeEventListener('input', onSlugInput);
            if (debounceTimer) clearTimeout(debounceTimer);
            if (controller) controller.abort();
        }
    };
}

function resolveSlugContext() {
    const contexts = [];

    const tagForm = byId('tagForm');
    if (tagForm) {
        contexts.push({
            type: 'tag',
            nameEl: byId('tagName') || tagForm.querySelector('input[name="name"]'),
            slugEl: byId('tagSlug') || tagForm.querySelector('input[name="slug"]'),
            feedbackEl: byId('slug-feedback'),
            checkUrl: '/api/tags/check_slug',
            excludeId: resolveNumericId(tagForm.querySelector('input[name="id"]')),
            slugParam: 'slug'
        });
    }

    const categoryForm = byId('categoryForm');
    if (categoryForm) {
        contexts.push({
            type: 'category',
            nameEl: categoryForm.querySelector('input[name="name"]'),
            slugEl: categoryForm.querySelector('input[name="slug"]'),
            feedbackEl: byId('slug-feedback'),
            checkUrl: '/api/categories/check_slug',
            excludeId: resolveNumericId(categoryForm.querySelector('input[name="id"]')),
            slugParam: 'slug'
        });
    }

    const contentForm = document.querySelector('form[data-autosave="true"]');
    if (contentForm && byId('title') && byId('seo')) {
        const action = contentForm.getAttribute('action') || '';
        const checkUrl = action.includes('/posts/')
            ? '/api/posts/check_permalink'
            : action.includes('/pages/')
                ? '/api/pages/check_url'
                : '';
        if (checkUrl) {
            contexts.push({
                type: 'content',
                nameEl: byId('title'),
                slugEl: byId('seo'),
                feedbackEl: byId('seo-feedback'),
                checkUrl,
                excludeId: getRecordIdFromForm(contentForm),
                slugParam: 'slug'
            });
        }
    }

    const serviceFormData = byId('service-form-data');
    if (serviceFormData) {
        contexts.push({
            type: 'service',
            excludeId: resolveNumericId(serviceFormData.dataset.excludeId)
        });
    }

    return contexts;
}

export function initUnifiedSlugFeatures() {
    const contexts = resolveSlugContext();
    contexts.forEach((context) => {
        if (context.type === 'service') return;
        setupSlugSync(context);
    });
}