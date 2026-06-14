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
'use strict';

// ── DOM references ──────────────────────────────────────────────────────
const form = document.getElementById('calculator-form');
const resultsPanel = document.getElementById('calc-results');
const submitBtn = document.getElementById('calc-submit-btn');
const btnLabel = document.getElementById('calc-btn-label');
const btnSpinner = document.getElementById('calc-spinner');

// Wrap execution in guard – only run on calculator pages
if (form) {
  const endpoint = form.action;

  // ── helpers ─────────────────────────────────────────────────────────────
  function setLoading(on) {
    submitBtn.disabled = on;
    btnLabel.classList.toggle('hidden', on);
    btnSpinner.classList.toggle('hidden', !on);
  }

  /** Collect form data into a plain object. */
  function collectForm() {
    const data = {};
    const typeEl = form.querySelector('input[name="calc_type"]');
    const type = typeEl ? typeEl.value : '';

    form.querySelectorAll('input[name]:not([type=checkbox]),select[name],textarea[name]').forEach((el) => {
      if (el.type === 'checkbox') return;
      const value = el.value.trim();

      // Special parsing for GPA courses JSON
      if (el.name === 'courses' && type === 'gpa' && value) {
        try {
          data[el.name] = JSON.parse(value);
        } catch (e) {
          data[el.name] = value; // Send as-is, let backend handle error
        }
      } else {
        data[el.name] = value;
      }
    });
    // checkbox booleans
    form.querySelectorAll('input[type=checkbox][name]').forEach((el) => {
      data[el.name] = el.checked;
    });
    return data;
  }

  /** Lightweight form validation before submission.
     *  @returns {string|null}  Field name of first error, or null if valid. */
  function clientValidate() {
    let firstError = null;
    const typeEl = form.querySelector('input[name="calc_type"]');
    const type = typeEl ? typeEl.value : '';

    form.querySelectorAll('[required]').forEach((el) => {
      const errEl = document.getElementById(`err-${ el.name}`);
      if (!errEl) return;
      errEl.textContent = '';
      el.classList.remove('is-invalid');

      if (el.type === 'number') {
        if (el.value === '' || isNaN(parseFloat(el.value))) {
          el.classList.add('is-invalid');
          errEl.textContent = 'Please enter a valid number.';
          firstError = firstError || el.name;
        }
      } else if (el.type === 'date') {
        // Validate date format (HTML5 date input uses YYYY-MM-DD)
        if (el.value === '' || !el.value.match(/^\d{4}-\d{2}-\d{2}$/)) {
          el.classList.add('is-invalid');
          errEl.textContent = 'Please enter a valid date (YYYY-MM-DD).';
          firstError = firstError || el.name;
        }
      } else if (el.name === 'courses' && type === 'gpa') {
        // Validate GPA JSON format
        if (el.value.trim() === '') {
          el.classList.add('is-invalid');
          errEl.textContent = 'This field is required.';
          firstError = firstError || el.name;
        } else {
          try {
            const parsed = JSON.parse(el.value);
            if (!Array.isArray(parsed) || parsed.length === 0) {
              throw new Error('Must be non-empty array');
            }
          } catch (e) {
            el.classList.add('is-invalid');
            errEl.textContent = 'Invalid JSON format. Expected: [{"credit_hours":3,"grade_point":4.0}, ...]';
            firstError = firstError || el.name;
          }
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
      { label: 'Interest Earned', value: r.currency_symbol + r.interest.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
      { label: 'Total', value: r.currency_symbol + r.total_after.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
    ] : type === 'compound-interest' ? [
      { label: 'Total Amount', value: r.currency_symbol + r.total_amount.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
      { label: 'Interest Earned', value: r.currency_symbol + r.interest_earned.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
    ] : type === 'loan-amortization' ? [
      { label: 'Monthly Payment', value: r.currency_symbol + r.monthly_payment.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
      { label: 'Total Interest', value: r.currency_symbol + r.total_interest.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
    ] : [
      { label: 'Monthly Payment', value: r.currency_symbol + r.monthly_total.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
      { label: 'Total Interest', value: r.currency_symbol + r.total_interest.toLocaleString(undefined, { maximumFractionDigits: 2, }), },
    ];

    const detailRows = Object.entries(r)
      .filter(([k,]) => !['currency_symbol',].includes(k))
      .map(([k, v,]) => {

        // waterfall for few known keys
        const labels = {
          monthly_payment: 'Monthly Payment (P&I)',
          monthly_total: 'Total Monthly Payment',
          monthly_payment_pi: 'Monthly P&I Payment',
          monthly_tax: 'Monthly Property Tax',
          monthly_insurance: 'Monthly Insurance',
          monthly_hoa: 'Monthly HOA',
          total_payment: 'Total Payment Over Life',
          total_interest: 'Total Interest',
          interest_earned: 'Interest Earned',
          interest: 'Interest',
          total_after: `Total after ${ r.time_years || '' } years`,
          principal: 'Principal',
          loan_amount: 'Loan Amount',
          down_payment: 'Down Payment',
          home_price: 'Home Price',
          rate_percent: 'Annual Rate',
          annual_rate_pct: 'Annual Rate',
          loan_term_months: 'Loan Term (months)',
          loan_term_years: 'Loan Term (years)',
          compounds_per_year: 'Compounds / Year',
          time_years: 'Time (years)',
          ltv_ratio: 'Loan-to-Value Ratio',
          property_tax: 'Property Tax (annual)',
          home_insurance: 'Home Insurance (annual)',
          hoa_monthly: 'HOA (monthly)',
        };

        const label = labels[k] ?? k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        if (k === 'currency_symbol') return null;
        const val = (typeof v === 'number')
          ? r.currency_symbol + v.toLocaleString(undefined, { maximumFractionDigits: 2, })
          : v;
        return `<div class="detail-row"><span>${ label }</span><span>${ val }</span></div>`;
      })
      .join('');

    const numCards = highlight.map((h) => {
      return `<div class="result-num-card"><div class="rn-label">${ h.label }</div>` +
                `<div class="rn-value">${ h.value }</div></div>`;
    }).join('');

    const detailsHtml = detailRows ? `<div class="mt-3 pt-3 border-t border-slate-200">${ detailRows }</div>` : '';

    return '<div class="result-card border-primary">' +
            '<div class="result-header bg-indigo-600 text-white"><i class="lucide lucide-check-circle mr-2"></i><span class="font-bold">Result</span></div>' +
            '<div class="result-body">' +
            `<div class="result-row-grid mb-3">${ numCards }</div>${
              detailsHtml
            }</div></div>`;
  }

  function renderPercentage(r) {
    return `<p class="text-2xl font-bold text-center text-indigo-600"><i class="lucide lucide-percent mr-2"></i>${
      r.result.toLocaleString(undefined, { maximumFractionDigits: 4, }) }</p>` +
            `<p class="text-slate-500 text-center">${ r.description }</p>`;
  }

  function renderPercentageChange(r) {
    const badge = r.change >= 0 ? '+' : '';
    return '<div class="result-highlight">' +
            '<div class="result-label">Change</div>' +
            `<div class="result-value">${ badge }${r.change.toLocaleString(undefined, { maximumFractionDigits: 2, }) }%</div>` +
            `<div class="result-meta">${ r.absolute_change }% absolute change</div>` +
            '</div>' +
            '<div class="result-row-grid mt-3">' +
            `<div class="result-num-card"><div class="rn-label">From</div><div class="rn-value">${ r.from.toLocaleString() }</div></div>` +
            `<div class="result-num-card"><div class="rn-label">To</div><div class="rn-value">${ r.to.toLocaleString() }</div></div>` +
            '</div>';
  }

  function renderGpa(r) {
    return '<div class="result-highlight">' +
            '<div class="result-label">GPA</div>' +
            `<div class="result-value">${ r.gpa.toLocaleString() } / 4.0</div>` +
            `<div class="result-meta">Letter grade: ${ r.letter }</div>` +
            '</div>' +
            '<div class="result-row-grid mt-3">' +
            `<div class="result-num-card"><div class="rn-label">Total Credits</div><div class="rn-value">${ r.total_credits }</div></div>` +
            `<div class="result-num-card"><div class="rn-label">Total Points</div><div class="rn-value">${ r.total_points }</div></div>` +
            '</div>';
  }

  function renderBMI(r) {
    return '<div class="result-highlight" style="background:linear-gradient(135deg,#11998e,#38ef7d)">' +
            '<div class="result-label">BMI</div>' +
            `<div class="result-value">${ r.bmi }</div>` +
            `<div class="result-meta">${ r.category }</div>` +
            '</div>' +
            '<div class="result-row-grid mt-3">' +
            `<div class="result-num-card"><div class="rn-label">Height</div><div class="rn-value">${ r.height_cm } cm / ${ r.height_feet } ft</div></div>` +
            `<div class="result-num-card"><div class="rn-label">Weight</div><div class="rn-value">${ r.weight_kg } kg / ${ r.weight_lbs } lbs</div></div>` +
            '</div>' +
            `<div class="result-num-card mt-3"><div class="rn-label">Healthy Weight Range</div><div class="rn-value">${ r.min_healthy } – ${ r.max_healthy } kg</div></div>` +
            `<p class="text-slate-500 mt-2 mb-0"><i class="lucide lucide-info mr-1"></i>${ r.description }</p>`;
  }

  function renderGraphing(r) {
    const rows = r.points.map((point) => {
      return `<tr><td class="px-3 py-2">${ point.x }</td><td class="px-3 py-2">${ point.y }</td></tr>`;
    }).join('');
    return '<div class="result-card border-primary">' +
            '<div class="result-header bg-indigo-600 text-white"><i class="lucide lucide-sliders-horizontal mr-2"></i><span class="font-bold">Graphing Sample Values</span></div>' +
            `<div class="result-body p-3"><p class="mb-3">Expression: <code>${ escapeHtml(r.expression) }</code></p>` +
            `<div class="overflow-x-auto"><table class="min-w-full text-sm divide-y divide-slate-200"><thead class="bg-slate-100"><tr><th class="px-3 py-2 text-left font-semibold text-slate-700">X</th><th class="px-3 py-2 text-left font-semibold text-slate-700">Y</th></tr></thead><tbody class="divide-y divide-slate-100">${ rows }</tbody></table></div></div></div>`;
  }

  function renderStandardScientific(r) {
    return '<div class="result-card border-primary">' +
            '<div class="result-header bg-indigo-600 text-white"><i class="lucide lucide-calculator mr-2"></i><span class="font-bold">Result</span></div>' +
            `<div class="result-body p-3"><p class="text-2xl font-bold text-center text-indigo-600">${ r.result }</p>` +
            `<p class="text-slate-500 text-center">Expression: <code>${ escapeHtml(r.expression) }</code></p></div></div>`;
  }

  function renderProgrammer(r) {
    return '<div class="result-card border-primary">' +
            '<div class="result-header bg-indigo-600 text-white"><i class="lucide lucide-code mr-2"></i><span class="font-bold">Programmer Conversion</span></div>' +
            '<div class="result-body p-3">' +
            '<div class="result-row-grid">' +
            `<div class="result-num-card"><div class="rn-label">Decimal</div><div class="rn-value">${ r.decimal }</div></div>` +
            `<div class="result-num-card"><div class="rn-label">Binary</div><div class="rn-value">${ r.binary }</div></div>` +
            `<div class="result-num-card"><div class="rn-label">Octal</div><div class="rn-value">${ r.octal }</div></div>` +
            `<div class="result-num-card"><div class="rn-label">Hex</div><div class="rn-value">${ r.hex }</div></div>` +
            '</div></div></div>';
  }

  function renderDateCalculation(r) {
    let rows = '';
    if (r.difference_days !== undefined) {
      rows += `<div class="detail-row"><span>Date Difference</span><span>${ r.difference_days } days</span></div>`;
    }
    if (r.add_date !== undefined) {
      rows += `<div class="detail-row"><span>Add Days</span><span>${ r.add_days } → ${ r.add_date }</span></div>`;
    }
    if (r.subtract_date !== undefined) {
      rows += `<div class="detail-row"><span>Subtract Days</span><span>${ r.subtract_days } → ${ r.subtract_date }</span></div>`;
    }
    return '<div class="result-card border-primary">' +
            '<div class="result-header bg-indigo-600 text-white"><i class="lucide lucide-calendar-clock mr-2"></i><span class="font-bold">Date Calculation</span></div>' +
            '<div class="result-body p-3">' +
            `<div class="detail-row"><span>Start Date</span><span>${ r.start_date }</span></div>${ rows
            }</div></div>`;
  }

  function renderConverter(r) {
    return '<div class="result-card border-primary">' +
            '<div class="result-header bg-indigo-600 text-white"><i class="lucide lucide-arrow-left-right mr-2"></i><span class="font-bold">Converted Value</span></div>' +
            '<div class="result-body p-3">' +
            `<div class="detail-row"><span>${ r.from_unit }</span><span>${ r.value }</span></div>` +
            `<div class="detail-row"><span>${ r.to_unit }</span><span>${ r.converted }</span></div>` +
            '</div></div>';
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
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
    case 'standard':
    case 'scientific':
      return renderStandardScientific(r);
    case 'graphing':
      return renderGraphing(r);
    case 'programmer':
      return renderProgrammer(r);
    case 'date-calculation':
      return renderDateCalculation(r);
    case 'currency-converter':
    case 'volume-converter':
    case 'length-converter':
    case 'weight-converter':
    case 'temperature-converter':
    case 'energy-converter':
    case 'area-converter':
    case 'speed-converter':
    case 'time-converter':
    case 'power-converter':
    case 'data-converter':
    case 'pressure-converter':
    case 'angle-converter':
      return renderConverter(r);
    default:
      return `<pre>${ JSON.stringify(r, null, 2) }</pre>`;
    }
  }

  function addCopyButtons() {
    resultsPanel.querySelectorAll('.result-highlight, .result-value').forEach((el) => {
      if (el.querySelector('.btn-calc-copy')) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition-colors btn-calc-copy mt-2';
      btn.innerHTML = '<i class="lucide lucide-clipboard"></i> Copy';
      btn.addEventListener('click', () => {
        const text = el.textContent.trim();
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
          btn.innerHTML = '<i class="lucide lucide-check"></i> Copied!';
          setTimeout(() => { btn.innerHTML = '<i class="lucide lucide-clipboard"></i> Copy'; }, 2000);
        });
      });
      el.appendChild(btn);
    });
  }

  // ── submit handler ──────────────────────────────────────────────────────

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const err = clientValidate();
    if (err) {
      const el = form.querySelector(`[name="${ err }"]`);
      if (el) el.focus();
      return;
    }

    const data = collectForm();
    const typeEl = form.querySelector('input[name="calc_type"]');
    if (!typeEl) { alert('Calc type not found in form.'); return; }
    const type = typeEl.value;

    setLoading(true);
    resultsPanel.innerHTML = '';
    resultsPanel.classList.add('hidden');

    // Collect CSRF token if available
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfMeta) {
      headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');
    }

    fetch(endpoint, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(data),
    })
      .then(async (resp) => {
        setLoading(false);

        // Detect redirects (server redirected POST to a GET) or non-JSON responses
        if (resp.redirected || (resp.status >= 300 && resp.status < 400)) {
          const location = resp.headers.get('Location') || resp.url || '';
          resultsPanel.innerHTML = '<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200"><i class="lucide lucide-alert-triangle mr-2"></i>' +
                        `Request was redirected by server (status ${ resp.status }). Redirect target: ${ escapeHtml(location) }</div>`;
          resultsPanel.classList.remove('hidden');
          console.error('[calculator.js] POST was redirected to GET. Response status:', resp.status, 'location:', location);
          return;
        }

        const ct = resp.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          const text = await resp.text();
          resultsPanel.innerHTML = '<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200"><i class="lucide lucide-alert-triangle mr-2"></i>' +
                        `Unexpected server response (expected JSON). Status: ${ resp.status }. Response preview:<pre style="max-height:200px;overflow:auto;">${ escapeHtml(String(text).slice(0, 2000)) }</pre></div>`;
          resultsPanel.classList.remove('hidden');
          console.error('[calculator.js] Non-JSON response from server:', resp.status, resp.url, text);
          return;
        }

        const json = await resp.json();

        if (!json.success) {
          resultsPanel.innerHTML = `<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200"><i class="lucide lucide-alert-triangle mr-2"></i>${
            json.error || 'Calculation failed. Please check your inputs.' }</div>`;
          resultsPanel.classList.remove('hidden');
          return;
        }

        const html = renderResult(type, json.result);
        resultsPanel.innerHTML = html;
        resultsPanel.classList.remove('hidden');
        addCopyButtons();
        resultsPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest', });
      })
      .catch((err) => {
        setLoading(false);
        console.error('[calculator.js] Network error:', err);
        resultsPanel.innerHTML = '<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200"><i class="lucide lucide-wifi-off mr-2"></i>' +
                    'Network error. Please check your connection and try again.</div>';
        resultsPanel.classList.remove('hidden');
      });
  });

  // ── clear field errors on input ─────────────────────────────────────────
  form.querySelectorAll('.calc-field-input').forEach((el) => {
    el.addEventListener('input', () => {
      el.classList.remove('is-invalid');
      const errEl = document.getElementById(`err-${ el.name}`);
      if (errEl) errEl.textContent = '';
    });
  });

  // ── keyboard shortcut: Ctrl/Cmd + Enter = submit ────────────────────────
  form.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      form.dispatchEvent(new Event('submit'));
    }
  });
}

export {};

