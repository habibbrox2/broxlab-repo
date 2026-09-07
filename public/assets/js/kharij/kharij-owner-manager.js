/**
 * kharij-owner-manager.js
 *
 * Dynamic owner entry management for Kharij forms.
 * Handles add/remove/reindex of multiple land owner entries.
 *
 * Depends on: nothing
 *
 * Usage:
 *   If data-owners attribute exists on #owners-container, it will be
 *   parsed as JSON and used to pre-populate owners. Otherwise, the
 *   manager falls back to scanning for flat owner fields (owner_name,
 *   father_or_husband_name, etc.) and then to Twig template data.
 *
 * Add button: #add-owner-btn
 * Remove button: .remove-owner-btn (event delegation on #owners-container)
 */
(function () {
  'use strict';

  const OWNER_TEMPLATE =
    '<div class="owner-entry rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden" data-owner-index="__INDEX__">' +
    '<div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-100">' +
    '<span class="text-sm font-semibold text-slate-700 owner-number-badge">\u09AE\u09BE\u09B2\u09BF\u0995 #__DISPLAY__</span>' +
    '<button type="button" class="remove-owner-btn inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition-all duration-200 hover:bg-red-50 hover:border-red-300 active:scale-[0.97]">' +
    '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
    '\u09B8\u09B0\u09BE\u09A8' +
    '</button>' +
    '</div>' +
    '<div class="p-5">' +
    '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">' +
    '<div class="kharij-field-group">' +
    '<label class="block text-sm font-semibold text-slate-700 mb-1.5">\u09AE\u09BE\u09B2\u09BF\u0995\u09C7\u09B0 \u09A8\u09BE\u09AE <span class="text-rose-500">*</span></label>' +
    '<input type="text" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10" name="owners[__INDEX__][name]" required placeholder="\u099C\u09AE\u09BF\u09B0 \u09AA\u09CD\u09B0\u0995\u09C3\u09A4 \u09AE\u09BE\u09B2\u09BF\u0995\u09C7\u09B0 \u09A8\u09BE\u09AE" data-owner-field="name">' +
    '<span class="field-icon" style="top: 36px;">\uD83D\uDC64</span>' +
    '</div>' +
    '<div class="kharij-field-group">' +
    '<label class="block text-sm font-semibold text-slate-700 mb-1.5">\u09AA\u09BF\u09A4\u09BE/\u09B8\u09CD\u09AC\u09BE\u09AE\u09C0\u09B0 \u09A8\u09BE\u09AE</label>' +
    '<input type="text" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10" name="owners[__INDEX__][father_or_husband_name]" placeholder="\u09AE\u09BE\u09B2\u09BF\u0995\u09C7\u09B0 \u09AA\u09BF\u09A4\u09BE \u09AC\u09BE \u09B8\u09CD\u09AC\u09BE\u09AE\u09C0\u09B0 \u09A8\u09BE\u09AE" data-owner-field="father_or_husband_name">' +
    '<span class="field-icon" style="top: 36px;">\uD83D\uDC68\u200D\uD83D\uDC69\u200D\uD83D\uDC67</span>' +
    '</div>' +
    '<div class="kharij-field-group">' +
    '<label class="block text-sm font-semibold text-slate-700 mb-1.5">\u09AE\u09BE\u09A4\u09BE\u09B0 \u09A8\u09BE\u09AE</label>' +
    '<input type="text" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10" name="owners[__INDEX__][mothers_name]" placeholder="\u09AE\u09BE\u09B2\u09BF\u0995\u09C7\u09B0 \u09AE\u09BE\u09A4\u09BE\u09B0 \u09A8\u09BE\u09AE" data-owner-field="mothers_name">' +
    '<span class="field-icon" style="top: 36px;">\uD83D\uDC69</span>' +
    '</div>' +
    '<div class="kharij-field-group">' +
    '<label class="block text-sm font-semibold text-slate-700 mb-1.5">\u099C\u09BE\u09A4\u09C0\u09AF\u09BC \u09AA\u09B0\u09BF\u099A\u09AF\u09BC\u09AA\u09A4\u09CD\u09B0 \u09A8\u0982</label>' +
    '<input type="text" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10" name="owners[__INDEX__][nid_number]" placeholder="\u098F\u09A8\u0986\u0987\u09A1\u09BF \u09A8\u09AE\u09CD\u09AC\u09B0 (\u0990\u099B\u09BF\u0995\u09CD\u09AF)" data-owner-field="nid_number">' +
    '<span class="field-icon" style="top: 36px;">\uD83E\uDEAA</span>' +
    '</div>' +
    '<div class="kharij-field-group sm:col-span-2">' +
    '<label class="block text-sm font-semibold text-slate-700 mb-1.5">\u09A0\u09BF\u0995\u09BE\u09A8\u09BE <span class="text-rose-500">*</span></label>' +
    '<input type="text" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10" name="owners[__INDEX__][address]" required placeholder="\u09AE\u09BE\u09B2\u09BF\u0995\u09C7\u09B0 \u09B8\u09AE\u09CD\u09AA\u09C2\u09B0\u09CD\u09A3 \u09A0\u09BF\u0995\u09BE\u09A8\u09BE" data-owner-field="address" value="বাসা/হোল্ডিং:, গ্রাম/রাস্তা: , , ডাকঘর:রোয়াইল-1822, উপজেলা: ধামরাই, জেলা: ঢাকা">' +
    '<span class="field-icon" style="top: 36px;">\uD83D\uDCCD</span>' +
    '</div>' +
    '<div class="kharij-field-group">' +
    '<label class="block text-sm font-semibold text-slate-700 mb-1.5">\u0985\u0982\u09B6 <span class="text-rose-500">*</span></label>' +
    '<input type="text" class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition-all duration-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10" name="owners[__INDEX__][share]" required value="1.000" placeholder="\u09AF\u09C7\u09AE\u09A8: \u09E7.\u09E6\u09E6\u09E6 (\u09E7\u09EC \u0986\u09A8\u09BE = \u09AA\u09C2\u09B0\u09CD\u09A3 \u0985\u0982\u09B6)" data-owner-field="share">' +
    '<span class="field-icon" style="top: 36px;">\uD83E\uDDEE</span>' +
    '</div>' +
    '</div>' +
    '</div>' +
    '</div>';

  function updateBadge(count) {
    const badge = document.getElementById('owner-count-badge');
    if (badge) badge.textContent = `${count} \u099C\u09A8 \u09AE\u09BE\u09B2\u09BF\u0995`;
  }

  function reindexOwners() {
    const entries = document.querySelectorAll('#owners-container .owner-entry');
    entries.forEach((entry, idx) => {
      const displayNum = idx + 1;
      entry.dataset.ownerIndex = idx;
      const badge = entry.querySelector('.owner-number-badge');
      if (badge) badge.textContent = `\u09AE\u09BE\u09B2\u09BF\u0995 #${displayNum}`;
      const inputs = entry.querySelectorAll('[name^="owners["]');
      inputs.forEach((input) => {
        const fieldName = input.dataset.ownerField;
        if (fieldName) {
          input.name = `owners[${idx}][${fieldName}]`;
          input.id = `owner_${idx}_${fieldName}`;
        }
      });
    });
    updateBadge(entries.length);
  }

  function createOwnerEntry(data) {
    const container = document.getElementById('owners-container');
    const entryCount = container.querySelectorAll('.owner-entry').length;
    const html = OWNER_TEMPLATE
      .replace(/__INDEX__/g, entryCount)
      .replace(/__DISPLAY__/g, entryCount + 1);

    const temp = document.createElement('div');
    temp.innerHTML = html.trim();
    const entry = temp.firstChild;

    if (data) {
      const fields = ['name', 'father_or_husband_name', 'mothers_name', 'nid_number', 'address', 'share',];
      fields.forEach((f) => {
        if (data[f]) {
          const input = entry.querySelector(`[data-owner-field="${f}"]`);
          if (input) input.value = data[f];
        }
      });
    }

    container.appendChild(entry);
    reindexOwners();
  }

  function initOwnerManager(initialOwnerData) {
    const container = document.getElementById('owners-container');
    if (!container) return;

    // Check for pre-populated owners data (from data-owners attribute)
    const ownersData = container.getAttribute('data-owners');
    if (ownersData) {
      try {
        const owners = JSON.parse(ownersData);
        if (Array.isArray(owners) && owners.length > 0) {
          owners.forEach((owner) => { createOwnerEntry(owner); });
          return;
        }
      } catch (e) { /* fall through */ }
    }

    // Fallback: build from old single fields
    const initialOwner = initialOwnerData || {};
    if (!initialOwner.name) {
      const nameInput = document.querySelector('[name="owner_name"]');
      if (nameInput && nameInput.value) initialOwner.name = nameInput.value;
    }
    if (!initialOwner.father_or_husband_name) {
      const fatherInput = document.querySelector('[name="father_or_husband_name"]');
      if (fatherInput && fatherInput.value) initialOwner.father_or_husband_name = fatherInput.value;
    }
    if (!initialOwner.mothers_name) {
      const motherInput = document.querySelector('[name="mothers_name"]');
      if (motherInput && motherInput.value) initialOwner.mothers_name = motherInput.value;
    }
    if (!initialOwner.address) {
      const addressInput = document.querySelector('[name="owner_address"]');
      if (addressInput && addressInput.value) initialOwner.address = addressInput.value;
    }
    if (!initialOwner.share) {
      const shareInput = document.querySelector('[name="share"]');
      if (shareInput && shareInput.value) initialOwner.share = shareInput.value;
    }
    if (!initialOwner.nid_number) {
      const nidInput = document.querySelector('[name="nid_number"]');
      if (nidInput && nidInput.value) initialOwner.nid_number = nidInput.value;
    }

    // Remove old single fields
    const oldFields = ['owner_name', 'father_or_husband_name', 'mothers_name', 'owner_address', 'share', 'nid_number',];
    oldFields.forEach((f) => {
      const el = document.querySelector(`[name="${f}"]`);
      if (el && el.parentNode) el.parentNode.removeChild(el);
    });

    createOwnerEntry(initialOwner);

    // Add button listener
    const addBtn = document.getElementById('add-owner-btn');
    if (addBtn) addBtn.addEventListener('click', () => { createOwnerEntry(null); });

    // Remove button listener (event delegation)
    container.addEventListener('click', (e) => {
      const removeBtn = e.target.closest('.remove-owner-btn');
      if (!removeBtn) return;
      const entry = removeBtn.closest('.owner-entry');
      if (entry) {
        const count = container.querySelectorAll('.owner-entry').length;
        if (count <= 1) {
          alert('\u0985\u09A8\u09CD\u09A4\u09A4 \u098F\u0995\u099C\u09A8 \u09AE\u09BE\u09B2\u09BF\u0995 \u09A5\u09BE\u0995\u09A4\u09C7 \u09B9\u09AC\u09C7\u0964');
          return;
        }
        if (confirm('\u098F\u0987 \u09AE\u09BE\u09B2\u09BF\u0995\u0995\u09C7 \u09B8\u09B0\u09BE\u09A4\u09C7 \u099A\u09BE\u09A8?')) {
          entry.remove();
          reindexOwners();
        }
      }
    });
  }

  window.initOwnerManager = initOwnerManager;
})();
