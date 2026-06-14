'use strict';

import { escapeHtml } from './shared/utils.js';

const path = window.location.pathname.replace(/\/+$/, '');
const isCompaniesPage = path === '/medex' || path === '/medex/companies';
const companyMatch = path.match(/^\/medex\/company\/(\d+)$/);
const brandMatch = path.match(/^\/medex\/brand\/(\d+)$/);
const companyId = companyMatch ? companyMatch[1] : null;
const brandId = brandMatch ? brandMatch[1] : null;

const PAGE_SIZE = 20;

function detectInitialLang() {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get('lang') || (function () { try { return localStorage.getItem('medex-lang') || 'en'; } catch (e) { return 'en'; } })();
}

const state = {
  companies: [],
  filteredCompanies: [],
  currentPage: 1,
  lang: detectInitialLang(),
  brandDetailsEn: null,
  brandDetailsBn: null,
  langInitialized: false,
};

function formatNumber(value) {
  return new Intl.NumberFormat('en-US').format(Number(value) || 0);
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = value != null ? String(value) : '';
}

function renderLoadingTableRows(columnCount = 6, rowCount = 5) {
  const cellHtml = '<td class="text-slate-500 text-sm py-3"><span class="inline-spinner inline-spinner-sm text-indigo-600 mr-2" role="status" aria-hidden="true"></span>Loading...</td>';
  return Array.from({ length: rowCount, }, () => `<tr>${Array.from({ length: columnCount, }, () => cellHtml).join('')}</tr>`).join('');
}

function renderCompanyTableLoading() {
  const tbody = document.getElementById('medexCompaniesTableBody');
  if (tbody) {
    tbody.innerHTML = renderLoadingTableRows(6, 5);
  }
}

function renderCompanyBrandTableLoading() {
  const tbody = document.getElementById('medexCompanyTableBody');
  if (tbody) {
    tbody.innerHTML = renderLoadingTableRows(4, 5);
  }
}

function renderBrandSectionsLoading() {
  document.querySelectorAll('.medex-section-content').forEach((el) => {
    el.innerHTML = '<div class="flex items-center gap-2 text-slate-500 py-3"><span class="inline-spinner inline-spinner-sm text-indigo-600" role="status" aria-hidden="true"></span> Loading details...</div>';
    el.removeAttribute('data-en-content');
    el.removeAttribute('data-bn-content');
  });
}

function showRouteStatus(id, message, isError = false, showSpinner = false) {
  const el = document.getElementById(id);
  if (!el) return;
  if (isError) {
    el.innerHTML = `<span class="text-red-600">${escapeHtml(message)}</span>`;
  } else if (showSpinner) {
    el.innerHTML = `<span class="inline-spinner inline-spinner-sm text-indigo-600 mr-1" role="status" aria-hidden="true"></span> ${escapeHtml(message)}`;
  } else {
    el.textContent = message;
  }
}

// --- Floating Toast Notification ---
let _routeToastEl = null;
let _routeToastTimer = null;

function showRouteToast(message, type) {
  // type: 'starting' | 'success' | 'error'
  if (_routeToastEl) {
    _routeToastEl.remove();
    _routeToastEl = null;
  }
  if (_routeToastTimer) {
    clearTimeout(_routeToastTimer);
    _routeToastTimer = null;
  }

  let bgClass = 'medex-toast--primary';
  let iconHtml = '<span class="inline-spinner inline-spinner-sm" role="status" aria-hidden="true"></span>';
  let dismissAfter = 0;

  if (type === 'success') {
    bgClass = 'medex-toast--success';
    iconHtml = '<i class="lucide lucide-check-circle" style="width:1.1rem;height:1.1rem;"></i>';
    dismissAfter = 5000;
  } else if (type === 'error') {
    bgClass = 'medex-toast--danger';
    iconHtml = '<i class="lucide lucide-alert-circle" style="width:1.1rem;height:1.1rem;"></i>';
    dismissAfter = 8000;
  }

  _routeToastEl = document.createElement('div');
  _routeToastEl.className = `medex-toast ${bgClass}`;
  _routeToastEl.setAttribute('role', 'alert');
  _routeToastEl.setAttribute('aria-live', 'assertive');
  _routeToastEl.setAttribute('aria-atomic', 'true');
  _routeToastEl.innerHTML = `${iconHtml}<span>${escapeHtml(message)}</span>`;

  if (dismissAfter === 0) {
    const closeBtn = document.createElement('button');
    closeBtn.className = 'medex-toast__close';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => {
      if (_routeToastEl) { _routeToastEl.remove(); _routeToastEl = null; }
    });
    _routeToastEl.appendChild(closeBtn);
  }

  document.body.appendChild(_routeToastEl);

  if (dismissAfter > 0) {
    _routeToastTimer = setTimeout(() => {
      if (_routeToastEl) {
        _routeToastEl.style.animation = 'medexToastSlideOut 0.3s ease-in forwards';
        setTimeout(() => {
          if (_routeToastEl) { _routeToastEl.remove(); _routeToastEl = null; }
        }, 300);
      }
    }, dismissAfter);
  }
}

