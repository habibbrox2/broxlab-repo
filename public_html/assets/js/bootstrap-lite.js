(function () {
    'use strict';

    // temporarily disable console output in all JS files unless explicitly enabled.
    // setting `window.ENABLE_CONSOLE_LOGS = true` from the browser console will restore
    // normal behaviour. this is useful for production debugging or when logs are noisy.
    if (!window.ENABLE_CONSOLE_LOGS) {
        ['log','debug','info','warn','error','trace'].forEach((fn) => {
            console[fn] = () => {};
        });
    }

    if (window.bootstrap && window.bootstrap.__BROX_LITE__) return;
    if (window.bootstrap && typeof window.bootstrap.Modal === 'function' && typeof window.bootstrap.Collapse === 'function') return;

    const stores = Object.create(null);
    const getStore = (name) => stores[name] || (stores[name] = new WeakMap());
    const toBool = (v, d = false) => v == null ? d : String(v).toLowerCase() === 'true';
    const toNum = (v, d = 0) => Number.isFinite(Number(v)) ? Number(v) : d;
    const emit = (el, name, detail) => {
        if (!el) return null;
        const ev = new CustomEvent(name, { bubbles: true, cancelable: true, detail: detail || {} });
        el.dispatchEvent(ev);
        return ev;
    };
    const selFrom = (trigger) => {
        if (!trigger) return null;
        const t = trigger.getAttribute('data-bs-target');
        if (t && t !== '#') return t.trim();
        const href = trigger.getAttribute('href');
        if (!href || href === '#') return null;
        if (href.startsWith('#') || href.startsWith('.')) return href.trim();
        try {
            const u = new URL(href, window.location.href);
            return u.hash || null;
        } catch (e) {
            return null;
        }
    };
    const targetFrom = (trigger) => {
        const s = selFrom(trigger);
        if (!s) return null;
        try {
            return document.querySelector(s);
        } catch (e) {
            return null;
        }
    };
    const onceReady = (fn) => {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
        else fn();
    };

    class Base {
        constructor(el, name) {
            this._el = el;
            this._name = name;
            getStore(name).set(el, this);
        }
        dispose() {
            if (this._el) getStore(this._name).delete(this._el);
            this._el = null;
        }
        static getInstance(el) {
            return el ? (getStore(this.NAME).get(el) || null) : null;
        }
        static getOrCreateInstance(el, cfg) {
            return this.getInstance(el) || new this(el, cfg);
        }
    }

    class Alert extends Base {
        static NAME = 'Alert';
        constructor(el) { super(el, Alert.NAME); }
        close() {
            if (!this._el) return;
            const ev = emit(this._el, 'close.bs.alert');
            if (ev && ev.defaultPrevented) return;
            const el = this._el;
            this.dispose();
            el.remove();
            emit(el, 'closed.bs.alert');
        }
    }

    class Collapse extends Base {
        static NAME = 'Collapse';
        constructor(el, cfg) {
            super(el, Collapse.NAME);
            cfg = cfg || {};
            this._toggleOnInit = cfg.toggle !== undefined ? !!cfg.toggle : true;
            this._parent = cfg.parent || el.getAttribute('data-bs-parent') || null;
            this._shown = el.classList.contains('show');
            this._syncTriggers();
            if (this._toggleOnInit) this.toggle();
        }
        _syncTriggers() {
            const triggers = document.querySelectorAll('[data-bs-toggle="collapse"]');
            triggers.forEach((t) => {
                if (targetFrom(t) !== this._el) return;
                t.classList.toggle('collapsed', !this._shown);
                t.setAttribute('aria-expanded', this._shown ? 'true' : 'false');
            });
        }
        _closeSiblings() {
            if (!this._parent) return;
            const parent = document.querySelector(this._parent);
            if (!parent) return;
            parent.querySelectorAll('.collapse.show').forEach((node) => {
                if (node === this._el) return;
                Collapse.getOrCreateInstance(node, { toggle: false }).hide();
            });
        }
        show() {
            if (!this._el || this._shown) return;
            const ev = emit(this._el, 'show.bs.collapse');
            if (ev && ev.defaultPrevented) return;
            this._closeSiblings();
            this._el.classList.add('show');
            this._shown = true;
            this._syncTriggers();
            emit(this._el, 'shown.bs.collapse');
        }
        hide() {
            if (!this._el || !this._shown) return;
            const ev = emit(this._el, 'hide.bs.collapse');
            if (ev && ev.defaultPrevented) return;
            this._el.classList.remove('show');
            this._shown = false;
            this._syncTriggers();
            emit(this._el, 'hidden.bs.collapse');
        }
        toggle() { this._shown ? this.hide() : this.show(); }
    }

    class Modal extends Base {
        static NAME = 'Modal';
        static _open = 0;
        constructor(el, cfg) {
            super(el, Modal.NAME);
            cfg = cfg || {};
            const b = el.getAttribute('data-bs-backdrop');
            this._backdropMode = cfg.backdrop !== undefined ? cfg.backdrop : (b === 'static' ? 'static' : b == null ? true : toBool(b, true));
            this._keyboard = cfg.keyboard !== undefined ? !!cfg.keyboard : toBool(el.getAttribute('data-bs-keyboard'), true);
            this._shown = el.classList.contains('show');
            this._backdrop = null;
            this._onKey = (e) => { if (e.key === 'Escape' && this._keyboard) this.hide(); };
            this._onMouseDown = (e) => {
                if (e.target !== this._el) return;
                if (this._backdropMode === 'static') return;
                this.hide();
            };
        }
        show() {
            if (!this._el || this._shown) return;
            const ev = emit(this._el, 'show.bs.modal');
            if (ev && ev.defaultPrevented) return;
            this._shown = true;
            this._el.style.display = 'block';
            this._el.classList.add('show');
            this._el.removeAttribute('aria-hidden');
            this._el.setAttribute('aria-modal', 'true');
            if (this._backdropMode) {
                this._backdrop = document.createElement('div');
                this._backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(this._backdrop);
            }
            this._el.addEventListener('mousedown', this._onMouseDown);
            document.addEventListener('keydown', this._onKey);
            Modal._open += 1;
            document.body.classList.add('modal-open');
            emit(this._el, 'shown.bs.modal');
        }
        hide() {
            if (!this._el || !this._shown) return;
            const ev = emit(this._el, 'hide.bs.modal');
            if (ev && ev.defaultPrevented) return;
            this._shown = false;
            this._el.classList.remove('show');
            this._el.style.display = 'none';
            this._el.setAttribute('aria-hidden', 'true');
            this._el.removeAttribute('aria-modal');
            this._el.removeEventListener('mousedown', this._onMouseDown);
            document.removeEventListener('keydown', this._onKey);
            if (this._backdrop) {
                this._backdrop.remove();
                this._backdrop = null;
            }
            Modal._open = Math.max(0, Modal._open - 1);
            if (Modal._open === 0) document.body.classList.remove('modal-open');
            emit(this._el, 'hidden.bs.modal');
        }
        toggle() { this._shown ? this.hide() : this.show(); }
    }

    class Offcanvas extends Base {
        static NAME = 'Offcanvas';
        constructor(el, cfg) {
            super(el, Offcanvas.NAME);
            cfg = cfg || {};
            this._shown = el.classList.contains('show');
            this._backdropEnabled = cfg.backdrop !== undefined ? !!cfg.backdrop : toBool(el.getAttribute('data-bs-backdrop'), true);
            this._keyboard = cfg.keyboard !== undefined ? !!cfg.keyboard : toBool(el.getAttribute('data-bs-keyboard'), true);
            this._backdrop = null;
            this._onKey = (e) => { if (e.key === 'Escape' && this._keyboard) this.hide(); };
        }
        show() {
            if (!this._el || this._shown) return;
            const ev = emit(this._el, 'show.bs.offcanvas');
            if (ev && ev.defaultPrevented) return;
            this._shown = true;
            this._el.classList.add('show');
            this._el.removeAttribute('aria-hidden');
            this._el.setAttribute('aria-modal', 'true');
            if (this._backdropEnabled) {
                this._backdrop = document.createElement('div');
                this._backdrop.className = 'offcanvas-backdrop fade show';
                this._backdrop.addEventListener('click', () => this.hide());
                document.body.appendChild(this._backdrop);
            }
            document.addEventListener('keydown', this._onKey);
            emit(this._el, 'shown.bs.offcanvas');
        }
        hide() {
            if (!this._el || !this._shown) return;
            const ev = emit(this._el, 'hide.bs.offcanvas');
            if (ev && ev.defaultPrevented) return;
            this._shown = false;
            this._el.classList.remove('show');
            this._el.setAttribute('aria-hidden', 'true');
            this._el.removeAttribute('aria-modal');
            document.removeEventListener('keydown', this._onKey);
            if (this._backdrop) {
                this._backdrop.remove();
                this._backdrop = null;
            }
            emit(this._el, 'hidden.bs.offcanvas');
        }
        toggle() { this._shown ? this.hide() : this.show(); }
    }

    class Toast extends Base {
        static NAME = 'Toast';
        constructor(el, cfg) {
            super(el, Toast.NAME);
            cfg = cfg || {};
            this._shown = el.classList.contains('show');
            this._autohide = cfg.autohide !== undefined ? !!cfg.autohide : toBool(el.getAttribute('data-bs-autohide'), true);
            this._delay = cfg.delay !== undefined ? toNum(cfg.delay, 5000) : toNum(el.getAttribute('data-bs-delay'), 5000);
            this._timer = null;
        }
        show() {
            if (!this._el) return;
            const ev = emit(this._el, 'show.bs.toast');
            if (ev && ev.defaultPrevented) return;
            this._shown = true;
            this._el.classList.add('show');
            this._el.classList.remove('hide');
            emit(this._el, 'shown.bs.toast');
            if (this._autohide) {
                clearTimeout(this._timer);
                this._timer = setTimeout(() => this.hide(), Math.max(0, this._delay));
            }
        }
        hide() {
            if (!this._el || !this._shown) return;
            const ev = emit(this._el, 'hide.bs.toast');
            if (ev && ev.defaultPrevented) return;
            this._shown = false;
            this._el.classList.remove('show');
            this._el.classList.add('hide');
            clearTimeout(this._timer);
            this._timer = null;
            emit(this._el, 'hidden.bs.toast');
        }
        dispose() {
            clearTimeout(this._timer);
            this._timer = null;
            super.dispose();
        }
    }

    class Carousel extends Base {
        static NAME = 'Carousel';
        constructor(el, cfg) {
            super(el, Carousel.NAME);
            cfg = cfg || {};
            this._items = Array.from(el.querySelectorAll('.carousel-item'));
            this._idx = this._items.findIndex((n) => n.classList.contains('active'));
            if (this._idx < 0) this._idx = 0;
            this._interval = cfg.interval !== undefined ? toNum(cfg.interval, 5000) : toNum(el.getAttribute('data-bs-interval'), 5000);
            this._wrap = cfg.wrap !== undefined ? !!cfg.wrap : toBool(el.getAttribute('data-bs-wrap'), true);
            this._pause = cfg.pause !== undefined ? cfg.pause : (el.getAttribute('data-bs-pause') || 'hover');
            this._ride = cfg.ride !== undefined ? cfg.ride : (el.getAttribute('data-bs-ride') || false);
            this._timer = null;
            if (cfg.keyboard !== false && toBool(el.getAttribute('data-bs-keyboard'), true)) {
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') this.prev();
                    if (e.key === 'ArrowRight') this.next();
                });
            }
            if (this._pause === 'hover') {
                el.addEventListener('mouseenter', () => this.pause());
                el.addEventListener('mouseleave', () => this.cycle());
            }
            if (this._ride === 'carousel') this.cycle();
        }
        _setIndicators() {
            this._el.querySelectorAll('[data-bs-slide-to]').forEach((n) => {
                const on = toNum(n.getAttribute('data-bs-slide-to'), -1) === this._idx;
                n.classList.toggle('active', on);
                if (on) n.setAttribute('aria-current', 'true');
                else n.removeAttribute('aria-current');
            });
        }
        _go(next) {
            if (!this._items.length || next === this._idx || next < 0 || next >= this._items.length) return;
            const ev = emit(this._el, 'slide.bs.carousel', { from: this._idx, to: next });
            if (ev && ev.defaultPrevented) return;
            this._items[this._idx].classList.remove('active');
            this._items[next].classList.add('active');
            this._idx = next;
            this._setIndicators();
            emit(this._el, 'slid.bs.carousel', { to: next });
        }
        next() {
            let n = this._idx + 1;
            if (n >= this._items.length) n = this._wrap ? 0 : this._items.length - 1;
            this._go(n);
        }
        prev() {
            let n = this._idx - 1;
            if (n < 0) n = this._wrap ? this._items.length - 1 : 0;
            this._go(n);
        }
        to(i) { this._go(toNum(i, this._idx)); }
        pause() {
            clearInterval(this._timer);
            this._timer = null;
        }
        cycle() {
            this.pause();
            if (this._interval > 0) this._timer = setInterval(() => this.next(), this._interval);
        }
        dispose() {
            this.pause();
            super.dispose();
        }
    }

    class Dropdown extends Base {
        static NAME = 'Dropdown';
        static _open = null;
        constructor(el) {
            super(el, Dropdown.NAME);
            this._menu = (el.closest('.dropdown') || el.parentElement)?.querySelector('.dropdown-menu') || null;
            this._shown = !!(this._menu && this._menu.classList.contains('show'));
        }
        show() {
            if (!this._menu || this._shown) return;
            if (Dropdown._open && Dropdown._open !== this) Dropdown._open.hide();
            const ev = emit(this._el, 'show.bs.dropdown');
            if (ev && ev.defaultPrevented) return;
            this._shown = true;
            this._menu.classList.add('show');
            this._el.classList.add('show');
            this._el.closest('.dropdown')?.classList.add('show');
            this._el.setAttribute('aria-expanded', 'true');
            Dropdown._open = this;
            emit(this._el, 'shown.bs.dropdown');
        }
        hide() {
            if (!this._menu || !this._shown) return;
            const ev = emit(this._el, 'hide.bs.dropdown');
            if (ev && ev.defaultPrevented) return;
            this._shown = false;
            this._menu.classList.remove('show');
            this._el.classList.remove('show');
            this._el.closest('.dropdown')?.classList.remove('show');
            this._el.setAttribute('aria-expanded', 'false');
            if (Dropdown._open === this) Dropdown._open = null;
            emit(this._el, 'hidden.bs.dropdown');
        }
        toggle() { this._shown ? this.hide() : this.show(); }
        static clear(e) {
            const d = Dropdown._open;
            if (!d || !d._menu) return;
            const t = e && e.target;
            if (t instanceof Element) {
                if (d._el.contains(t)) return;
                if (d._menu.contains(t) && !t.closest('.dropdown-item,[data-bs-dismiss="dropdown"]')) return;
            }
            d.hide();
        }
    }

    class Tooltip extends Base {
        static NAME = 'Tooltip';
        constructor(el) { super(el, Tooltip.NAME); }
        show() { if (this._el) { emit(this._el, 'show.bs.tooltip'); emit(this._el, 'shown.bs.tooltip'); } }
        hide() { if (this._el) { emit(this._el, 'hide.bs.tooltip'); emit(this._el, 'hidden.bs.tooltip'); } }
    }

    class Tab extends Base {
        static NAME = 'Tab';
        constructor(el) { super(el, Tab.NAME); }
        show() {
            if (!this._el) return;
            const list = this._el.closest('.nav, .list-group, [role="tablist"]');
            const group = list ? Array.from(list.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]')) : [this._el];
            const prev = group.find((n) => n.classList.contains('active')) || null;
            if (prev === this._el) return;
            const prevPane = prev ? targetFrom(prev) : null;
            const nextPane = targetFrom(this._el);
            const hideEv = prev ? emit(prev, 'hide.bs.tab', { relatedTarget: this._el }) : null;
            if (hideEv && hideEv.defaultPrevented) return;
            const showEv = emit(this._el, 'show.bs.tab', { relatedTarget: prev });
            if (showEv && showEv.defaultPrevented) return;
            if (prev) {
                prev.classList.remove('active');
                prev.setAttribute('aria-selected', 'false');
            }
            if (prevPane) {
                prevPane.classList.remove('active', 'show');
                prevPane.setAttribute('aria-hidden', 'true');
            }
            this._el.classList.add('active');
            this._el.setAttribute('aria-selected', 'true');
            if (nextPane) {
                nextPane.classList.add('active');
                if (nextPane.classList.contains('fade')) nextPane.classList.add('show');
                nextPane.setAttribute('aria-hidden', 'false');
            }
            if (prev) emit(prev, 'hidden.bs.tab', { relatedTarget: this._el });
            emit(this._el, 'shown.bs.tab', { relatedTarget: prev });
        }
    }

    const ns = window.bootstrap || {};
    ns.Alert = Alert;
    ns.Collapse = Collapse;
    ns.Modal = Modal;
    ns.Offcanvas = Offcanvas;
    ns.Toast = Toast;
    ns.Carousel = Carousel;
    ns.Dropdown = Dropdown;
    ns.Tooltip = Tooltip;
    ns.Tab = Tab;
    ns.__BROX_LITE__ = true;
    window.bootstrap = ns;

    if (!document.__BROX_LITE_DATA_API) {
        document.__BROX_LITE_DATA_API = true;

        document.addEventListener('click', (e) => {
            const t = e.target instanceof Element ? e.target : null;
            if (!t) return;

            const dismiss = t.closest('[data-bs-dismiss]');
            if (dismiss) {
                const kind = dismiss.getAttribute('data-bs-dismiss');
                if (kind === 'alert') {
                    const el = dismiss.closest('.alert');
                    if (el) Alert.getOrCreateInstance(el).close();
                    e.preventDefault();
                    return;
                }
                if (kind === 'modal') {
                    const el = dismiss.closest('.modal');
                    if (el) Modal.getOrCreateInstance(el).hide();
                    e.preventDefault();
                    return;
                }
                if (kind === 'toast') {
                    const el = dismiss.closest('.toast');
                    if (el) Toast.getOrCreateInstance(el).hide();
                    e.preventDefault();
                    return;
                }
                if (kind === 'offcanvas') {
                    const el = dismiss.closest('.offcanvas');
                    if (el) Offcanvas.getOrCreateInstance(el).hide();
                    e.preventDefault();
                    return;
                }
            }

            const m = t.closest('[data-bs-toggle="modal"]');
            if (m) {
                const el = targetFrom(m);
                if (el) {
                    Modal.getOrCreateInstance(el).show();
                    e.preventDefault();
                }
                return;
            }

            const c = t.closest('[data-bs-toggle="collapse"]');
            if (c) {
                const el = targetFrom(c);
                if (el) {
                    Collapse.getOrCreateInstance(el).toggle();
                    e.preventDefault();
                }
                return;
            }

            const d = t.closest('[data-bs-toggle="dropdown"]');
            if (d) {
                Dropdown.getOrCreateInstance(d).toggle();
                e.preventDefault();
                return;
            }

            const tab = t.closest('[data-bs-toggle="tab"], [data-bs-toggle="pill"]');
            if (tab) {
                Tab.getOrCreateInstance(tab).show();
                e.preventDefault();
                return;
            }

            const o = t.closest('[data-bs-toggle="offcanvas"]');
            if (o) {
                const el = targetFrom(o);
                if (el) {
                    Offcanvas.getOrCreateInstance(el).toggle();
                    e.preventDefault();
                }
                return;
            }

            const cr = t.closest('[data-bs-slide], [data-bs-slide-to]');
            if (cr) {
                const el = targetFrom(cr) || cr.closest('.carousel');
                if (!el) return;
                const ins = Carousel.getOrCreateInstance(el);
                if (cr.hasAttribute('data-bs-slide-to')) {
                    ins.to(cr.getAttribute('data-bs-slide-to'));
                } else if (cr.getAttribute('data-bs-slide') === 'prev') {
                    ins.prev();
                } else {
                    ins.next();
                }
                e.preventDefault();
            }
        });

        document.addEventListener('click', (e) => Dropdown.clear(e));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') Dropdown.clear(e); });

        onceReady(() => {
            document.querySelectorAll('[data-bs-ride="carousel"]').forEach((el) => {
                Carousel.getOrCreateInstance(el, { ride: 'carousel' });
            });
        });
    }
})();
