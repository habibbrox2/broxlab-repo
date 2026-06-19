/**
 * Services Dashboard — Modern SaaS Service Grid
 *
 * Features:
 *  - IntersectionObserver reveal animation with staggered delays
 *  - Skeleton loading state → fade in cards
 *  - Card hover glow (mouse tracking)
 *  - Keyboard navigation (arrow keys, Enter/Space)
 *  - Reduced motion support
 *  - Event delegation
 *  - Data attribute driven
 */

const SERVICES_DASHBOARD_SELECTOR = '[data-services-dashboard]';
const SERVICE_CARD_SELECTOR = '[data-service-card]';
const SKELETON_SELECTOR = '[data-services-skeleton]';
const GRID_SELECTOR = '[data-services-grid]';

// ── Services Data (15 cards) ──
const SERVICES = [
  { id: 'file-photo', icon: 'file-image', titleEn: 'File to Photo', titleBn: '\u09ab\u09be\u0987\u09b2 \u09ab\u099f\u09cb', url: '/file-photo', color: '#6366f1', },
  { id: 'income-expense', icon: 'indian-rupee', titleEn: 'Income-Expense', titleBn: '\u0986\u09df-\u09ac\u09cd\u09af\u09df \u09b9\u09bf\u09b8\u09be\u09ac', url: '/income-expense', color: '#10b981', },
  { id: 'image-convert', icon: 'image', titleEn: 'Image Convert', titleBn: '\u0987\u09ae\u09c7\u099c \u09ab\u099f\u09cb \u0995\u09a8\u09ad\u09be\u09b0\u09cd\u099f', url: '/image-convert', color: '#f59e0b', },
  { id: 'whatsapp', icon: 'message-circle', titleEn: 'WhatsApp App', titleBn: '\u09b9\u09cb\u09df\u09be\u099f\u09b8\u0985\u09cd\u09af\u09be\u09aa \u0985\u09cd\u09af\u09be\u09aa', url: '/whatsapp', color: '#25D366', },
  { id: 'advertise', icon: 'megaphone', titleEn: 'My Advertise', titleBn: '\u0986\u09ae\u09be\u09b0 \u09ac\u09bf\u099c\u09cd\u099e\u09be\u09aa\u09a8', url: '/advertise', color: '#ef4444', },
  { id: 'photo-studio', icon: 'camera', titleEn: 'Photo Studio', titleBn: '\u09ab\u099f\u09cb \u09b8\u09cd\u099f\u09c1\u09a1\u09bf\u09df\u09cb', url: '/photo-studio', color: '#8b5cf6', },
  { id: 'teletalk', icon: 'smartphone', titleEn: 'Teletalk Autofill', titleBn: '\u099f\u09c7\u09b2\u09bf\u099f\u0995 \u099c\u09ae\u09be \u0985\u099f\u09cb\u09ab\u09bf\u09b2', url: '/teletalk', color: '#06b6d4', },
  { id: 'nid-print', icon: 'id-card', titleEn: 'NID Print Ready', titleBn: 'NID \u09aa\u09cd\u09b0\u09bf\u09a8\u09cd\u099f \u09b0\u09c7\u09a1\u09bf', url: '/nid-print', color: '#e11d48', },
  { id: 'a4-print', icon: 'file-text', titleEn: 'A4 Print Ready', titleBn: 'A4 \u09aa\u09cd\u09b0\u09bf\u09a8\u09cd\u099f \u09b0\u09c7\u09a1\u09bf', url: '/a4-print', color: '#7c3aed', },
  { id: 'joint-photo', icon: 'users', titleEn: 'Joint Photo', titleBn: '\u09af\u09cc\u09a5 \u099b\u09ac\u09bf', url: '/joint-photo', color: '#ec4899', },
  { id: 'digital-notebook', icon: 'book-open', titleEn: 'Digital Notebook', titleBn: '\u09a1\u09bf\u099c\u09bf\u099f\u09be\u09b2 \u0996\u09be\u09a4\u09be', url: '/digital-notebook', color: '#14b8a6', },
  { id: 'photo-card', icon: 'credit-card', titleEn: 'Photo Card', titleBn: '\u09ab\u099f\u09cb \u0995\u09be\u09b0\u09cd\u09a1', url: '/photo-card', color: '#f97316', },
  { id: 'unicode-bijoy', icon: 'keyboard', titleEn: 'Unicode to Bijoy', titleBn: '\u0987\u0989\u09a8\u09bf\u099f\u09c1 \u09ac\u09bf\u099c\u09df', url: '/unicode-bijoy', color: '#64748b', },
  { id: 'ai-prompt', icon: 'sparkles', titleEn: 'AI Prompt For Photo', titleBn: 'AI Prompt For Photo', url: '/ai-prompt', color: '#a855f7', },
  { id: 'remember', icon: 'heart', titleEn: 'Remember', titleBn: '\u09ae\u09a8\u09c7 \u09b0\u09be\u0996\u09c1\u09a8', url: '/remember', color: '#f43f5e', },
];