function triggerRouteRefresh(step, params = {}) {
  const statusId = step === 'companies'
    ? 'medexFetchStatus'
    : step === 'detailed'
      ? 'medexCompanyStatus'
      : step === 'brand-details'
        ? 'medexBrandStatus'
        : step === 'drug-details'
          ? 'medex-refresh-feedback'
          : 'medexFetchStatus';
  showRouteStatus(statusId, 'Live route refresh is unavailable in this deployment.', true);
  showRouteToast('Route refresh is unavailable.', 'error');
}

async function fetchJson(url) {
  const response = await fetch(url, {
    cache: 'no-store',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
    },
  });
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }
  const data = await response.json();
  if (!data || !data.success) {
    throw new Error(data && data.error ? data.error : 'Invalid response');
  }
  return data;
}

function renderCompanyRows(brands) {
  const tbody = document.getElementById('medexCompanyTableBody');
  if (!tbody) return;
  if (!Array.isArray(brands) || !brands.length) {
    tbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center py-5 text-slate-500">No brands available for this company.</td>
        </tr>`;
    return;
  }

  tbody.innerHTML = brands
    .map((brand) => {
      const genericText = escapeHtml(brand.generic || '');
      const strengthLabel = genericText.match(/(\d+\s*[a-zA-Z]+)/) ? genericText.match(/(\d+\s*[a-zA-Z]+)/)[0] : '';
      return `
          <tr class="medex-hover-lift">
            <td>
              <a href="/medex/brand/${encodeURIComponent(brand._id)}" class="no-underline font-semibold medex-brand-name">
                <i class="lucide lucide-pill mr-1 text-emerald-600" style="width:1rem;height:1rem;"></i>${escapeHtml(brand.name || 'Unknown')}
              </a>
            </td>
            <td class="medex-generic-name">${genericText.replace(/\n/g, '<br>')}</td>
            <td class="text-center">
              ${strengthLabel ? `<span class="medex-badge medex-badge--strength">${escapeHtml(strengthLabel)}</span>` : '<span class="text-slate-500">-</span>'}
            </td>
            <td class="text-center">
              <a href="/medex/brand/${encodeURIComponent(brand._id)}" class="medex-btn medex-btn--details">
                <i class="lucide lucide-file-plus" style="width:1rem;height:1rem;"></i> View
              </a>
            </td>
          </tr>`;
    })
    .join('');
}

function updateCompanyUI(company) {
  setText('medexCompanyName', company.name || document.getElementById('medexCompanyName')?.textContent);
  setText('medexCompanyOverview', company.overview || '');
  setText('medexCompanyEstablished', company.established || 'N/A');
  setText('medexCompanyMarketShare', company.market_share || 'N/A');
  setText('medexCompanyGrowth', company.growth || 'N/A');
  setText('medexCompanyGenerics', company.total_generics || company.generics || 0);
  setText('medexCompanyBrands', Array.isArray(company.brands) ? company.brands.length : company.brands || 0);
  setText('medexCompanyHeadquarter', company.headquarter || 'N/A');
  setText('medexCompanyContact', company.contact || 'N/A');
  setText('medexCompanyFax', company.fax || 'N/A');
  setText('medexCompanyBrandCount', Array.isArray(company.brands) ? company.brands.length : company.brands || 0);
  const statusEl = document.getElementById('medexCompanyStatus');
  if (statusEl) {
    statusEl.textContent = 'Live company data loaded.';
  }
  renderCompanyRows(company.brands || []);
}

function getSectionContent(source, key) {
  if (!source || typeof source !== 'object') return '';
  let value = source[key] || '';
  if (value && typeof value === 'object' && value.content) {
    value = value.content;
  }
  return String(value);
}

function renderSectionDisplay(contentEl) {
  const lang = state.lang;
  const enValue = contentEl.getAttribute('data-en-content') || '';
  const bnValue = contentEl.getAttribute('data-bn-content') || '';
  const displayValue = (lang === 'bn' && bnValue) ? bnValue : enValue;
  contentEl.innerHTML = displayValue
    ? String(displayValue).replace(/\n/g, '<br>')
    : `<p class="medex-section-empty">${lang === 'bn' ? 'কোনও তথ্য উপলব্ধ নেই।' : 'No data available.'}</p>`;
}

function renderBrandSections(brand) {
  if (!brand || typeof brand !== 'object') {
    return;
  }

  // Extract EN source
  let enSource = null;
  if (brand.details_en && typeof brand.details_en === 'object') {
    enSource = brand.details_en;
  } else if (brand.sections && typeof brand.sections === 'object') {
    enSource = brand.sections;
  } else if (brand.sections_en && typeof brand.sections_en === 'object') {
    enSource = brand.sections_en;
  }

  // Extract BN source
  let bnSource = null;
  if (brand.details_bn && typeof brand.details_bn === 'object') {
    bnSource = brand.details_bn;
  } else if (brand.sections_bn && typeof brand.sections_bn === 'object') {
    bnSource = brand.sections_bn;
  }

  // Store for toggle
  state.brandDetailsEn = enSource;
  state.brandDetailsBn = bnSource;

  const knownKeys = ['indications', 'pharmacology', 'dosage', 'interactions', 'contraindications', 'side_effects', 'pregnancy', 'precautions', 'overdose', 'therapeutic_class', 'storage', 'administration',];

  knownKeys.forEach((key) => {
    const contentEl = document.getElementById(`content-${key}`);
    if (!contentEl) return;

    const enValue = getSectionContent(enSource, key);
    const bnValue = getSectionContent(bnSource, key);

    contentEl.setAttribute('data-en-content', enValue);
    contentEl.setAttribute('data-bn-content', bnValue);

    renderSectionDisplay(contentEl);
  });

  // Show/hide lang toggle based on BN availability
  const langToggle = document.getElementById('medexBrandLangToggle');
  if (langToggle) {
    const hasBn = bnSource && Object.keys(bnSource).some((k) => { return bnSource[k] && String(bnSource[k]).trim(); });
    langToggle.style.display = hasBn ? 'inline-flex' : 'none';
  }
}

function toggleBrandLang(lang) {
  if (lang !== 'en' && lang !== 'bn') return;
  state.lang = lang;

  // Update brand generic name (use BN name if available)
  if (lang === 'bn' && state.brandDetailsBn) {
    const bnName = state.brandDetailsBn.brand_name || '';
    if (bnName) {
      setText('medexBrandGeneric', bnName);
    }
  }

  // Re-render all section content elements
  const knownKeys = ['indications', 'pharmacology', 'dosage', 'interactions', 'contraindications', 'side_effects', 'pregnancy', 'precautions', 'overdose', 'therapeutic_class', 'storage', 'administration',];
  knownKeys.forEach((key) => {
    const contentEl = document.getElementById(`content-${key}`);
    if (contentEl) {
      renderSectionDisplay(contentEl);
    }
  });
}

function updateBrandUI(brand) {
  setText('medexBrandName', brand.name || document.getElementById('medexBrandName')?.textContent);
  setText('medexBrandGeneric', brand.generic || document.getElementById('medexBrandGeneric')?.textContent);
  const statusEl = document.getElementById('medexBrandStatus');
  if (statusEl) {
    statusEl.textContent = 'Live brand data loaded.';
  }
  renderBrandSections(brand);
  // Show lang toggle if BN data available (renderBrandSections handles visibility)
}

function renderCompanyTableRows(companies, baseIndex) {
  return companies
    .map((company, idx) => {
      const rank = baseIndex + idx + 1;
      const established = escapeHtml(company.established || 'N/A');
      const headquarter = escapeHtml(company.headquarter || '');
      const headquarterText = headquarter.length > 50 ? `${headquarter.slice(0, 50)}...` : headquarter;
      const generics = formatNumber(company.generics || company.total_generics || 0);
      const brands = formatNumber(company.brands || 0);
      const companyUrl = `/medex/company/${encodeURIComponent(company._id)}`;
      return `
            <tr class="medex-hover-lift">
              <td class="text-center font-semibold text-slate-500">${rank}</td>
              <td>
                <a href="${companyUrl}" class="company-name no-underline">
                  <strong>${escapeHtml(company.name || 'Unknown')}</strong>
                  ${headquarter ? `<br><small class="text-slate-500"><i class="lucide lucide-map-pin mr-1" style="width:1rem;height:1rem;"></i>${headquarterText}</small>` : ''}
                </a>
              </td>
              <td class="text-center"><span class="medex-badge medex-badge--established">${established}</span></td>
              <td class="text-end"><span class="medex-badge medex-badge--generics">${generics}</span></td>
              <td class="text-end"><span class="medex-badge medex-badge--brands">${brands}</span></td>
              <td class="text-center"><a href="${companyUrl}" class="medex-btn medex-btn--view"><i class="lucide lucide-eye" style="width:1rem;height:1rem;"></i> View</a></td>
            </tr>`;
    })
    .join('');
}

function renderPaginationControls(totalItems, currentPage, perPage) {
  const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
  currentPage = Math.min(currentPage, totalPages);
  currentPage = Math.max(1, currentPage);

  if (totalPages <= 1) {
    return '';
  }

  const from = (currentPage - 1) * perPage + 1;
  const to = Math.min(currentPage * perPage, totalItems);

  let html = '<nav aria-label="Company list pagination" class="mt-4"><ul class="flex items-center justify-center gap-0.5">';

  // Previous button
  html += `<li class="${currentPage <= 1 ? 'opacity-50 pointer-events-none' : ''}">`;
  html += `<button class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer" data-page="${currentPage - 1}" aria-label="Previous"><span aria-hidden="true">&laquo;</span></button></li>`;

  // Page numbers with ellipsis
  const startPage = Math.max(1, currentPage - 2);
  const endPage = Math.min(totalPages, currentPage + 2);

  if (startPage > 1) {
    html += '<li><button class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer" data-page="1">1</button></li>';
    if (startPage > 2) {
      html += '<li class="opacity-50 cursor-default"><span class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium text-slate-400">...</span></li>';
    }
  }

  for (let p = startPage; p <= endPage; p++) {
    html += `<li class="${p === currentPage ? 'active' : ''}">`;
    html += `<button class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium transition-colors cursor-pointer ${p === currentPage ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'}" data-page="${p}">${p}</button></li>`;
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += '<li class="opacity-50 cursor-default"><span class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium text-slate-400">...</span></li>';
    }
    html += `<li class=""><button class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer" data-page="${totalPages}">${totalPages}</button></li>`;
  }

  // Next button
  html += `<li class="${currentPage >= totalPages ? 'opacity-50 pointer-events-none' : ''}">`;
  html += `<button class="page-link inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors cursor-pointer" data-page="${currentPage + 1}" aria-label="Next"><span aria-hidden="true">&raquo;</span></button></li>`;

  html += '</ul></nav>';

  // Page info
  html += `<div class="text-center text-slate-500 text-sm mt-2">Showing ${formatNumber(from)}–${formatNumber(to)} of ${formatNumber(totalItems)} companies</div>`;

  return html;
}

