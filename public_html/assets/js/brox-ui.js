/**
 * brox-ui.js — Lightweight UI component engine (zero Bootstrap dependencies)
 * ES Module — exports default broxUI, keeps window.broxUI for backward compat.
 * Zero Bootstrap dependencies. Uses data-brox-* attributes.
 */
'use strict';

const stores = Object.create(null);
function getStore(name) { return stores[name] || (stores[name] = new WeakMap()); }
function toBool(v, d) { d = d === undefined ? false : d; return v == null ? d : String(v).toLowerCase() === 'true'; }
function toNum(v, d) { d = d === undefined ? 0 : d; return Number.isFinite(Number(v)) ? Number(v) : d; }
function emit(el, name, detail) {
  if (!el) return null;
  const ev = new CustomEvent(`brox:${name}`, { bubbles: true, cancelable: true, detail: detail || {}, });
  el.dispatchEvent(ev);
  return ev;
}
function selFrom(trigger, attr) {
  attr = attr || 'data-brox-target';
  if (!trigger) return null;
  const t = trigger.getAttribute(attr);
  if (t && t !== '#') return t.trim();
  const href = trigger.getAttribute('href');
  if (!href || href === '#') return null;
  if (href.startsWith('#') || href.startsWith('.')) return href.trim();
  try { const u = new URL(href, window.location.href); return u.hash || null; } catch (e) { return null; }
}
function targetFrom(trigger, attr) {
  const s = selFrom(trigger, attr);
  if (!s) return null;
  try { return document.querySelector(s); } catch (e) { return null; }
}
function onceReady(fn) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true, });
  else fn();
}

function Base(el, name) {
  this._el = el;
  this._name = name;
  getStore(name).set(el, this);
}
Base.prototype.dispose = function () { if (this._el) getStore(this._name).delete(this._el); this._el = null; };
Base.getInstance = function (el) { return el ? (getStore(this.NAME).get(el) || null) : null; };
Base.getOrCreateInstance = function (el, cfg) { return this.getInstance(el) || new this(el, cfg); };

function Alert(el) { Base.call(this, el, 'Alert'); }
Alert.prototype = Object.create(Base.prototype);
Alert.prototype.constructor = Alert;
Alert.NAME = 'Alert';
Alert.prototype.close = function () {
  if (!this._el) return;
  if (emit(this._el, 'close').defaultPrevented) return;
  const el = this._el; this.dispose(); el.remove(); emit(el, 'closed');
};
Alert.dismiss = function (el) { Alert.getOrCreateInstance(el).close(); };

function Collapse(el, cfg) {
  Base.call(this, el, 'Collapse');
  cfg = cfg || {};
  this._toggleOnInit = cfg.toggle !== undefined ? Boolean(cfg.toggle) : false;
  this._parent = cfg.parent || el.getAttribute('data-brox-parent') || null;
  this._shown = el.classList.contains('show');
  this._syncTriggers();
  if (this._toggleOnInit) this.toggle();
}
Collapse.prototype = Object.create(Base.prototype);
Collapse.prototype.constructor = Collapse;
Collapse.NAME = 'Collapse';
Collapse.prototype._syncTriggers = function () {
  const self = this;
  document.querySelectorAll('[data-brox-toggle="collapse"]').forEach((t) => {
    if (targetFrom(t) !== self._el) return;
    t.classList.toggle('collapsed', !self._shown);
    t.setAttribute('aria-expanded', self._shown ? 'true' : 'false');
  });
};
Collapse.prototype._closeSiblings = function () {
  if (!this._parent) return;
  const parent = document.querySelector(this._parent);
  if (!parent) return;
  const self = this;
  parent.querySelectorAll('.collapse.show').forEach((node) => {
    if (node === self._el) return;
    Collapse.getOrCreateInstance(node, { toggle: false, }).hide();
  });
};
Collapse.prototype.show = function () {
  if (!this._el || this._shown) return;
  if (emit(this._el, 'show').defaultPrevented) return;
  this._closeSiblings();
  this._el.classList.add('show');
  this._shown = true;
  this._syncTriggers();
  emit(this._el, 'shown');
};
Collapse.prototype.hide = function () {
  if (!this._el || !this._shown) return;
  if (emit(this._el, 'hide').defaultPrevented) return;
  this._el.classList.remove('show');
  this._shown = false;
  this._syncTriggers();
  emit(this._el, 'hidden');
};
Collapse.prototype.toggle = function () { this._shown ? this.hide() : this.show(); };