// ── Icon SVG map (Lucide-style, inline for zero network requests) ──
const ICON_SVG = {
  'file-image': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><circle cx="10" cy="13" r="2"/><path d="m20 17-1.09-1.09a2 2 0 0 0-2.82 0L10 22"/></svg>',
  'indian-rupee': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8 8"/><path d="M6 13h3a4 4 0 0 0 0-8"/></svg>',
  'image': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
  'message-circle': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.7 12.4a9 9 0 1 1-6.2-8.3"/><path d="M21.7 4.1v5.1h-5.1"/></svg>',
  'megaphone': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
  'camera': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="2.5"/></svg>',
  'smartphone': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
  'id-card': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><line x1="15" y1="8" x2="17" y2="8"/><line x1="15" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="17" y2="16"/></svg>',
  'file-text': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  'users': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'book-open': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
  'credit-card': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
  'keyboard': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M6 16h.01M18 16h.01M10 16h.01M14 16h.01"/></svg>',
  'sparkles': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 3.4a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M17 4v4"/><path d="M19 6h-4"/></svg>',
  'heart': '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
};

let _initialized = false;
let _observer = null;

function buildServiceCard(service, index) {
  const iconSvg = ICON_SVG[service.icon] || ICON_SVG.sparkles;
  return `
    <a href="${service.url}"
       data-service-card
       data-index="${index}"
       class="group relative flex flex-col items-center justify-center rounded-[20px] border border-white/10 bg-gradient-to-b from-slate-800/90 to-slate-800/60 p-5 text-white shadow-lg backdrop-blur-sm transition-all duration-300 hover:-translate-y-1.5 hover:scale-[1.03] min-h-[200px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
       style="--service-color: ${service.color};"
       aria-label="${service.titleBn} — ${service.titleEn}"
       tabindex="0"
       role="link">
      <div class="services-card-glow pointer-events-none absolute -inset-[1px] rounded-[20px] opacity-0 transition-opacity duration-300 group-hover:opacity-100"
           style="background: radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), ${service.color}15, transparent 40%);">
      </div>
      <div class="services-card-inner-shadow pointer-events-none absolute inset-0 rounded-[20px] opacity-60 transition-opacity duration-300">
      </div>
      <div class="relative z-10 mb-3 flex h-14 w-14 items-center justify-center rounded-xl sm:h-16 sm:w-16"
           style="background: linear-gradient(135deg, ${service.color}20, ${service.color}08);">
        <div class="services-icon-float h-7 w-7 sm:h-8 sm:w-8" style="color: ${service.color};">
          ${iconSvg}
        </div>
      </div>
      <h3 class="relative z-10 mb-1 text-center text-xs font-bold leading-tight sm:text-sm"
          style="font-family: 'Hind Siliguri', 'Noto Sans Bengali', sans-serif;">
        ${service.titleBn}
      </h3>
      <p class="relative z-10 text-center text-[10px] text-slate-400 sm:text-xs">${service.titleEn}</p>
      <span class="relative z-10 mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
            style="background: ${service.color}15; color: ${service.color};">
        <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        Explore
      </span>
    </a>
  `;
}

