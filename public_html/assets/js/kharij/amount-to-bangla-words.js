/**
 * amount-to-bangla-words.js
 *
 * Converts any numeric amount to Bengali words (up to কোটি with পয়সা support).
 * Ported from PHP amountToBanglaWords helper.
 *
 * Usage:
 *   bnNumToWords(337)       → "তিনশত সাঁইত্রিশ টাকা মাত্র"
 *   bnNumToWords(337.50)    → "তিনশত সাঁইত্রিশ টাকা পঞ্চাশ পয়সা মাত্র"
 *   bnNumToWords(0)         → "শূন্য টাকা মাত্র"
 */
(function () {
  'use strict';

  function bnNumToWords(amount) {
    var ones = [
      '', 'এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়', 'সাত', 'আট', 'নয়',
      'দশ', 'এগারো', 'বারো', 'তেরো', 'চৌদ্দ', 'পনেরো', 'ষোল', 'সতেরো', 'আঠারো', 'উনিশ',
      'বিশ', 'একুশ', 'বাইশ', 'তেইশ', 'চব্বিশ', 'পঁচিশ', 'ছাব্বিশ', 'সাতাশ', 'আটাশ', 'ঊনত্রিশ',
      'ত্রিশ', 'একত্রিশ', 'বত্রিশ', 'তেত্রিশ', 'চৌত্রিশ', 'পঁয়ত্রিশ', 'ছত্রিশ', 'সাঁইত্রিশ', 'আটত্রিশ', 'ঊনচল্লিশ',
      'চল্লিশ', 'একচল্লিশ', 'বিয়াল্লিশ', 'তেতাল্লিশ', 'চুয়াল্লিশ', 'পঁয়তাল্লিশ', 'ছেচল্লিশ', 'সাতচল্লিশ', 'আটচল্লিশ', 'ঊনপঞ্চাশ',
      'পঞ্চাশ', 'একান্ন', 'বাহান্ন', 'তিপ্পান্ন', 'চুয়ান্ন', 'পঞ্চান্ন', 'ছাপ্পান্ন', 'সাতান্ন', 'আটান্ন', 'ঊনষাট',
      'ষাট', 'একষট্টি', 'বাষট্টি', 'তেষট্টি', 'চৌষট্টি', 'পঁয়ষট্টি', 'ছেষট্টি', 'সাতষট্টি', 'আটষট্টি', 'ঊনসত্তর',
      'সত্তর', 'একাত্তর', 'বাহাত্তর', 'তিয়াত্তর', 'চুয়াত্তর', 'পঁচাত্তর', 'ছিয়াত্তর', 'সাতাত্তর', 'আটাত্তর', 'ঊনআশি',
      'আশি', 'একাশি', 'বিরাশি', 'তিরাশি', 'চুরাশি', 'পঁচাশি', 'ছিয়াশি', 'সাতাশি', 'আটাশি', 'ঊননব্বই',
      'নব্বই', 'একানব্বই', 'বিরানব্বই', 'তিরানব্বই', 'চুরানব্বই', 'পঁচানব্বই', 'ছিয়ানব্বই', 'সাতানব্বই', 'আটানব্বই', 'নিরানব্বই'
    ];

    function convert(num) {
      if (num < 100) return ones[num];
      if (num < 1000) {
        var t = ones[Math.floor(num / 100)] + 'শত';
        var r = num % 100;
        return r ? t + ' ' + convert(r) : t;
      }
      if (num < 100000) {
        var t = convert(Math.floor(num / 1000)) + ' হাজার';
        var r = num % 1000;
        return r ? t + ' ' + convert(r) : t;
      }
      if (num < 10000000) {
        var t = convert(Math.floor(num / 100000)) + ' লক্ষ';
        var r = num % 100000;
        return r ? t + ' ' + convert(r) : t;
      }
      if (num < 1000000000) {
        var t = convert(Math.floor(num / 10000000)) + ' কোটি';
        var r = num % 10000000;
        return r ? t + ' ' + convert(r) : t;
      }
      return '';
    }

    amount = Math.round(amount * 100) / 100;
    var taka = Math.floor(amount);
    var poisha = Math.round((amount - taka) * 100);

    var result = (convert(taka) || 'শূন্য') + ' টাকা';
    if (poisha > 0) result += ' ' + (convert(poisha) || 'শূন্য') + ' পয়সা';
    return result + ' মাত্র';
  }

  window.bnNumToWords = bnNumToWords;
})();
