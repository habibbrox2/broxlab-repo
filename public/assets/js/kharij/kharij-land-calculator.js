/**
 * kharij-land-calculator.js
 *
 * Land calculator panel UI — unit conversion between acre/shottangsho/sqft,
 * auto-calculation triggers for dag entries and land words.
 *
 * Depends on: kharij-utils.js (toBnNum, bnToEn, parseNum, fmt, calcDagTotalArea,
 *             calcDagKhash, updateDagSummary, recalcAllDags, calcLandWords, runAll)
 *
 * Provides:
 *   - Toggle calculator panel (#toggle-land-calc)
 *   - Unit conversion (#calc-convert-btn)
 *   - Auto-fill all calculations (#calc-auto-fill)
 *   - Event delegation for dag & owner share changes
 */
(function () {
  'use strict';

  function initLandCalculator() {
    // ── Toggle calculator panel ──
    const toggle = document.getElementById('toggle-land-calc');
    const panel = document.getElementById('land-calc-panel');
    if (toggle && panel) {
      toggle.addEventListener('click', () => {
        panel.classList.toggle('open');
        toggle.classList.toggle('bg-amber-200');
      });
    }

    // ── Auto-calc for total_land_area changes ──
    const tla = document.getElementById('total_land_area');
    if (tla) {
      tla.addEventListener('input', () => {
        if (window.calcLandWords) window.calcLandWords();
        if (window.recalcAllDags) window.recalcAllDags();
      });
      tla.addEventListener('change', () => {
        if (window.calcLandWords) window.calcLandWords();
        if (window.recalcAllDags) window.recalcAllDags();
      });
    }

    // ── Per-dag auto-calc via event delegation ──
    const dagContainer = document.getElementById('dag-entries-container');
    if (dagContainer) {
      dagContainer.addEventListener('input', (e) => {
        const dagEntry = e.target.closest('.dag-entry');
        if (!dagEntry) return;
        const field = e.target.dataset.dagField;
        if (field === 'total_dag_area') {
          if (window.calcDagTotalArea) window.calcDagTotalArea(dagEntry);
          if (window.updateDagSummary) window.updateDagSummary();
        } else if (field === 'single_area') {
          if (window.calcDagKhash) window.calcDagKhash(dagEntry);
        }
      });
      dagContainer.addEventListener('change', (e) => {
        const dagEntry = e.target.closest('.dag-entry');
        if (!dagEntry) return;
        const field = e.target.dataset.dagField;
        if (field === 'total_dag_area') {
          if (window.calcDagTotalArea) window.calcDagTotalArea(dagEntry);
          if (window.updateDagSummary) window.updateDagSummary();
        } else if (field === 'single_area') {
          if (window.calcDagKhash) window.calcDagKhash(dagEntry);
        }
      });
    }

    // ── Listen for owner share changes ──
    const ownerContainer = document.getElementById('owners-container');
    if (ownerContainer) {
      ownerContainer.addEventListener('input', (e) => {
        const shareInput = e.target.closest('[data-owner-field="share"]');
        if (shareInput) {
          if (window.recalcAllDags) window.recalcAllDags();
          if (window.calcLandWords) window.calcLandWords();
        }
      });
      ownerContainer.addEventListener('change', (e) => {
        const shareInput = e.target.closest('[data-owner-field="share"]');
        if (shareInput) {
          if (window.recalcAllDags) window.recalcAllDags();
          if (window.calcLandWords) window.calcLandWords();
        }
      });
    }

    // ── Unit converter ──
    function doConvert() {
      const SHOTTANGSHO_PER_ACRE = 100;
      const SQFT_PER_SHOTTANGSHO = 435.6;
      const SQFT_PER_ACRE = 43560;

      const input = document.getElementById('calc-input-value');
      const unit = document.getElementById('calc-input-unit');
      const results = document.getElementById('calc-results');
      if (!input || !unit || !results) return;

      const val = parseFloat(input.value);
      if (isNaN(val) || val <= 0) {
        results.innerHTML = '<div class="calc-result-item" style="grid-column:1/-1;color:#94a3b8;">\u098F\u0995\u099F\u09BF \u09AC\u09C8\u09A7 \u09B8\u0982\u0996\u09CD\u09AF\u09BE \u09B2\u09BF\u0996\u09C1\u09A8</div>';
        return;
      }

      let acres, stg, sqft;
      switch (unit.value) {
        case 'acre':
          acres = val; stg = val * SHOTTANGSHO_PER_ACRE; sqft = val * SQFT_PER_ACRE;
          break;
        case 'shottangsho':
          stg = val; acres = val / SHOTTANGSHO_PER_ACRE; sqft = val * SQFT_PER_SHOTTANGSHO;
          break;
        case 'sqft':
          sqft = val; acres = val / SQFT_PER_ACRE; stg = val / SQFT_PER_SHOTTANGSHO;
          break;
      }

      const fmt = window.fmt || function (n) {
        if (isNaN(n) || n === 0) return '0';
        return n.toFixed(4).replace(/\.?0+$/, '');
      };

      results.innerHTML =
        `<div class="calc-result-item"><span>\u098F\u0995\u09B0:</span><strong>${ fmt(acres) }</strong></div>` +
        `<div class="calc-result-item"><span>\u09B6\u09A4\u09BE\u0982\u09B6:</span><strong>${ fmt(stg) }</strong></div>` +
        `<div class="calc-result-item"><span>\u09AC\u09B0\u09CD\u0997\u09AB\u09C1\u099F:</span><strong>${ Math.round(sqft).toLocaleString() }</strong></div>` +
        `<div class="calc-result-item"><span>\u0995\u09BE\u09A0\u09BE (\u09ED\u09E8\u09E6 \u09AC\u09B0\u09CD\u0997\u09AB\u09C1\u099F):</span><strong>${ fmt(sqft / 720) }</strong></div>` +
        `<div class="calc-result-item"><span>\u09AC\u09BF\u0998\u09BE (\u09E9\u09E9 \u09B6\u09A4\u09BE\u0982\u09B6):</span><strong>${ fmt(stg / 33) }</strong></div>` +
        `<div class="calc-result-item"><span>\u09B9\u09C7\u0995\u09CD\u099F\u09B0:</span><strong>${ fmt(acres / 2.471) }</strong></div>`;
    }

    const convertBtn = document.getElementById('calc-convert-btn');
    if (convertBtn) {
      convertBtn.addEventListener('click', doConvert);
      const cvInput = document.getElementById('calc-input-value');
      if (cvInput) cvInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') doConvert();
      });
    }

    // ── Auto-fill button ──
    const autoBtn = document.getElementById('calc-auto-fill');
    if (autoBtn) {
      autoBtn.addEventListener('click', () => {
        if (window.runAll) window.runAll();
      });
    }

    // ── Run initial calculation after a brief delay ──
    setTimeout(() => {
      if (window.runAll) window.runAll();
    }, 200);
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLandCalculator);
  } else {
    initLandCalculator();
  }
})();
