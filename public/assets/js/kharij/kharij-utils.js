/**
 * kharij-utils.js
 *
 * Shared utilities for Kharij forms — numeral conversion, parsing, and
 * automatic dag/land area calculations.
 *
 * Depends on: nothing
 * Load before: kharij-land-calculator.js, kharij-tax-calculator.js
 *
 * Provides global functions:
 *   toBnNum(num)         – English digits → Bengali digits
 *   bnToEn(str)          – Bengali digits → English digits
 *   parseNum(val)        – Parse Bengali/English numeric input
 *   fmt(n)               – Format number (trim trailing zeros)
 *   calcDagTotalArea(dagEntry)   – Calculate dag_area_shottangsho from total_dag_area
 *   calcDagKhash(dagEntry)       – Calculate khash_area from single_area × owner share
 *   updateDagSummary()           – Sum all dag areas into summary fields
 *   recalcAllDags()              – Recalculate all dags and summary
 *   calcLandWords()              – Generate Bengali words for total land area
 *   runAll()                     – Run all auto-calculations
 */
(function () {
  'use strict';

  /* global bnToEn, toBnNum, parseNum, fmt, calcDagTotalArea, calcDagKhash, updateDagSummary, recalcAllDags, calcLandWords */

  // ──────────────────────────────────────────────
  // Numeral Conversion
  // ──────────────────────────────────────────────

  const BN_DIGITS = ['\u09E6', '\u09E7', '\u09E8', '\u09E9', '\u09EA', '\u09EB', '\u09EC', '\u09ED', '\u09EE', '\u09EF',];

  /** English digits → Bengali digits */
  window.toBnNum = function toBnNum(num) {
    if (num === '' || num === null || num === undefined) return '';
    return String(num).split('').map((c) => {
      return c === '.' ? '.' : (c === '-' ? '-' : (BN_DIGITS[parseInt(c, 10)] || c));
    }).join('');
  };

  /** Bengali digits → English digits */
  window.bnToEn = function bnToEn(str) {
    if (!str) return str;
    return str.replace(/[\u09E6-\u09EF]/g, (m) => {
      return String.fromCharCode(m.charCodeAt(0) - 0x09E6 + 48);
    });
  };

  // ──────────────────────────────────────────────
  // Numeric Parsing & Formatting
  // ──────────────────────────────────────────────

  /** Parse a Bengali/English numeric string into a float */
  window.parseNum = function parseNum(val) {
    if (!val || val === '') return NaN;
    const cleaned = bnToEn(val).replace(/[^\d.]/g, '');
    return parseFloat(cleaned);
  };

  /** Format number: trim trailing zeros after decimal */
  window.fmt = function fmt(n) {
    if (isNaN(n) || n === 0) return '0';
    return n.toFixed(4).replace(/\.?0+$/, '');
  };

  // ──────────────────────────────────────────────
  // Bangladeshi Land Unit Constants
  // ──────────────────────────────────────────────

  const SHOTTANGSHO_PER_ACRE = 100;
  const SQFT_PER_SHOTTANGSHO = 435.6; // eslint-disable-line no-unused-vars
  const SQFT_PER_ACRE = 43560; // eslint-disable-line no-unused-vars

  // ──────────────────────────────────────────────
  // Per-Dag Auto-Calculation Functions
  // ──────────────────────────────────────────────

  function getFirstOwnerShare() {
    const el = document.querySelector('[data-owner-field="share"]');
    return el ? parseNum(el.value) : NaN;
  }

  /** Calculate dag_area_shottangsho from total_dag_area */
  window.calcDagTotalArea = function calcDagTotalArea(dagEntry) {
    const totalDagInput = dagEntry.querySelector('[data-dag-field="total_dag_area"]');
    const dagAreaStgInput = dagEntry.querySelector('[data-dag-field="dag_area_shottangsho"]');
    if (!totalDagInput || !dagAreaStgInput) return;
    const acres = parseNum(totalDagInput.value);
    if (!isNaN(acres) && acres > 0) {
      dagAreaStgInput.value = fmt(acres * SHOTTANGSHO_PER_ACRE);
    }
  };

  /** Calculate khash_area from single_area × owner share */
  window.calcDagKhash = function calcDagKhash(dagEntry) {
    const singleInput = dagEntry.querySelector('[data-dag-field="single_area"]');
    const khashInput = dagEntry.querySelector('[data-dag-field="khash_area"]');
    const khashStgInput = dagEntry.querySelector('[data-dag-field="khash_area_shottangsho"]');
    const shareVal = getFirstOwnerShare();
    if (!singleInput || !khashInput || !khashStgInput) return;
    const single = parseNum(singleInput.value);
    if (!isNaN(single) && single > 0 && !isNaN(shareVal) && shareVal > 0) {
      const khashAcres = single * shareVal;
      khashInput.value = fmt(khashAcres);
      khashStgInput.value = fmt(khashAcres * SHOTTANGSHO_PER_ACRE);
    }
  };

  /** Sum all dag areas into summary fields */
  window.updateDagSummary = function updateDagSummary() {
    let totalDagAcres = 0, totalDagStg = 0;
    document.querySelectorAll('#dag-entries-container .dag-entry').forEach((entry) => {
      totalDagAcres += parseNum(entry.querySelector('[data-dag-field="total_dag_area"]').value) || 0;
      totalDagStg += parseNum(entry.querySelector('[data-dag-field="dag_area_shottangsho"]').value) || 0;
    });
    const summaryAcr = document.getElementById('total_dag_area_summary_acre');
    const summaryStg = document.getElementById('total_dag_area_summary_shottangsho');
    if (summaryAcr) summaryAcr.value = fmt(totalDagAcres);
    if (summaryStg) summaryStg.value = fmt(totalDagStg);
  };

  /** Recalculate all dags and update summary */
  window.recalcAllDags = function recalcAllDags() {
    document.querySelectorAll('#dag-entries-container .dag-entry').forEach((entry) => {
      calcDagTotalArea(entry);
      calcDagKhash(entry);
    });
    updateDagSummary();
  };

  /** Generate Bengali words for total land area */
  window.calcLandWords = function calcLandWords() {
    const tla = document.getElementById('total_land_area');
    const tlw = document.getElementById('total_land_words');
    if (!tla || !tlw) return;
    const acres = parseNum(tla.value);
    if (isNaN(acres) || acres <= 0) return;
    const acrePart = Math.floor(acres);
    const totalSh = acres * 100;
    const bighaPart = Math.floor(totalSh / 33);
    const sotangshoPart = Math.floor(totalSh % 33);
    tlw.value = acrePart + ' একর ' + bighaPart + ' শতক ' + sotangshoPart + ' অযুতাংশ 00 লক্ষাংশ';
  };

  /** Run all auto-calculations */
  window.runAll = function runAll() {
    recalcAllDags();
    calcLandWords();
  };

})();