function Modal(el, cfg) {
  Base.call(this, el, 'Modal');
  cfg = cfg || {};
  const b = el.getAttribute('data-brox-backdrop');
  this._backdropMode = cfg.backdrop !== undefined ? cfg.backdrop : (b === 'static' ? 'static' : b == null ? true : toBool(b, true));
  this._keyboard = cfg.keyboard !== undefined ? Boolean(cfg.keyboard) : toBool(el.getAttribute('data-brox-keyboard'), true);
  this._shown = el.classList.contains('show');
  this._backdrop = null;
  const self = this;
  this._onKey = function (e) { if (e.key === 'Escape' && self._keyboard) self.hide(); };
  this._onMouseDown = function (e) {
    if (e.target !== self._el) return;
    if (self._backdropMode === 'static') return;
    self.hide();
  };
}
Modal.prototype = Object.create(Base.prototype);
Modal.prototype.constructor = Modal;
Modal.NAME = 'Modal';
Modal._open = 0;
Modal.prototype.show = function () {
  if (!this._el || this._shown) return;
  if (emit(this._el, 'show').defaultPrevented) return;
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
  emit(this._el, 'shown');
};
Modal.prototype.hide = function () {
  if (!this._el || !this._shown) return;
  if (emit(this._el, 'hide').defaultPrevented) return;
  this._shown = false;
  this._el.classList.remove('show');
  this._el.style.display = 'none';
  this._el.setAttribute('aria-hidden', 'true');
  this._el.removeAttribute('aria-modal');
  this._el.removeEventListener('mousedown', this._onMouseDown);
  document.removeEventListener('keydown', this._onKey);
  if (this._backdrop) { this._backdrop.remove(); this._backdrop = null; }
  Modal._open = Math.max(0, Modal._open - 1);
  if (Modal._open === 0) document.body.classList.remove('modal-open');
  emit(this._el, 'hidden');
};
Modal.prototype.toggle = function () { this._shown ? this.hide() : this.show(); };

function Offcanvas(el, cfg) {
  Base.call(this, el, 'Offcanvas');
  cfg = cfg || {};
  this._shown = el.classList.contains('show');
  this._backdropEnabled = cfg.backdrop !== undefined ? Boolean(cfg.backdrop) : toBool(el.getAttribute('data-brox-backdrop'), true);
  this._keyboard = cfg.keyboard !== undefined ? Boolean(cfg.keyboard) : toBool(el.getAttribute('data-brox-keyboard'), true);
  this._backdrop = null;
  const self = this;
  this._onKey = function (e) { if (e.key === 'Escape' && self._keyboard) self.hide(); };
}
Offcanvas.prototype = Object.create(Base.prototype);
Offcanvas.prototype.constructor = Offcanvas;
Offcanvas.NAME = 'Offcanvas';
Offcanvas.prototype.show = function () {
  if (!this._el || this._shown) return;
  if (emit(this._el, 'show').defaultPrevented) return;
  this._shown = true;
  this._el.classList.add('show');
  this._el.removeAttribute('aria-hidden');
  this._el.setAttribute('aria-modal', 'true');
  if (this._backdropEnabled) {
    this._backdrop = document.createElement('div');
    this._backdrop.className = 'offcanvas-backdrop fade show';
    const self = this;
    this._backdrop.addEventListener('click', () => { self.hide(); });
    document.body.appendChild(this._backdrop);
  }
  document.addEventListener('keydown', this._onKey);
  emit(this._el, 'shown');
};
Offcanvas.prototype.hide = function () {
  if (!this._el || !this._shown) return;
  if (emit(this._el, 'hide').defaultPrevented) return;
  this._shown = false;
  this._el.classList.remove('show');
  this._el.setAttribute('aria-hidden', 'true');
  this._el.removeAttribute('aria-modal');
  document.removeEventListener('keydown', this._onKey);
  if (this._backdrop) { this._backdrop.remove(); this._backdrop = null; }
  emit(this._el, 'hidden');
};
Offcanvas.prototype.toggle = function () { this._shown ? this.hide() : this.show(); };