function updatePaginationEventListeners(onPageChange) {
  document.querySelectorAll('.medex-pagination-wrap .page-link[data-page]').forEach((btn) => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const page = parseInt(this.getAttribute('data-page'), 10);
      if (!isNaN(page)) {
        onPageChange(page);
      }
    });
  });
}

function setPage(page) {
  state.currentPage = Math.max(1, page);
  const list = state.filteredCompanies.length ? state.filteredCompanies : state.companies;
  const totalItems = list.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
  if (state.currentPage > totalPages) {
    state.currentPage = totalPages;
  }

  const startIdx = (state.currentPage - 1) * PAGE_SIZE;
  const pageItems = list.slice(startIdx, startIdx + PAGE_SIZE);
  const tableBody = document.getElementById('medexCompaniesTableBody');
  if (tableBody) {
    if (pageItems.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-slate-500">No companies found.</td></tr>';
    } else {
      tableBody.innerHTML = renderCompanyTableRows(pageItems, startIdx);
    }
  }

  const paginationWrap = document.querySelector('.medex-pagination-wrap');
  if (paginationWrap) {
    paginationWrap.innerHTML = renderPaginationControls(totalItems, state.currentPage, PAGE_SIZE);
    updatePaginationEventListeners(setPage);
  }
}

function updateCompaniesUI(companies, lastUpdated) {
  const list = Array.isArray(companies) ? companies : [];
  const statusEl = document.getElementById('medexFetchStatus');
  const totalCompaniesEl = document.getElementById('medexTotalCompanies');
  const totalBrandsEl = document.getElementById('medexTotalBrands');

  state.companies = list;
  state.filteredCompanies = [];
  state.currentPage = 1;

  if (totalCompaniesEl) {
    totalCompaniesEl.textContent = formatNumber(list.length);
  }
  if (totalBrandsEl) {
    const totalBrands = list.reduce((sum, item) => sum + Number(item.brands || 0), 0);
    totalBrandsEl.textContent = formatNumber(totalBrands);
  }

  setPage(1);

  if (statusEl) {
    statusEl.textContent = `Showing ${formatNumber(list.length)} live companies from MedEx.`;
    if (lastUpdated) {
      statusEl.textContent += ` Last updated ${new Date(lastUpdated).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', })}.`;
    }
  }
}

