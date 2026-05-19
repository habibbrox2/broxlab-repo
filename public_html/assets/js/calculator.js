/**
 * calculator.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Client-side logic for all BroxLab calculators:
 *  • light client-side input validation (HTML5 + JS)
 *  • AJAX form submission to /api/calculator/compute/{type}
 *  • progressive result rendering (animated cards)
 *  • copy-to-clipboard for result blocks
 *  • keyboard shortcuts (Enter to submit)
 *
 * Loaded once at the bottom of public/calculator/generic.twig.
 */
(function () {
    'use strict';

    // ── DOM references ──────────────────────────────────────────────────────
    const form          = document.getElementById('calculator-form');
    const formWrap      = document.getElementById('calc-form-wrap');
    const resultsPanel  = document.getElementById('calc-results');
    const submitBtn     = document.getElementById('calc-submit-btn');
    const btnLabel      = document.getElementById('calc-btn-label');
    const btnSpinner    = document.getElementById('calc-spinner');

    if (!form) return; // Not a calculator page – do nothing

    const endpoint = form.action;

    // ── helpers ─────────────────────────────────────────────────────────────
    function setLoading(on) {
        submitBtn.disabled  = on;
        btnLabel.classList.toggle  ('d-none', on);
        btnSpinner.classList.toggle('d-none', !on);
    }

    /** Collect form data into a plain object. */
    function collectForm() {
        const data = {};
        form.querySelectorAll('input[name]:not([type=checkbox]),select[name],textarea[name]').forEach(function (el) {
            if (el.type === 'checkbox') return;
            data[el.name] = el.value.trim();
        });
        // checkbox booleans
        form.querySelectorAll('input[type=checkbox][name]').forEach(function (el) {
            data[el.name] = el.checked;
        });
        return data;
    }

    /** Lightweight number validation before submission.
     *  @returns {string|null}  Field name of first error, or null if valid. */
    function clientValidate() {
        let firstError = null;
        form.querySelectorAll('[required]').forEach(function (el) {
            const errEl = document.getElementById('err-' + el.name);
            if (!errEl) return;
            errEl.textContent = '';
            el.classList.remove('is-invalid');

            if (el.type === 'number') {
                if (el.value === '' || isNaN(parseFloat(el.value))) {
                    el.classList.add('is-invalid');
                    errEl.textContent = 'Please enter a valid number.';
                    firstError = firstError || el.name;
                }
            } else if (el.value.trim() === '') {
                el.classList.add('is-invalid');
                errEl.textContent = 'This field is required.';
                firstError = firstError || el.name;
            }
        });
        return firstError;
    }

    // ── Result rendering ────────────────────────────────────────────────────

    /**
     * Render for "simple-interest", "compound-interest", "loan-amortization",
     * "mortgage" — i.e. financial calculators that share a similar shape.
     */
    function renderFinancial(type, r) {
        const highlight = type === 'simple-interest' ? [
            { label: 'Interest Earned', value: r.currency_symbol + r.interest.toLocaleString(undefined, {maximumFractionDigits: 2}) },
            { label: 'Total',           value: r.currency_symbol + r.total_after.toLocaleString(undefined, {maximumFractionDigits: 2}) },
        ] : type === 'compound-interest' ? [
            { label: 'Total Amount',    value: r.currency_symbol + r.total_amount.toLocaleString(undefined, {maximumFractionDigits: 2}) },
            { label: 'Interest Earned', value: r.currency_symbol + r.interest_earned.toLocaleString(undefined, {maximumFractionDigits: 2}) },
        ] : type === 'loan-amortization' ? [
            { label: 'Monthly Payment', value: r.currency_symbol + r.monthly_payment.toLocaleString(undefined, {maximumFractionDigits: 2}) },
            { label: 'Total Interest',  value: r.currency_symbol + r.total_interest.toLocaleString(undefined, {maximumFractionDigits: 2}) },
        ] : [
            { label: 'Monthly Payment', value: r.currency_symbol + r.monthly_total.toLocaleString(undefined, {maximumFractionDigits: 2}) },
            { label: 'Total Interest',  value: r.currency_symbol + r.total_interest.toLocaleString(undefined, {maximumFractionDigits: 2}) },
        ];

        const detailRows = Object.entries(r)
            .filter(([k]) => !['currency_symbol'].includes(k))
            .map(([k, v]) => {

                // waterfall for few known keys
                const labels = {
                    monthly_payment:        'Monthly Payment (P&I)',
                    monthly_total:          'Total Monthly Payment',
                    monthly_payment_pi:     'Monthly P&I Payment',
                    monthly_tax:            'Monthly Property Tax',
                    monthly_insurance:      'Monthly Insurance',
                    monthly_hoa:            'Monthly HOA',
                    total_payment:          'Total Payment Over Life',
                    total_interest:         'Total Interest',
                    interest_earned:        'Interest Earned',
                    interest:               'Interest',
                    total_after:            'Total after ' + (r.time_years || '') + ' years',
                    principal:              'Principal',
                    loan_amount:            'Loan Amount',
                    down_payment:           'Down Payment',
                    home_price:             'Home Price',
                    rate_percent:           'Annual Rate',
                    annual_rate_pct:        'Annual Rate',
                    loan_term_months:       'Loan Term (months)',
                    loan_term_years:        'Loan Term (years)',
                    compounds_per_year:     'Compounds / Year',
                    time_years:             'Time (years)',
                    ltv_ratio:              'Loan-to-Value Ratio',
                    property_tax:           'Property Tax (annual)',
                    home_insurance:         'Home Insurance (annual)',
                    hoa_monthly:            'HOA (monthly)',
                };
                const numKeys = ['monthly_payment','monthly_total','monthly_payment_pi','monthly_tax',
                                 'monthly_insurance','monthly_hoa','total_payment','total_interest',
                                 'interest_earned','interest','total_after','principal','loan_amount',
                                 'down_payment','home_price','rate_percent','annual_rate_pct','property_tax',
                                 'home_insurance','hoa_monthly','loan_amount'];

                const label = labels[k] ?? k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                if (k === 'currency_symbol') return null;
                const val = (typeof v === 'number')
                    ? r.currency_symbol + v.toLocaleString(undefined, {maximumFractionDigits: 2})
                    : v;
                return '<div class="detail-row"><span>' + label + '</span><span>' + val + '</span></div>';
            })
            .join('');

        const numCards = highlight.map(function (h) {
            return '<div class="result-num-card"><div class="rn-label">' + h.label + '</div>' +
                   '<div class="rn-value">'    + h.value    + '</div></div>';
        }).join('');

        const detailsHtml = detailRows ? '<div class="mt-3 pt-3 border-top">' + detailRows + '</div>' : '';

        return '<div class="result-card border-primary">' +
               '<div class="result-header bg-primary text-white"><i class="bi bi-check-circle-fill me-2"></i><span class="fw-bold">Result</span></div>' +
               '<div class="result-body">' +
               '<div class="result-row-grid mb-3">' + numCards + '</div>' +
               detailsHtml +
               '</div></div>';
    }

    function renderPercentage(r) {
        return '<p class="fs-3 fw-bold text-center text-primary"><i class="bi bi-percent me-2"></i>' +
               r.result.toLocaleString(undefined, {maximumFractionDigits: 4}) + '</p>' +
               '<p class="text-muted text-center">' + r.description + '</p>';
    }

    function renderPercentageChange(r) {
        const cls   = r.change >= 0 ? 'text-success' : 'text-danger';
        const badge = r.change >= 0 ? '+' : '';
        return '<div class="result-highlight">' +
               '<div class="result-label">Change</div>' +
               '<div class="result-value">' + badge + r.change.toLocaleString(undefined, {maximumFractionDigits: 2}) + '%</div>' +
               '<div class="result-meta">' + r.absolute_change + '% absolute change</div>' +
               '</div>' +
               '<div class="result-row-grid mt-3">' +
               '<div class="result-num-card"><div class="rn-label">From</div><div class="rn-value">' + r.from.toLocaleString() + '</div></div>' +
               '<div class="result-num-card"><div class="rn-label">To</div><div class="rn-value">' + r.to.toLocaleString() + '</div></div>' +
               '</div>';
    }

    function renderGpa(r) {
        return '<div class="result-highlight">' +
               '<div class="result-label">GPA</div>' +
               '<div class="result-value">' + r.gpa.toLocaleString() + ' / 4.0</div>' +
               '<div class="result-meta">Letter grade: ' + r.letter + '</div>' +
               '</div>' +
               '<div class="result-row-grid mt-3">' +
               '<div class="result-num-card"><div class="rn-label">Total Credits</div><div class="rn-value">' + r.total_credits + '</div></div>' +
               '<div class="result-num-card"><div class="rn-label">Total Points</div><div class="rn-value">' + r.total_points + '</div></div>' +
               '</div>';
    }

    function renderBMI(r) {
        return '<div class="result-highlight" style="background:linear-gradient(135deg,#11998e,#38ef7d)">' +
               '<div class="result-label">BMI</div>' +
               '<div class="result-value">' + r.bmi + '</div>' +
               '<div class="result-meta">' + r.category + '</div>' +
               '</div>' +
               '<div class="result-row-grid mt-3">' +
               '<div class="result-num-card"><div class="rn-label">Height</div><div class="rn-value">' + r.height_cm + ' cm / ' + r.height_feet + ' ft</div></div>' +
               '<div class="result-num-card"><div class="rn-label">Weight</div><div class="rn-value">' + r.weight_kg + ' kg / ' + r.weight_lbs + ' lbs</div></div>' +
               '</div>' +
               '<div class="result-num-card mt-3"><div class="rn-label">Healthy Weight Range</div><div class="rn-value">' + r.min_healthy + ' – ' + r.max_healthy + ' kg</div></div>' +
               '<p class="text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>' + r.description + '</p>';
    }

    function renderResult(type, r) {
        switch (type) {
            case 'simple-interest':
            case 'compound-interest':
            case 'loan-amortization':
            case 'mortgage':
                return renderFinancial(type, r);
            case 'percentage':
                return renderPercentage(r);
            case 'percentage-change':
                return renderPercentageChange(r);
            case 'gpa':
                return renderGpa(r);
            case 'bmi':
                return renderBMI(r);
            default:
                return '<pre>' + JSON.stringify(r, null, 2) + '</pre>';
        }
    }

    function addCopyButtons() {
        resultsPanel.querySelectorAll('.result-highlight, .result-value').forEach(function (el) {
            if (el.querySelector('.btn-calc-copy')) return;
            const btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'btn btn-outline-secondary btn-calc-copy mt-2';
            btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
            btn.addEventListener('click', function () {
                const text = el.textContent.trim();
                if (!text) return;
                navigator.clipboard.writeText(text).then(function () {
                    btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
                });
            });
            el.appendChild(btn);
        });
    }

    // ── submit handler ──────────────────────────────────────────────────────

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const err = clientValidate();
        if (err) {
            const el = form.querySelector('[name="' + err + '"]');
            if (el) el.focus();
            return;
        }

        const data   = collectForm();
        const typeEl = form.querySelector('input[name="calc_type"]');
        if (!typeEl) { alert('Calc type not found in form.'); return; }
        const type = typeEl.value;

        setLoading(true);
        resultsPanel.innerHTML = '';
        resultsPanel.classList.add('d-none');

        fetch(endpoint, {
            method:   'POST',
            headers:  { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:     JSON.stringify(data),
        })
        .then(function (resp) { return resp.json(); })
        .then(function (resp) {
            setLoading(false);

            if (!resp.success) {
                resultsPanel.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' +
                                         (resp.error || 'Calculation failed. Please check your inputs.') + '</div>';
                resultsPanel.classList.remove('d-none');
                return;
            }

            const html = renderResult(type, resp.result);
            resultsPanel.innerHTML = html;
            resultsPanel.classList.remove('d-none');
            addCopyButtons();
            resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(function (err) {
            setLoading(false);
            console.error('[calculator.js] Network error:', err);
            resultsPanel.innerHTML = '<div class="alert alert-danger"><i class="bi bi-wifi-off me-2"></i>' +
                                     'Network error. Please check your connection and try again.</div>';
            resultsPanel.classList.remove('d-none');
        });
    });

    // ── clear field errors on input ─────────────────────────────────────────
    form.querySelectorAll('.calc-field-input').forEach(function (el) {
        el.addEventListener('input', function () {
            el.classList.remove('is-invalid');
            const errEl = document.getElementById('err-' + el.name);
            if (errEl) errEl.textContent = '';
        });
    });

    // ── keyboard shortcut: Ctrl/Cmd + Enter = submit ────────────────────────
    form.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });

})();