function Toast(el, cfg) {
  Base.call(this, el, 'Toast');
  cfg = cfg || {};
  this._shown = el.classList.contains('show');
  this._autohide = cfg.autohide !== undefined ? Boolean(cfg.autohide) : toBool(el.getAttribute('data-brox-autohide'), true);
  this._delay = cfg.delay !== undefined ? toNum(cfg.delay, 5000) : toNum(el.getAttribute('data-brox-delay'), 5000);
  this._timer = null;
}
Toast.prototype = Object.create(Base.prototype);
Toast.prototype.constructor = Toast;
Toast.NAME = 'Toast';
Toast.prototype.show = function () {
  if (!this._el) return;
  if (emit(this._el, 'show').defaultPrevented) return;
  this._shown = true;
  this._el.classList.add('show');
  this._el.classList.remove('hide');
  emit(this._el, 'shown');
  if (this._autohide) {
    const self = this;
    clearTimeout(this._timer);
    this._timer = setTimeout(() => { self.hide(); }, Math.max(0, this._delay));
  }
};
Toast.prototype.hide = function () {
  if (!this._el || !this._shown) return;
  if (emit(this._el, 'hide').defaultPrevented) return;
  this._shown = false;
  this._el.classList.remove('show');
  this._el.classList.add('hide');
  clearTimeout(this._timer);
  this._timer = null;
  emit(this._el, 'hidden');
};
Toast.prototype.dispose = function () { clearTimeout(this._timer); this._timer = null; Base.prototype.dispose.call(this); };

function Carousel(el, cfg) {
  Base.call(this, el, 'Carousel');
  cfg = cfg || {};
  this._items = Array.from(el.querySelectorAll('.carousel-item'));
  this._idx = this._items.findIndex((n) => { return n.classList.contains('active'); });
  if (this._idx < 0) this._idx = 0;
  this._interval = cfg.interval !== undefined ? toNum(cfg.interval, 5000) : toNum(el.getAttribute('data-brox-interval'), 5000);
  this._wrap = cfg.wrap !== undefined ? Boolean(cfg.wrap) : toBool(el.getAttribute('data-brox-wrap'), true);
  this._pause = cfg.pause !== undefined ? cfg.pause : (el.getAttribute('data-brox-pause') || 'hover');
  this._ride = cfg.ride !== undefined ? cfg.ride : (el.getAttribute('data-brox-ride') || false);
  this._timer = null;
  const self = this;
  if (cfg.keyboard !== false && toBool(el.getAttribute('data-brox-keyboard'), true)) {
    el.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') self.prev();
      if (e.key === 'ArrowRight') self.next();
    });
  }
  if (this._pause === 'hover') {
    el.addEventListener('mouseenter', () => { self.pause(); });
    el.addEventListener('mouseleave', () => { self.cycle(); });
  }
  if (this._ride === 'carousel') this.cycle();
}
Carousel.prototype = Object.create(Base.prototype);
Carousel.prototype.constructor = Carousel;
Carousel.NAME = 'Carousel';
Carousel.prototype._setIndicators = function () {
  const self = this;
  this._el.querySelectorAll('[data-brox-slide-to]').forEach((n) => {
    const on = toNum(n.getAttribute('data-brox-slide-to'), -1) === self._idx;
    n.classList.toggle('active', on);
    if (on) n.setAttribute('aria-current', 'true');
    else n.removeAttribute('aria-current');
  });
};
Carousel.prototype._go = function (next) {
  if (!this._items.length || next === this._idx || next < 0 || next >= this._items.length) return;
  if (emit(this._el, 'slide', { from: this._idx, to: next, }).defaultPrevented) return;
  this._items[this._idx].classList.remove('active');
  this._items[next].classList.add('active');
  this._idx = next;
  this._setIndicators();
  emit(this._el, 'slid', { to: next, });
};
Carousel.prototype.next = function () {
  let n = this._idx + 1;
  if (n >= this._items.length) n = this._wrap ? 0 : this._items.length - 1;
  this._go(n);
};
Carousel.prototype.prev = function () {
  let n = this._idx - 1;
  if (n < 0) n = this._wrap ? this._items.length - 1 : 0;
  this._go(n);
};
Carousel.prototype.to = function (i) { this._go(toNum(i, this._idx)); };
Carousel.prototype.pause = function () { clearInterval(this._timer); this._timer = null; };
Carousel.prototype.cycle = function () { this.pause(); if (this._interval > 0) { const self = this; this._timer = setInterval(() => { self.next(); }, this._interval); } };
Carousel.prototype.dispose = function () { this.pause(); Base.prototype.dispose.call(this); };