function initCompanyPage() {
  const statusEl = document.getElementById('medexCompanyStatus');
  if (statusEl) {
    statusEl.textContent = 'Fetching live company data…';
  }
  renderCompanyBrandTableLoading();
  fetchJson(`/api/medex/company/${companyId}`)
    .then((data) => {
      if (!data.company || !data.company.name) {
        throw new Error('Company details unavailable');
      }
      updateCompanyUI(data.company);
      if (!Array.isArray(data.company.brands) || data.company.brands.length === 0) {
        showRouteStatus('medexCompanyStatus', 'Company details appear incomplete. Starting detailed route collection...', false);
        triggerRouteRefresh('detailed');
      }
    })
    .catch((error) => {
      showRouteStatus('medexCompanyStatus', `Unable to load live company data: ${error.message || error}`, true);
      triggerRouteRefresh('detailed');
    });
}

function initCompaniesPage() {
  const searchInput = document.getElementById('medexSearchInput');
  const filterBtn = document.getElementById('medexFilterBtn');
  const exportBtn = document.getElementById('medexExportBtn');

  const handleSearch = () => {
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    state.filteredCompanies = query
      ? state.companies.filter((company) => {
        const name = String(company.name || '').toLowerCase();
        const headquarter = String(company.headquarter || '').toLowerCase();
        return name.includes(query) || headquarter.includes(query);
      })
      : [];

    state.currentPage = 1;
    setPage(1);

    const statusEl = document.getElementById('medexFetchStatus');
    if (statusEl) {
      const showing = state.filteredCompanies.length || state.companies.length;
      const total = state.companies.length;
      if (query) {
        statusEl.textContent = `Showing ${formatNumber(showing)} of ${formatNumber(total)} companies matching "${escapeHtml(searchInput.value)}".`;
      } else {
        statusEl.textContent = `Showing ${formatNumber(total)} live companies from MedEx.`;
      }
    }
  };

  if (searchInput) {
    let timeout = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(handleSearch, 200);
    });
  }
  if (filterBtn) {
    filterBtn.addEventListener('click', handleSearch);
  }
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      if (!state.companies.length) {
        return;
      }
      const blob = new Blob([JSON.stringify(state.companies, null, 2),], { type: 'application/json;charset=utf-8', });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'medex_companies_live.json';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    });
  }

  const statusEl = document.getElementById('medexFetchStatus');
  if (statusEl) {
    statusEl.textContent = 'Fetching live companies from MedEx…';
  }
  renderCompanyTableLoading();
  fetchJson('/api/medex/companies')
    .then((data) => {
      state.companies = Array.isArray(data.companies) ? data.companies : [];
      if (!state.companies.length) {
        showRouteStatus('medexFetchStatus', 'No live companies found yet. Starting route collection...', false);
        triggerRouteRefresh('companies');
        return;
      }
      updateCompaniesUI(state.companies, data.last_updated);
    })
    .catch((error) => {
      showRouteStatus('medexFetchStatus', `Unable to load live MedEx data. ${error.message || error}`, true);
      triggerRouteRefresh('companies');
    });
}