function renderServices(container) {
  const fragment = document.createDocumentFragment();
  SERVICES.forEach((service, i) => {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = buildServiceCard(service, i);
    const card = wrapper.firstElementChild;
    if (card) {
      card.style.setProperty('--reveal-delay', `${i * 50}ms`);
      fragment.appendChild(card);
    }
  });
  container.innerHTML = '';
  container.appendChild(fragment);
}

function setupRevealObserver(container) {
  if (_observer) _observer.disconnect();
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  _observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const card = entry.target;
      const delay = parseInt(card.style.getPropertyValue('--reveal-delay')) || 0;
      requestAnimationFrame(() => {
        card.style.opacity = '1';
        card.style.transform = 'translateY(0) scale(1)';
        card.style.transitionDelay = prefersReduced ? '0ms' : `${delay}ms`;
      });
      _observer.unobserve(card);
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px', });

  container.querySelectorAll(SERVICE_CARD_SELECTOR).forEach((card) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(24px) scale(0.95)';
    card.style.transition = 'opacity 500ms ease, transform 500ms cubic-bezier(0.16, 1, 0.3, 1)';
    _observer.observe(card);
  });
}

function setupCardGlow(container) {
  container.addEventListener('mousemove', (e) => {
    const card = e.target.closest(SERVICE_CARD_SELECTOR);
    if (!card) return;
    const rect = card.getBoundingClientRect();
    card.style.setProperty('--mouse-x', `${((e.clientX - rect.left) / rect.width) * 100}%`);
    card.style.setProperty('--mouse-y', `${((e.clientY - rect.top) / rect.height) * 100}%`);
  });
}

function setupKeyboardNav(container) {
  container.addEventListener('keydown', (e) => {
    const current = document.activeElement;
    if (!current || !current.closest(SERVICE_CARD_SELECTOR)) return;
    const cards = Array.from(container.querySelectorAll(SERVICE_CARD_SELECTOR));
    const idx = cards.indexOf(current);
    if (idx === -1) return;
    let nextIdx;
    const cols = getComputedCols(container);
    switch (e.key) {
      case 'ArrowRight': e.preventDefault(); nextIdx = Math.min(idx + 1, cards.length - 1); break;
      case 'ArrowLeft': e.preventDefault(); nextIdx = Math.max(idx - 1, 0); break;
      case 'ArrowDown': e.preventDefault(); nextIdx = Math.min(idx + cols, cards.length - 1); break;
      case 'ArrowUp': e.preventDefault(); nextIdx = Math.max(idx - cols, 0); break;
      case 'Enter': case ' ': e.preventDefault(); current.click(); return;
      default: return;
    }
    if (nextIdx >= 0 && nextIdx < cards.length) {
      cards[nextIdx].focus();
      cards[nextIdx].style.opacity = '1';
      cards[nextIdx].style.transform = 'translateY(0) scale(1)';
    }
  });
}

function getComputedCols(container) {
  const w = container.offsetWidth;
  if (w >= 1200) return 5;
  if (w >= 1024) return 4;
  if (w >= 768) return 3;
  if (w >= 480) return 2;
  return 1;
}

function animateIn(container, skeleton) {
  if (skeleton) {
    skeleton.style.opacity = '0';
    skeleton.style.transition = 'opacity 400ms ease';
    setTimeout(() => skeleton.classList.add('hidden'), 500);
  }
  renderServices(container);
  container.classList.remove('hidden');
  requestAnimationFrame(() => {
    setupRevealObserver(container);
    setupCardGlow(container);
    setupKeyboardNav(container);
  });
}

export function initServicesDashboard(container) {
  if (!container || _initialized) return;
  const skeleton = container.querySelector(SKELETON_SELECTOR);
  const grid = container.querySelector(GRID_SELECTOR);
  if (grid) { animateIn(grid, skeleton); _initialized = true; return; }
  const newGrid = document.createElement('div');
  newGrid.setAttribute('data-services-grid', '');
  (skeleton || container).after(newGrid);
  animateIn(newGrid, skeleton);
  _initialized = true;
}

document.addEventListener('DOMContentLoaded', () => {
  const el = document.querySelector(SERVICES_DASHBOARD_SELECTOR);
  if (el) initServicesDashboard(el);
});

export default { initServicesDashboard, };
