/**
 * Services Dashboard — Utility Portal Style Service Grid
 *
 * Simple static card grid matching government-portal design.
 * No fancy effects, no glassmorphism, no gradients.
 */

const SERVICES_DASHBOARD_SELECTOR = '[data-services-dashboard]';
const SKELETON_SELECTOR = '[data-services-skeleton]';
const GRID_SELECTOR = '[data-services-grid]';
const ENDPOINT_ATTR = 'data-services-endpoint';
const DEBUG_PREFIX = '[ServicesDashboard]';

// Services are supplied by the server from the database.
// The script only handles rendering and fallback icon display.

// ── Icon SVG map ──
const ICON_SVG = {
  'file-image': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><circle cx="10" cy="13" r="2"/><path d="m20 17-1.09-1.09a2 2 0 0 0-2.82 0L10 22"/></svg>',
  'indian-rupee': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 8h12"/><path d="m6 13 8 8"/><path d="M6 13h3a4 4 0 0 0 0-8"/></svg>',
  'image': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
  'message-circle': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.7 12.4a9 9 0 1 1-6.2-8.3"/><path d="M21.7 4.1v5.1h-5.1"/></svg>',
  'megaphone': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
  'camera': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="2.5"/></svg>',
  'smartphone': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
  'id-card': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><line x1="15" y1="8" x2="17" y2="8"/><line x1="15" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="17" y2="16"/></svg>',
  'file-text': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  'users': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'book-open': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
  'credit-card': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
  'keyboard': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M6 16h.01M18 16h.01M10 16h.01M14 16h.01"/></svg>',
  'sparkles': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 3.4a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M17 4v4"/><path d="M19 6h-4"/></svg>',
  'heart': '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
};

let _initialized = false;

function debugLog(message, details) {
  if (typeof console !== 'undefined' && typeof console.info === 'function') {
    console.info(DEBUG_PREFIX, message, details || '');
  }
}

function debugWarn(message, details) {
  if (typeof console !== 'undefined' && typeof console.warn === 'function') {
    console.warn(DEBUG_PREFIX, message, details || '');
  }
}

function getServicesFromContainer(container) {
  if (!container) {
    return [];
  }

  const raw = container.getAttribute('data-services-json') || '';
  if (!raw) {
    return [];
  }

  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    debugWarn('Failed to parse embedded services JSON.', error);
    return [];
  }
}

async function fetchServicesFromEndpoint(container) {
  const endpoint = container.getAttribute(ENDPOINT_ATTR) || '';
  if (!endpoint || !window.fetch) {
    debugWarn('Fetch skipped because endpoint or fetch API is unavailable.', {
      hasEndpoint: Boolean(endpoint),
      hasFetch: Boolean(window.fetch),
    });
    return [];
  }

  debugLog('Fetching services from endpoint.', { endpoint, });

  try {
    const response = await fetch(endpoint, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      debugWarn('Services endpoint returned a non-OK response.', {
        endpoint,
        status: response.status,
        statusText: response.statusText,
      });
      return [];
    }

    const payload = await response.json();
    const services = Array.isArray(payload?.services) ? payload.services : [];
    debugLog('Services endpoint response received.', {
      endpoint,
      success: Boolean(payload?.success),
      serviceCount: services.length,
    });
    return services;
  } catch (error) {
    debugWarn('Failed to fetch services dashboard data.', error);
    return [];
  }
}

function getServiceTitle(service) {
  return service.name || service.title || service.titleBn || service.titleEn || 'Service';
}

function getServiceSlug(service) {
  return service.slug || service.url || '';
}

function getServiceUrl(service) {
  const slug = getServiceSlug(service);
  if (slug && !slug.startsWith('http')) {
    return slug.startsWith('/') ? slug : `/services/view/${slug}`;
  }
  return service.url || '/services';
}