// ===== Dynamic Language Switching (MedEx pages) =====

function medexTogglePageLang(lang) {
  if (lang !== 'en' && lang !== 'bn') return;
  state.lang = lang;

  // Update URL without page reload
  try {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.history.replaceState({}, '', url.toString());
  } catch (e) { /* non-critical, silently ignore */ }

  // Save preference
  try { localStorage.setItem('medex-lang', lang); } catch (e) { /* non-critical, silently ignore */ }

  // Update all data-i18n elements (static page text)
  document.querySelectorAll('[data-i18n-en]').forEach((el) => {
    const translation = el.getAttribute(`data-i18n-${lang}`);
    if (translation !== null && translation !== '') {
      el.textContent = translation;
    }
  });

  // Update lang toggle buttons (page-level)
  document.querySelectorAll('[data-lang-btn]').forEach((btn) => {
    const btnLang = btn.getAttribute('data-lang-btn');
    btn.classList.toggle('active', btnLang === lang);
    if (btnLang === lang) {
      btn.classList.remove('border-slate-300', 'text-slate-600', 'hover:bg-slate-50', 'border-indigo-600', 'text-indigo-600', 'hover:bg-indigo-50');
      btn.classList.add('bg-indigo-600', 'text-white', 'hover:bg-indigo-700');
    } else {
      btn.classList.remove('bg-indigo-600', 'text-white', 'hover:bg-indigo-700');
      btn.classList.add('border-slate-300', 'text-slate-600', 'hover:bg-slate-50');
    }
  });

  // Update brand section language if on brand page
  if (typeof toggleBrandLang === 'function') {
    toggleBrandLang(lang);
  }
}