function Dropdown(el) {
  Base.call(this, el, 'Dropdown');
  this._menu = (el.closest('.dropdown') || el.parentElement) ? (el.closest('.dropdown') || el.parentElement).querySelector('.dropdown-menu') || null : null;
  this._shown = Boolean(this._menu && this._menu.classList.contains('show'));
}
Dropdown.prototype = Object.create(Base.prototype);
Dropdown.prototype.constructor = Dropdown;
Dropdown.NAME = 'Dropdown';
Dropdown._open = null;
Dropdown.prototype.show = function () {
  if (!this._menu || this._shown) return;
  if (Dropdown._open && Dropdown._open !== this) Dropdown._open.hide();
  if (emit(this._el, 'show').defaultPrevented) return;
  this._shown = true;
  this._menu.classList.add('show');
  this._el.classList.add('show');
  const dd = this._el.closest('.dropdown');
  if (dd) dd.classList.add('show');
  this._el.setAttribute('aria-expanded', 'true');
  Dropdown._open = this;
  emit(this._el, 'shown');
};
Dropdown.prototype.hide = function () {
  if (!this._menu || !this._shown) return;
  if (emit(this._el, 'hide').defaultPrevented) return;
  this._shown = false;
  this._menu.classList.remove('show');
  this._el.classList.remove('show');
  const dd = this._el.closest('.dropdown');
  if (dd) dd.classList.remove('show');
  this._el.setAttribute('aria-expanded', 'false');
  if (Dropdown._open === this) Dropdown._open = null;
  emit(this._el, 'hidden');
};
Dropdown.prototype.toggle = function () { this._shown ? this.hide() : this.show(); };
Dropdown.clear = function (e) {
  const d = Dropdown._open;
  if (!d || !d._menu) return;
  const t = e && e.target;
  if (t instanceof Element) {
    if (d._el.contains(t)) return;
    if (d._menu.contains(t) && !t.closest('.dropdown-item,[data-brox-dismiss="dropdown"]')) return;
  }
  d.hide();
};

function Tab(el) { Base.call(this, el, 'Tab'); }
Tab.prototype = Object.create(Base.prototype);
Tab.prototype.constructor = Tab;
Tab.NAME = 'Tab';
Tab.prototype.show = function () {
  if (!this._el) return;
  const list = this._el.closest('.nav, .list-group, [role="tablist"]');
  const group = list ? Array.from(list.querySelectorAll('[data-brox-toggle="tab"], [data-brox-toggle="pill"]')) : [this._el,];
  const prev = group.find((n) => { return n.classList.contains('active'); }) || null;
  if (prev === this._el) return;
  const prevPane = prev ? targetFrom(prev) : null;
  const nextPane = targetFrom(this._el);
  const hideEv = prev ? emit(prev, 'hide', { relatedTarget: this._el, }) : null;
  if (hideEv && hideEv.defaultPrevented) return;
  if (emit(this._el, 'show', { relatedTarget: prev, }).defaultPrevented) return;
  if (prev) { prev.classList.remove('active'); prev.setAttribute('aria-selected', 'false'); }
  if (prevPane) { prevPane.classList.remove('active', 'show'); prevPane.setAttribute('aria-hidden', 'true'); }
  this._el.classList.add('active');
  this._el.setAttribute('aria-selected', 'true');
  if (nextPane) {
    nextPane.classList.add('active');
    if (nextPane.classList.contains('fade')) nextPane.classList.add('show');
    nextPane.setAttribute('aria-hidden', 'false');
  }
  if (prev) emit(prev, 'hidden', { relatedTarget: this._el, });
  emit(this._el, 'shown', { relatedTarget: prev, });
};

