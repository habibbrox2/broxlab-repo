// Editor Sanitize Helpers - move heavy sanitization logic out of core
(function (global) {
    function installSanitizeHelpers(RichTextEditor) {
        function isSafeUrl(value, tagName, attrName) {
            const raw = String(value || '').trim();
            if (!raw) return true;

            // Allow anchor fragments and relative URLs.
            if (raw.startsWith('#') || raw.startsWith('/') || raw.startsWith('./') || raw.startsWith('../')) {
                return true;
            }

            const lower = raw.toLowerCase();
            if (lower.startsWith('javascript:') || lower.startsWith('vbscript:')) {
                return false;
            }

            // Allow inline image data URLs for img/src only.
            if (lower.startsWith('data:')) {
                return tagName === 'img'
                    && attrName === 'src'
                    && /^data:image\/(?:png|jpe?g|gif|webp|bmp|avif);base64,[a-z0-9+/=\s]+$/i.test(raw);
            }

            if (lower.startsWith('mailto:') || lower.startsWith('tel:')) {
                return tagName === 'a' && attrName === 'href';
            }

            if (lower.startsWith('http:') || lower.startsWith('https:')) {
                return true;
            }

            // Non-prefixed values are treated as relative paths.
            if (!/^[a-z][a-z0-9+.-]*:/i.test(raw)) {
                return true;
            }

            return false;
        }

        function sanitizeInlineStyle(styleValue) {
            const raw = String(styleValue || '').trim();
            if (!raw) return '';

            const forbiddenPattern = /(expression\s*\(|@import|behavior\s*:|-moz-binding|url\s*\()/i;
            if (forbiddenPattern.test(raw)) {
                return '';
            }

            const allowedProps = new Set([
                'color', 'background-color', 'font-size', 'font-family', 'font-weight', 'font-style',
                'text-decoration', 'text-align', 'line-height', 'letter-spacing',
                'width', 'height', 'max-width', 'min-width', 'max-height', 'min-height',
                'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
                'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
                'border', 'border-width', 'border-style', 'border-color',
                'display', 'vertical-align', 'float', 'clear'
            ]);

            const sanitizedDeclarations = [];
            raw.split(';').forEach((chunk) => {
                const decl = chunk.trim();
                if (!decl) return;

                const idx = decl.indexOf(':');
                if (idx <= 0) return;

                const prop = decl.slice(0, idx).trim().toLowerCase();
                const val = decl.slice(idx + 1).trim();
                if (!allowedProps.has(prop)) return;
                if (!val) return;
                if (/(javascript:|data:|vbscript:)/i.test(val)) return;
                if (forbiddenPattern.test(val)) return;

                sanitizedDeclarations.push(`${prop}: ${val}`);
            });

            return sanitizedDeclarations.join('; ');
        }

        // Attempt to load DOMPurify asynchronously so we can use a robust
        // sanitization library when available. We still keep the built-in
        // sanitizer as a fallback for immediate protection.
        function _ensureDOMPurify() {
            if (typeof window === 'undefined') return Promise.resolve(null);
            if (window.DOMPurify) return Promise.resolve(window.DOMPurify);

            return new Promise((resolve) => {
                try {
                    let settled = false;

                    function finish() {
                        if (settled) return;
                        settled = true;
                        resolve(window.DOMPurify || null);
                    }

                    let base = '';
                    const scripts = document.getElementsByTagName('script');
                    for (let i = scripts.length - 1; i >= 0; i--) {
                        const src = scripts[i].src || '';
                        if (src && src.indexOf('editor.sanitize.js') !== -1) {
                            base = src.replace(/\/[^\/]*$/, '/');
                            break;
                        }
                    }
                    if (!base) {
                        base = (location && location.href) ? location.href.replace(/\/[^\/]*$/, '/') : './';
                    }

                    const tryPaths = [
                        base + 'purify.min.js',
                        base + '../vendor/dompurify/purify.min.js',
                        '/vendor/dompurify/purify.min.js',
                        'https://cdn.jsdelivr.net/npm/dompurify@3.1.0/dist/purify.min.js'
                    ];
                    let tryIndex = 0;

                    function tryNextPath() {
                        if (window.DOMPurify) {
                            finish();
                            return;
                        }
                        if (tryIndex >= tryPaths.length) {
                            finish();
                            return;
                        }

                        const path = tryPaths[tryIndex++];
                        const existing = document.querySelector(`script[data-rte-purify-src="${path}"]`);
                        if (existing) {
                            existing.addEventListener('load', () => finish(), { once: true });
                            existing.addEventListener('error', () => tryNextPath(), { once: true });
                            return;
                        }

                        try {
                            const script = document.createElement('script');
                            script.src = path;
                            script.async = true;
                            script.setAttribute('data-purify-loading', '1');
                            script.setAttribute('data-rte-purify-src', path);
                            script.onload = () => finish();
                            script.onerror = () => tryNextPath();
                            document.head.appendChild(script);
                        } catch (e) {
                            tryNextPath();
                        }
                    }

                    tryNextPath();
                    setTimeout(() => finish(), 5000);
                } catch (err) {
                    resolve(null);
                }
            });
        }

        // Kick off loader but don't await here (non-blocking). Expose a promise
        // on window so the editor bootstrap can optionally wait for DOMPurify
        // to load. Try local paths first, CDN as the last resort.
        if (typeof window !== 'undefined') {
            window.RTE_DOMPurifyPromise = _ensureDOMPurify().catch(() => null);
        }

        RichTextEditor.prototype.sanitizeHTML = function (html) {
            if (!html) return '';

            // If DOMPurify is present, prefer it (fast and secure)
            if (typeof window !== 'undefined' && window.DOMPurify) {
                try {
                    const config = {
                        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'hr', 'a', 'img', 'span', 'div', 'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'caption', 'figure', 'figcaption', 'video', 'source', 'b', 'i', 'sub', 'sup'],
                        ALLOWED_ATTR: ['href', 'target', 'rel', 'title', 'src', 'alt', 'width', 'height', 'style', 'class', 'frameborder', 'allow', 'allowfullscreen', 'controls', 'autoplay', 'loop', 'muted', 'type', 'colspan', 'rowspan']
                    };
                    return window.DOMPurify.sanitize(html, config);
                } catch (e) {
                    // fallthrough to built-in sanitizer
                    console.warn('DOMPurify sanitize failed, falling back:', e);
                }
            }

            // Built-in whitelist sanitizer (fallback)
            const allowedTags = [
                'p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li', 'blockquote', 'hr', 'a', 'img', 'span', 'div',
                'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot', 'caption',
                'figure', 'figcaption', 'video', 'source', 'b', 'i', 'sub', 'sup'
            ];

            const allowedAttrs = {
                'a': ['href', 'target', 'rel', 'title'],
                'img': ['src', 'alt', 'title', 'width', 'height', 'style'],
                'span': ['style', 'class'],
                'div': ['style', 'class'],
                'video': ['src', 'width', 'height', 'controls', 'autoplay', 'loop', 'muted'],
                'source': ['src', 'type'],
                // figure itself should not carry contenteditable (it causes the
                // editor to lock if pasted from source). captions are editable by
                // our runtime behavior and don't need the attribute either.
                'figure': ['class'],
                'figcaption': [],
                'td': ['colspan', 'rowspan'],
                'th': ['colspan', 'rowspan']
            };

            try {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                this.sanitizeNode(tempDiv, allowedTags, allowedAttrs);
                return tempDiv.innerHTML;
            } catch (e) {
                console.error('Sanitization error (fallback):', e);
                return '';
            }
        };

        // Recursively sanitize a DOM node
        RichTextEditor.prototype.sanitizeNode = function (node, allowedTags, allowedAttrs) {
            const nodesToRemove = [];

            for (let i = 0; i < node.childNodes.length; i++) {
                const child = node.childNodes[i];

                if (child.nodeType === Node.ELEMENT_NODE) {
                    const tagName = child.tagName.toLowerCase();

                    // Remove disallowed tags
                    if (!allowedTags.includes(tagName)) {
                        nodesToRemove.push(child);
                        continue;
                    }

                    // Remove disallowed attributes
                    const attrs = Array.from(child.attributes);
                    const allowed = allowedAttrs[tagName] || [];

                    attrs.forEach(attr => {
                        const attrName = attr.name.toLowerCase();
                        const attrValue = String(attr.value || '').trim();

                        // Remove event handlers
                        if (attrName.startsWith('on')) {
                            child.removeAttribute(attr.name);
                            return;
                        }

                        // Sanitize style values instead of trusting raw CSS.
                        if (attrName === 'style') {
                            const cleanStyle = sanitizeInlineStyle(attrValue);
                            if (cleanStyle) {
                                child.setAttribute('style', cleanStyle);
                            } else {
                                child.removeAttribute(attr.name);
                            }
                            return;
                        }

                        // Enforce safe URL protocols for href/src.
                        if ((attrName === 'href' || attrName === 'src') && !isSafeUrl(attrValue, tagName, attrName)) {
                            child.removeAttribute(attr.name);
                            return;
                        }

                        // Remove if not in allowed list
                        if (!allowed.includes(attrName) && attrName !== 'style' && attrName !== 'class') {
                            child.removeAttribute(attr.name);
                        }
                    });

                    // Recursively sanitize children
                    this.sanitizeNode(child, allowedTags, allowedAttrs);
                }
            }

            // Remove disallowed nodes but keep their text
            nodesToRemove.forEach(child => {
                const textNode = document.createTextNode(child.textContent);
                node.replaceChild(textNode, child);
            });
        };

        return true;
    }

    // Export for both CommonJS and ES6 module contexts
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { installSanitizeHelpers };
    }
    if (typeof window !== 'undefined') {
        window.installSanitizeHelpers = installSanitizeHelpers;
    }
})(typeof window !== 'undefined' ? window : {});