function initPageLang() {
  if (state.langInitialized) return;
  state.langInitialized = true;

  // Wire page-level data-lang-btn buttons
  document.querySelectorAll('[data-lang-btn]').forEach((btn) => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      medexTogglePageLang(this.getAttribute('data-lang-btn'));
    });
  });

  // Sync UI to current language
  medexSyncLangUI(state.lang);
}

function medexSyncLangUI(lang) {
  document.querySelectorAll('[data-lang-btn]').forEach((btn) => {
    const btnLang = btn.getAttribute('data-lang-btn');
    btn.classList.toggle('active', btnLang === lang);
    if (btnLang === lang) {
      btn.classList.remove('border-slate-300', 'text-slate-600', 'hover:bg-slate-50', 'border-indigo-600', 'text-indigo-600', 'hover:bg-indigo-50');
      btn.classList.add('bg-indigo-600', 'text-white', 'hover:bg-indigo-700');
    } else {
      btn.classList.remove('bg-indigo-600', 'text-white', 'hover:bg-indigo-700');
      btn.classList.add('border-slate-300', 'text-slate-600', 'hover:bg-slate-50');
    }
  });
}

function initBrandLangToggle() {
  // Page-level lang buttons are wired by initPageLang() via [data-lang-btn].
  // This function only syncs the initial brand section language state.
  const lang = state.lang;
  if (typeof toggleBrandLang === 'function') {
    toggleBrandLang(lang);
  }
  medexSyncLangUI(lang);
}

function initBrandSpecificCollection() {
  if (!brandId) return;
  showRouteToast('Brand detail collection is disabled. Use the browser scraper workflow instead.', 'error');
}

function initBrandPage() {
  const statusEl = document.getElementById('medexBrandStatus');
  if (statusEl) {
    statusEl.textContent = 'Fetching live brand data…';
  }
  renderBrandSectionsLoading();
  initBrandLangToggle();
  fetchJson(`/api/medex/brand/${brandId}`)
    .then((data) => {
      if (!data.brand || !data.brand.name) {
        throw new Error('Brand details unavailable');
      }
      updateBrandUI(data.brand);
      // Check if we have sections data (either en or bn)
      let hasSections = false;
      if (data.brand.details_en && typeof data.brand.details_en === 'object') {
        hasSections = Object.keys(data.brand.details_en).length > 0;
      } else if (data.brand.details_bn && typeof data.brand.details_bn === 'object') {
        hasSections = Object.keys(data.brand.details_bn).length > 0;
      } else if (data.brand.sections && typeof data.brand.sections === 'object') {
        hasSections = Object.keys(data.brand.sections).length > 0;
      }
      if (!hasSections) {
        showRouteStatus('medexBrandStatus', 'Brand details appear incomplete. Starting route collection...', false);
        triggerRouteRefresh('brand-details', { brand_id: brandId, });
      }
      // Also trigger brand-specific detail collection in background
      initBrandSpecificCollection();
    })
    .catch((error) => {
      showRouteStatus('medexBrandStatus', `Unable to load live brand data: ${error.message || error}`, true);
      triggerRouteRefresh('brand-details', { brand_id: brandId, });
    });
}

// Guarded execution - only initialize on MedEx pages
if (isCompaniesPage || companyId || brandId) {
  initPageLang();

  if (isCompaniesPage) {
    initCompaniesPage();
  } else if (companyId) {
    initCompanyPage();
  } else if (brandId) {
    initBrandPage();
  }
}

export { toggleBrandLang, medexTogglePageLang, triggerRouteRefresh, setPage };

