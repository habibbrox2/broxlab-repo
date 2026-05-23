(function () {
    'use strict';

    const path = window.location.pathname.replace(/\/+$/, '');
    const isCompaniesPage = path === '/medex' || path === '/medex/companies';
    const companyMatch = path.match(/^\/medex\/company\/(\d+)$/);
    const brandMatch = path.match(/^\/medex\/brand\/(\d+)$/);
    const companyId = companyMatch ? companyMatch[1] : null;
    const brandId = brandMatch ? brandMatch[1] : null;
    if (!isCompaniesPage && !companyId && !brandId) {
        return;
    }

    const PAGE_SIZE = 20;

    function detectInitialLang() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('lang') || (function() { try { return localStorage.getItem('medex-lang') || 'en'; } catch(e) { return 'en'; } })();
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

    const routeRefreshState = {
        triggered: false,
    };

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('en-US').format(Number(value) || 0);
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = value != null ? String(value) : '';
    }

    function renderLoadingTableRows(columnCount = 6, rowCount = 5) {
        const cellHtml = '<td class="text-muted small py-3"><span class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></span>Loading...</td>';
        return Array.from({ length: rowCount }, () => `<tr>${Array.from({ length: columnCount }, () => cellHtml).join('')}</tr>`).join('');
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
            el.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted py-3"><span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span> Loading details...</div>';
            el.removeAttribute('data-en-content');
            el.removeAttribute('data-bn-content');
        });
    }

    function showRouteStatus(id, message, isError = false, showSpinner = false) {
        const el = document.getElementById(id);
        if (!el) return;
        if (isError) {
            el.innerHTML = `<span class="text-danger">${escapeHtml(message)}</span>`;
        } else if (showSpinner) {
            el.innerHTML = `<span class="spinner-border spinner-border-sm text-primary me-1" role="status" aria-hidden="true"></span> ${escapeHtml(message)}`;
        } else {
            el.textContent = message;
        }
    }

    // --- Floating Toast Notification ---
    var _routeToastEl = null;
    var _routeToastTimer = null;

    function _injectToastStyles() {
        if (document.getElementById('medex-toast-style')) return;
        var style = document.createElement('style');
        style.id = 'medex-toast-style';
        style.textContent = `
            @keyframes medexToastSlideIn {
                from { transform: translateX(100%); opacity: 0; }
                to   { transform: translateX(0);    opacity: 1; }
            }
            @keyframes medexToastSlideOut {
                from { transform: translateX(0);    opacity: 1; }
                to   { transform: translateX(100%); opacity: 0; }
            }
            .medex-toast {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9999;
                min-width: 280px;
                max-width: 420px;
                padding: 14px 18px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.18);
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #fff;
                animation: medexToastSlideIn 0.3s ease-out;
                line-height: 1.4;
            }
            .medex-toast--primary { background: #0d6efd; }
            .medex-toast--success { background: #198754; }
            .medex-toast--danger  { background: #dc3545; }
            .medex-toast__close {
                margin-left: auto;
                background: none;
                border: none;
                color: rgba(255,255,255,0.8);
                font-size: 1.1rem;
                cursor: pointer;
                padding: 0 0 0 8px;
                line-height: 1;
                flex-shrink: 0;
            }
            .medex-toast__close:hover { color: #fff; }
        `;
        document.head.appendChild(style);
    }

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

        _injectToastStyles();

        var bgClass = 'medex-toast--primary';
        var iconHtml = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        var dismissAfter = 0;

        if (type === 'success') {
            bgClass = 'medex-toast--success';
            iconHtml = '<i class="bi bi-check-circle-fill" style="font-size:1.1rem;"></i>';
            dismissAfter = 5000;
        } else if (type === 'error') {
            bgClass = 'medex-toast--danger';
            iconHtml = '<i class="bi bi-exclamation-circle-fill" style="font-size:1.1rem;"></i>';
            dismissAfter = 8000;
        }

        _routeToastEl = document.createElement('div');
        _routeToastEl.className = 'medex-toast ' + bgClass;
        _routeToastEl.setAttribute('role', 'alert');
        _routeToastEl.setAttribute('aria-live', 'assertive');
        _routeToastEl.setAttribute('aria-atomic', 'true');
        _routeToastEl.innerHTML = iconHtml + '<span>' + escapeHtml(message) + '</span>';

        if (dismissAfter === 0) {
            var closeBtn = document.createElement('button');
            closeBtn.className = 'medex-toast__close';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.innerHTML = '&times;';
            closeBtn.addEventListener('click', function () {
                if (_routeToastEl) { _routeToastEl.remove(); _routeToastEl = null; }
            });
            _routeToastEl.appendChild(closeBtn);
        }

        document.body.appendChild(_routeToastEl);

        if (dismissAfter > 0) {
            _routeToastTimer = setTimeout(function () {
                if (_routeToastEl) {
                    _routeToastEl.style.animation = 'medexToastSlideOut 0.3s ease-in forwards';
                    setTimeout(function () {
                        if (_routeToastEl) { _routeToastEl.remove(); _routeToastEl = null; }
                    }, 300);
                }
            }, dismissAfter);
        }
    }

    async function triggerRouteRefresh(step, params = {}) {
        if (routeRefreshState.triggered) {
            return;
        }
        routeRefreshState.triggered = true;

        const statusId = step === 'companies'
            ? 'medexFetchStatus'
            : step === 'detailed'
                ? 'medexCompanyStatus'
                : step === 'brand-details'
                    ? 'medexBrandStatus'
                    : step === 'drug-details'
                        ? 'medex-refresh-feedback'
                        : 'medexFetchStatus';

        showRouteStatus(statusId, 'Starting route-specific MedEx collection...', false, true);
        showRouteToast('Route collection: ' + step + ' step starting...', 'starting');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const body = new URLSearchParams({ step });
        Object.keys(params).forEach((key) => {
            body.append(key, String(params[key]));
        });
        if (csrfToken) {
            body.append('csrf_token', csrfToken);
        }

        try {
            const response = await fetch('/api/medex/refresh-route', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: body.toString(),
            });
            const json = await response.json();
            if (!response.ok || !json.success) {
                throw new Error(json.error || 'Failed to start route refresh');
            }
            showRouteStatus(statusId, 'Background collection started. Refresh this page after a few minutes.', false);
            showRouteToast('Background collection started for ' + step + '. Refresh after a few minutes.', 'success');
        } catch (err) {
            showRouteStatus(statusId, `Route collection failed: ${err.message || err}`, true);
            showRouteToast('Collection failed: ' + (err.message || err), 'error');
        }
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
          <td colspan="4" class="text-center py-5 text-muted">No brands available for this company.</td>
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
              <a href="/medex/brand/${encodeURIComponent(brand._id)}" class="text-decoration-none fw-semibold medex-brand-name">
                <i class="bi bi-capsule me-1 text-success"></i>${escapeHtml(brand.name || 'Unknown')}
              </a>
            </td>
            <td class="medex-generic-name">${genericText.replace(/\n/g, '<br>')}</td>
            <td class="text-center">
              ${strengthLabel ? `<span class="medex-badge medex-badge--strength">${escapeHtml(strengthLabel)}</span>` : '<span class="text-muted">-</span>'}
            </td>
            <td class="text-center">
              <a href="/medex/brand/${encodeURIComponent(brand._id)}" class="medex-btn medex-btn--details">
                <i class="bi bi-file-medical"></i> View
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
        var value = source[key] || '';
        if (value && typeof value === 'object' && value.content) {
            value = value.content;
        }
        return String(value);
    }

    function renderSectionDisplay(contentEl) {
        var lang = state.lang;
        var enValue = contentEl.getAttribute('data-en-content') || '';
        var bnValue = contentEl.getAttribute('data-bn-content') || '';
        var displayValue = (lang === 'bn' && bnValue) ? bnValue : enValue;
        contentEl.innerHTML = displayValue
            ? String(displayValue).replace(/\n/g, '<br>')
            : '<p class="medex-section-empty">' + (lang === 'bn' ? 'কোনও তথ্য উপলব্ধ নেই।' : 'No data available.') + '</p>';
    }

    function renderBrandSections(brand) {
        if (!brand || typeof brand !== 'object') {
            return;
        }

        // Extract EN source
        var enSource = null;
        if (brand.details_en && typeof brand.details_en === 'object') {
            enSource = brand.details_en;
        } else if (brand.sections && typeof brand.sections === 'object') {
            enSource = brand.sections;
        } else if (brand.sections_en && typeof brand.sections_en === 'object') {
            enSource = brand.sections_en;
        }

        // Extract BN source
        var bnSource = null;
        if (brand.details_bn && typeof brand.details_bn === 'object') {
            bnSource = brand.details_bn;
        } else if (brand.sections_bn && typeof brand.sections_bn === 'object') {
            bnSource = brand.sections_bn;
        }

        // Store for toggle
        state.brandDetailsEn = enSource;
        state.brandDetailsBn = bnSource;

        var knownKeys = ['indications', 'pharmacology', 'dosage', 'interactions', 'contraindications', 'side_effects', 'pregnancy', 'precautions', 'overdose', 'therapeutic_class', 'storage', 'administration'];

        knownKeys.forEach(function (key) {
            var contentEl = document.getElementById('content-' + key);
            if (!contentEl) return;

            var enValue = getSectionContent(enSource, key);
            var bnValue = getSectionContent(bnSource, key);

            contentEl.setAttribute('data-en-content', enValue);
            contentEl.setAttribute('data-bn-content', bnValue);

            renderSectionDisplay(contentEl);
        });

        // Show/hide lang toggle based on BN availability
        var langToggle = document.getElementById('medexBrandLangToggle');
        if (langToggle) {
            var hasBn = bnSource && Object.keys(bnSource).some(function (k) { return bnSource[k] && String(bnSource[k]).trim(); });
            langToggle.style.display = hasBn ? 'inline-flex' : 'none';
        }
    }

    function toggleBrandLang(lang) {
        if (lang !== 'en' && lang !== 'bn') return;
        state.lang = lang;

        // Update brand generic name (use BN name if available)
        if (lang === 'bn' && state.brandDetailsBn) {
            var bnName = state.brandDetailsBn.brand_name || '';
            if (bnName) {
                setText('medexBrandGeneric', bnName);
            }
        }

        // Re-render all section content elements
        var knownKeys = ['indications', 'pharmacology', 'dosage', 'interactions', 'contraindications', 'side_effects', 'pregnancy', 'precautions', 'overdose', 'therapeutic_class', 'storage', 'administration'];
        knownKeys.forEach(function (key) {
            var contentEl = document.getElementById('content-' + key);
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
              <td class="text-center fw-semibold text-muted">${rank}</td>
              <td>
                <a href="${companyUrl}" class="company-name text-decoration-none">
                  <strong>${escapeHtml(company.name || 'Unknown')}</strong>
                  ${headquarter ? `<br><small class="text-muted"><i class="bi bi-geo-alt me-1"></i>${headquarterText}</small>` : ''}
                </a>
              </td>
              <td class="text-center"><span class="medex-badge medex-badge--established">${established}</span></td>
              <td class="text-end"><span class="medex-badge medex-badge--generics">${generics}</span></td>
              <td class="text-end"><span class="medex-badge medex-badge--brands">${brands}</span></td>
              <td class="text-center"><a href="${companyUrl}" class="medex-btn medex-btn--view"><i class="bi bi-eye"></i> View</a></td>
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

        let html = '<nav aria-label="Company list pagination" class="mt-4"><ul class="pagination justify-content-center">';

        // Previous button
        html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">`;
        html += `<button class="page-link" data-page="${currentPage - 1}" aria-label="Previous"><span aria-hidden="true">&laquo;</span></button></li>`;

        // Page numbers with ellipsis
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><button class="page-link" data-page="1">1</button></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            html += `<li class="page-item ${p === currentPage ? 'active' : ''}">`;
            html += `<button class="page-link" data-page="${p}">${p}</button></li>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><button class="page-link" data-page="${totalPages}">${totalPages}</button></li>`;
        }

        // Next button
        html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">`;
        html += `<button class="page-link" data-page="${currentPage + 1}" aria-label="Next"><span aria-hidden="true">&raquo;</span></button></li>`;

        html += '</ul></nav>';

        // Page info
        html += `<div class="text-center text-muted small mt-2">Showing ${formatNumber(from)}–${formatNumber(to)} of ${formatNumber(totalItems)} companies</div>`;

        return html;
    }

    function updatePaginationEventListeners(onPageChange) {
        document.querySelectorAll('.medex-pagination-wrap .page-link[data-page]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var page = parseInt(this.getAttribute('data-page'), 10);
                if (!isNaN(page)) {
                    onPageChange(page);
                }
            });
        });
    }

    function setPage(page) {
        state.currentPage = Math.max(1, page);
        var list = state.filteredCompanies.length ? state.filteredCompanies : state.companies;
        var totalItems = list.length;
        var totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
        if (state.currentPage > totalPages) {
            state.currentPage = totalPages;
        }

        var startIdx = (state.currentPage - 1) * PAGE_SIZE;
        var pageItems = list.slice(startIdx, startIdx + PAGE_SIZE);
        var tableBody = document.getElementById('medexCompaniesTableBody');
        if (tableBody) {
            if (pageItems.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No companies found.</td></tr>';
            } else {
                tableBody.innerHTML = renderCompanyTableRows(pageItems, startIdx);
            }
        }

        var paginationWrap = document.querySelector('.medex-pagination-wrap');
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
                statusEl.textContent += ` Last updated ${new Date(lastUpdated).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}.`;
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
                const blob = new Blob([JSON.stringify(state.companies, null, 2)], { type: 'application/json;charset=utf-8' });
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
            var url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}

        // Save preference
        try { localStorage.setItem('medex-lang', lang); } catch (e) {}

        // Update all data-i18n elements (static page text)
        document.querySelectorAll('[data-i18n-en]').forEach(function (el) {
            var translation = el.getAttribute('data-i18n-' + lang);
            if (translation !== null && translation !== '') {
                el.textContent = translation;
            }
        });

        // Update lang toggle buttons (page-level)
        document.querySelectorAll('[data-lang-btn]').forEach(function (btn) {
            var btnLang = btn.getAttribute('data-lang-btn');
            btn.classList.toggle('active', btnLang === lang);
            if (btnLang === lang) {
                btn.classList.remove('btn-outline-secondary', 'btn-outline-primary');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-secondary');
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
        document.querySelectorAll('[data-lang-btn]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                medexTogglePageLang(this.getAttribute('data-lang-btn'));
            });
        });

        // Sync UI to current language
        medexSyncLangUI(state.lang);
    }

    function medexSyncLangUI(lang) {
        document.querySelectorAll('[data-lang-btn]').forEach(function (btn) {
            var btnLang = btn.getAttribute('data-lang-btn');
            btn.classList.toggle('active', btnLang === lang);
            if (btnLang === lang) {
                btn.classList.remove('btn-outline-secondary', 'btn-outline-primary');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-secondary');
            }
        });
    }

    function initBrandLangToggle() {
        // Page-level lang buttons are wired by initPageLang() via [data-lang-btn].
        // This function only syncs the initial brand section language state.
        var lang = state.lang;
        if (typeof toggleBrandLang === 'function') {
            toggleBrandLang(lang);
        }
        medexSyncLangUI(lang);
    }

    async function initBrandDrugDetailsCollection() {
        if (!brandId) return;

        const feedback = document.getElementById('medex-refresh-feedback');
        if (!feedback) return;

        var stateKey = 'medex-brand-details-last-run-' + brandId;
        var cooldownMs = 12 * 60 * 60 * 1000; // 12 hours
        var lastRun = parseInt(localStorage.getItem(stateKey) || '0', 10);
        if (Number.isFinite(lastRun) && Date.now() - lastRun < cooldownMs) {
            return;
        }
        localStorage.setItem(stateKey, Date.now().toString());

        feedback.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-1" role="status" aria-hidden="true"></span> Starting drug details collection in background…';
        showRouteToast('Drug details collection starting for brand ' + brandId + '...', 'starting');

        try {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var body = new URLSearchParams({ step: 'drug-details' });
            if (csrfToken) {
                body.append('csrf_token', csrfToken);
            }

            var response = await fetch('/api/medex/refresh-route', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: body.toString(),
            });

            var json = await response.json();
            if (!response.ok || !json.success) {
                throw new Error(json.error || 'Unable to start drug details collection');
            }
            feedback.textContent = 'Drug details collection started in background. Refresh after a few minutes.';
            showRouteToast('Drug details collection started for brand ' + brandId + '.', 'success');
        } catch (err) {
            feedback.innerHTML = '<span class="text-danger">Drug details collection failed: ' + escapeHtml(err.message || err) + '</span>';
            showRouteToast('Drug details collection failed: ' + (err.message || err), 'error');
        }
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
                var hasSections = false;
                if (data.brand.details_en && typeof data.brand.details_en === 'object') {
                    hasSections = Object.keys(data.brand.details_en).length > 0;
                } else if (data.brand.details_bn && typeof data.brand.details_bn === 'object') {
                    hasSections = Object.keys(data.brand.details_bn).length > 0;
                } else if (data.brand.sections && typeof data.brand.sections === 'object') {
                    hasSections = Object.keys(data.brand.sections).length > 0;
                }
                if (!hasSections) {
                    showRouteStatus('medexBrandStatus', 'Brand details appear incomplete. Starting route collection...', false);
                    triggerRouteRefresh('brand-details', { brand_id: brandId });
                }
                // Also trigger drug-details collection for this brand in background
                initBrandDrugDetailsCollection();
            })
            .catch((error) => {
                showRouteStatus('medexBrandStatus', `Unable to load live brand data: ${error.message || error}`, true);
                triggerRouteRefresh('brand-details', { brand_id: brandId });
            });
    }

    initPageLang();

    if (isCompaniesPage) {
        initCompaniesPage();
    } else if (companyId) {
        initCompanyPage();
    } else if (brandId) {
        initBrandPage();
    }
})();