function buildServiceCard(service) {
  const title = getServiceTitle(service);
  const url = getServiceUrl(service);
  const iconSvg = ICON_SVG[service.icon] || ICON_SVG.sparkles;

  return `
    <a href="${url}"
       class="service-card"
       aria-label="${title}"
       tabindex="0">
      <div class="service-card-icon">
        ${iconSvg}
      </div>
      <div class="service-card-title">${title}</div>
    </a>
  `;
}

function renderServices(container) {
  const target = container.querySelector('.services-grid') || container;
  const fragment = document.createDocumentFragment();
  const services = getServicesFromContainer(container);

  debugLog('Rendering services grid.', {
    containerHasGrid: Boolean(container.querySelector('.services-grid')),
    serviceCount: services.length,
  });

  services.forEach((service) => {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = buildServiceCard(service);
    const card = wrapper.firstElementChild;
    if (card) {
      fragment.appendChild(card);
    }
  });

  target.innerHTML = '';
  target.appendChild(fragment);
}

function renderEmptyState(container, message) {
  const target = container.querySelector('.services-grid') || container;
  target.innerHTML = `
    <div class="services-empty-state" role="status" aria-live="polite">
      <div class="services-empty-state-title">Services unavailable</div>
      <div class="services-empty-state-text">${message}</div>
    </div>
  `;
  container.classList.remove('hidden');
}

function animateIn(container, skeleton) {
  if (skeleton) {
    skeleton.style.transition = 'opacity 300ms ease';
    skeleton.style.opacity = '0';
    setTimeout(() => { skeleton.classList.add('hidden'); }, 350);
  }

  const gridContainer = container.querySelector('.services-grid') || container;
  renderServices(gridContainer);
  container.classList.remove('hidden');
}

async function resolveServices(container) {
  const embeddedServices = getServicesFromContainer(container);
  if (embeddedServices.length > 0) {
    debugLog('Using embedded services JSON.', { serviceCount: embeddedServices.length, });
    return embeddedServices;
  }

  const fetchedServices = await fetchServicesFromEndpoint(container);
  if (fetchedServices.length > 0) {
    debugLog('Using fetched services payload.', { serviceCount: fetchedServices.length, });
    return fetchedServices;
  }

  debugWarn('No services data available from embedded JSON or endpoint.', {
    endpoint: container.getAttribute(ENDPOINT_ATTR) || '',
  });
  return [];
}

export async function initServicesDashboard(container) {
  if (!container || _initialized) return;
  const skeleton = container.querySelector(SKELETON_SELECTOR);
  const grid = container.querySelector(GRID_SELECTOR);
  const services = await resolveServices(grid || container);

  if (grid) {
    if (services.length > 0) {
      grid.setAttribute('data-services-json', JSON.stringify(services));
      animateIn(grid, skeleton);
    } else {
      if (skeleton) {
        skeleton.style.transition = 'opacity 300ms ease';
        skeleton.style.opacity = '0';
        setTimeout(() => { skeleton.classList.add('hidden'); }, 350);
      }
      renderEmptyState(grid, 'We could not load the service list right now. Please try again later.');
      debugWarn('Rendered empty state for services dashboard.');
    }
    _initialized = true;
    return;
  }

  const newGrid = document.createElement('div');
  newGrid.setAttribute('data-services-grid', '');
  if (services.length > 0) {
    newGrid.setAttribute('data-services-json', JSON.stringify(services));
    animateIn(newGrid, skeleton);
  } else {
    if (skeleton) {
      skeleton.style.transition = 'opacity 300ms ease';
      skeleton.style.opacity = '0';
      setTimeout(() => { skeleton.classList.add('hidden'); }, 350);
    }
    renderEmptyState(newGrid, 'We could not load the service list right now. Please try again later.');
    debugWarn('Rendered empty state for services dashboard.');
  }
  (skeleton || container).after(newGrid);
  _initialized = true;
}

document.addEventListener('DOMContentLoaded', async () => {
  const el = document.querySelector(SERVICES_DASHBOARD_SELECTOR);
  if (el) await initServicesDashboard(el);
});

export default { initServicesDashboard, };
