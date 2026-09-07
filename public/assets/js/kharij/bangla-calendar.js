/**
 * bangla-calendar.js
 *
 * Converts Gregorian dates (dd-mm-yyyy) to traditional Bengali calendar dates.
 * Based on the fixed solar month boundaries used in Bangladesh land records.
 *
 * Bengali month boundaries (fixed solar):
 *   বৈশাখ   14 Apr – 14 May       জ্যৈষ্ঠ    15 May – 14 Jun
 *   আষাঢ়    15 Jun – 15 Jul       শ্রাবণ     16 Jul – 15 Aug
 *   ভাদ্র    16 Aug – 15 Sep       আশ্বিন     16 Sep – 15 Oct
 *   কার্তিক  16 Oct – 14 Nov       অগ্রহায়ণ  15 Nov – 14 Dec
 *   পৌষ     15 Dec – 14 Jan       মাঘ        15 Jan – 13 Feb
 *   ফাল্গুন  14 Feb – 14 Mar       চৈত্র      15 Mar – 13 Apr
 *
 * Bengali Year (Bangabd):
 *   বছরের শুরু 1 বৈশাখ = 14 April (Gregorian)
 *   Before 14 Apr: bnYear = gregYear - 594
 *   On/after 14 Apr: bnYear = gregYear - 593
 *
 * Usage:
 *   gregorianToBangla('21-05-2026') → "৭ জ্যৈষ্ঠ ১৪৩৩"
 *   gregorianToBangla('10-01-2026') → "২৭ পৌষ ১৪৩২"
 *   gregorianToBangla('29-06-2025') → "১৫ আষাঢ় ১৪৩২"
 *   gregorianToBangla('15-12-2026') → "১ পৌষ ১৪৩৩"
 *   gregorianToBangla('14-04-2026') → "১ বৈশাখ ১৪৩৩"
 */