function Tooltip(el) { Base.call(this, el, 'Tooltip'); }
Tooltip.prototype = Object.create(Base.prototype);
Tooltip.prototype.constructor = Tooltip;
Tooltip.NAME = 'Tooltip';
Tooltip.prototype.show = function () { if (this._el) { emit(this._el, 'show'); emit(this._el, 'shown'); } };
Tooltip.prototype.hide = function () { if (this._el) { emit(this._el, 'hide'); emit(this._el, 'hidden'); } };

// Ensure static helpers from Base are available on each component constructor
[Alert, Collapse, Modal, Offcanvas, Toast, Carousel, Dropdown, Tab, Tooltip,].forEach((C) => {
  if (C) {
    C.getInstance = Base.getInstance;
    C.getOrCreateInstance = Base.getOrCreateInstance;
  }
});

// Public API
const broxUI = {
  Alert: Alert, Collapse: Collapse, Modal: Modal, Offcanvas: Offcanvas,
  Toast: Toast, Carousel: Carousel, Dropdown: Dropdown, Tab: Tab, Tooltip: Tooltip,
  __BROX_UI__: true,
};
window.broxUI = broxUI;

// Data API — Tailwind + vanilla JS only
const dismissTypes = {
  alert: function (el) { const p = el.closest('.alert'); if (p) Alert.dismiss(p); },
  modal: function (el) { const p = el.closest('.modal'); if (p) Modal.getOrCreateInstance(p).hide(); },
  toast: function (el) { const p = el.closest('.toast'); if (p) Toast.getOrCreateInstance(p).hide(); },
  offcanvas: function (el) { const p = el.closest('.offcanvas'); if (p) Offcanvas.getOrCreateInstance(p).hide(); },
};

document.addEventListener('click', (e) => {
  const t = e.target instanceof Element ? e.target : null;
  if (!t) return;

  // ── data-brox-dismiss ──
  const dismiss = t.closest('[data-brox-dismiss]');
  if (dismiss) {
    const kind = dismiss.getAttribute('data-brox-dismiss');
    if (kind && dismissTypes[kind]) { dismissTypes[kind](dismiss); e.preventDefault(); return; }
  }

  // ── data-brox-toggle ──
  const toggle = t.closest('[data-brox-toggle]');
  if (toggle) {
    const kind = toggle.getAttribute('data-brox-toggle');
    const targetAttr = toggle.hasAttribute('data-brox-target') ? 'data-brox-target' : null;
    if (kind === 'dropdown') { Dropdown.getOrCreateInstance(toggle).toggle(); e.preventDefault(); return; }
    if (kind === 'tab' || kind === 'pill') { Tab.getOrCreateInstance(toggle).show(); e.preventDefault(); return; }
    if (kind === 'collapse') {
      const el = targetFrom(toggle, targetAttr);
      if (el) { Collapse.getOrCreateInstance(el).toggle(); e.preventDefault(); }
      return;
    }
    if (kind === 'modal') {
      const el = targetFrom(toggle, targetAttr);
      if (el) { Modal.getOrCreateInstance(el).show(); e.preventDefault(); }
      return;
    }
    if (kind === 'offcanvas') {
      const el = targetFrom(toggle, targetAttr);
      if (el) { Offcanvas.getOrCreateInstance(el).toggle(); e.preventDefault(); }
      return;
    }
  }

  // ── data-brox-slide-to / data-brox-slide ──
  const carouselBtn = t.closest('[data-brox-slide-to],[data-brox-slide]');
  if (carouselBtn) {
    const el = targetFrom(carouselBtn, carouselBtn.hasAttribute('data-brox-target') ? 'data-brox-target' : null) || carouselBtn.closest('.carousel');
    if (!el) return;
    const ins = Carousel.getOrCreateInstance(el);
    if (carouselBtn.hasAttribute('data-brox-slide-to')) {
      ins.to(carouselBtn.getAttribute('data-brox-slide-to'));
    } else {
      const slideDir = carouselBtn.getAttribute('data-brox-slide') || '';
      if (slideDir === 'prev') { ins.prev(); } else { ins.next(); }
    }
    e.preventDefault();
    return;
  }
});

document.addEventListener('click', (e) => { Dropdown.clear(e); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') Dropdown.clear(e); });

onceReady(() => {
  document.querySelectorAll('[data-brox-ride="carousel"],[data-bs-ride="carousel"]').forEach((el) => {
    Carousel.getOrCreateInstance(el, { ride: 'carousel', });
  });
});

export default broxUI;