(function () {
  'use strict';

  // Bengali month definitions: [name, startMonth(1-12), startDay]
  // Order follows the Gregorian calendar from বৈশাখ (Apr) to চৈত্র (Mar)
  const BN_MONTHS = [
    { name: 'বৈশাখ', month: 4, day: 14, },
    { name: 'জ্যৈষ্ঠ', month: 5, day: 15, },
    { name: 'আষাঢ়', month: 6, day: 15, },
    { name: 'শ্রাবণ', month: 7, day: 16, },
    { name: 'ভাদ্র', month: 8, day: 16, },
    { name: 'আশ্বিন', month: 9, day: 16, },
    { name: 'কার্তিক', month: 10, day: 16, },
    { name: 'অগ্রহায়ণ', month: 11, day: 15, },
    { name: 'পৌষ', month: 12, day: 15, },
    { name: 'মাঘ', month: 1, day: 15, },
    { name: 'ফাল্গুন', month: 2, day: 14, },
    { name: 'চৈত্র', month: 3, day: 15, },
  ];

  const EN_MONTH_MAP = {
    'january': 1, 'february': 2, 'march': 3, 'april': 4,
    'may': 5, 'june': 6, 'july': 7, 'august': 8,
    'september': 9, 'october': 10, 'november': 11, 'december': 12,
  };

  const BN_MONTH_MAP = {
    'জানুয়ারী': 1, 'ফেব্রুয়ারী': 2, 'মার্চ': 3, 'এপ্রিল': 4,
    'মে': 5, 'জুন': 6, 'জুলাই': 7, 'আগস্ট': 8,
    'সেপ্টেম্বর': 9, 'অক্টোবর': 10, 'নভেম্বর': 11, 'ডিসেম্বর': 12,
  };

  /**
   * Convert integer to Bengali digits.
   */
  function toBnNum(num) {
    const bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯',];
    return String(num).split('').map((c) => {
      return bn[parseInt(c, 10)] || c;
    }).join('');
  }

  /**
   * Create a Date object from Gregorian year, month, day values.
   * Month is 1-based (1 = January).
   */
  function newDate(y, m, d) {
    return new Date(y, m - 1, d);
  }

  /**
   * Calculate the day count from the start of a given Bengali month
   * to a specific date, handling wrapping across Gregorian year boundary.
   *
   * Returns 1-based day number within the Bengali month.
   */
  function calcBnDay(bnMonthIdx, gYear, gMonth, gDay) {
    const bnMon = BN_MONTHS[bnMonthIdx];
    // Determine which Gregorian year the Bengali month started in
    // If the Bengali month starts in a month > the date's month
    // (or same month but later day), the start was in the previous year
    let startYear = gYear;
    if (bnMon.month > gMonth || (bnMon.month === gMonth && bnMon.day > gDay)) {
      startYear = gYear - 1;
    }
    const startDate = newDate(startYear, bnMon.month, bnMon.day);
    const currentDate = newDate(gYear, gMonth, gDay);
    const diffMs = currentDate.getTime() - startDate.getTime();
    return Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
  }

  /**
   * Determine the Bengali year for a given Gregorian date.
   * Bengali New Year (1 বৈশাখ) starts on 14 April.
   */
  function calcBnYear(gYear, gMonth, gDay) {
    if (gMonth > 4 || (gMonth === 4 && gDay >= 14)) {
      return gYear - 593;
    }
    return gYear - 594;
  }

  /**
   * Convert a Gregorian date string (dd-mm-yyyy) to a Bengali date string.
   *
   * @param {string} dateStr - Date in dd-mm-yyyy format
   * @returns {string} Bengali date like "৭ জ্যৈষ্ঠ ১৪৩৩" or empty string on failure
   */
  function gregorianToBangla(dateStr) {
    if (!dateStr || typeof dateStr !== 'string') return '';

    // Normalize: remove Bengali digits
    const cleaned = dateStr.replace(/[০-৯]/g, (m) => {
      return String.fromCharCode(m.charCodeAt(0) - 0x09E6 + 48);
    });

    // Try numeric format first: dd-mm-yyyy or dd/mm/yyyy
    const parts = cleaned.split(/[-/]/);
    if (parts.length === 3) {
      const day = parseInt(parts[0], 10);
      const month = parseInt(parts[1], 10);
      const year = parseInt(parts[2], 10);

      if (!isNaN(day) && !isNaN(month) && !isNaN(year)) {
        if (day >= 1 && day <= 31 && month >= 1 && month <= 12 && year >= 1000) {
          return buildBengaliDate(day, month, year);
        }
      }
    }

    // Try "dd MonthName, yyyy" or "dd MonthName yyyy" format
    const monthNameMatch = cleaned.match(/^(\d{1,2})\s+([a-zA-Z\u0980-\u09FF]+)[\s,]+(\d{4})$/);
    if (monthNameMatch) {
      const mDay = parseInt(monthNameMatch[1], 10);
      const mName = monthNameMatch[2].toLowerCase().trim();
      const mYear = parseInt(monthNameMatch[3], 10);

      const mMonth = EN_MONTH_MAP[mName] || BN_MONTH_MAP[mName];
      if (mMonth && !isNaN(mDay) && !isNaN(mYear)) {
        if (mDay >= 1 && mDay <= 31 && mYear >= 1000) {
          return buildBengaliDate(mDay, mMonth, mYear);
        }
      }
    }

    return '';
  }

  function buildBengaliDate(day, month, year) {
    for (let i = 0; i < BN_MONTHS.length; i++) {
      const bnMon = BN_MONTHS[i];
      const nextIdx = (i + 1) % BN_MONTHS.length;
      const nextMon = BN_MONTHS[nextIdx];

      const wraps = bnMon.month > nextMon.month;

      let startYear = year;
      if (wraps && (month < bnMon.month || (month === bnMon.month && day < bnMon.day))) {
        startYear = year - 1;
      }
      const bnStart = newDate(startYear, bnMon.month, bnMon.day);

      const endYear = wraps ? year + 1 : year;
      const bnEnd = newDate(endYear, nextMon.month, nextMon.day);

      const current = newDate(year, month, day);

      if (current >= bnStart && current < bnEnd) {
        const bnDay = calcBnDay(i, year, month, day);
        const bnYear = calcBnYear(year, month, day);
        return `${toBnNum(bnDay) } ${ bnMon.name } ${ toBnNum(bnYear)}`;
      }
    }

    return '';
  }

  window.gregorianToBangla = gregorianToBangla;
})();
