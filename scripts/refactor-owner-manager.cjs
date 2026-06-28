const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

// Shared init logic for create.twig — builds initial owner from old single fields + Twig fallback
const CREATE_INIT_CODE = `
  function init() {
    var container = document.getElementById('owners-container');
    if (!container) return;

    // Build initial owner from server-provided data
    var initialOwner = {};
    var nameInput = document.querySelector('[name="owner_name"]');
    if (nameInput && nameInput.value) initialOwner.name = nameInput.value;
    var fatherInput = document.querySelector('[name="father_or_husband_name"]');
    if (fatherInput && fatherInput.value) initialOwner.father_or_husband_name = fatherInput.value;
    var motherInput = document.querySelector('[name="mothers_name"]');
    if (motherInput && motherInput.value) initialOwner.mothers_name = motherInput.value;
    var addressInput = document.querySelector('[name="owner_address"]');
    if (addressInput && addressInput.value) initialOwner.address = addressInput.value;
    var shareInput = document.querySelector('[name="share"]');
    if (shareInput && shareInput.value) initialOwner.share = shareInput.value;
    var nidInput = document.querySelector('[name="nid_number"]');
    if (nidInput && nidInput.value) initialOwner.nid_number = nidInput.value;

    // Remove old single fields
    var oldFields = ['owner_name', 'father_or_husband_name', 'mothers_name', 'owner_address', 'share', 'nid_number'];
    oldFields.forEach(function(f) {
      var el = document.querySelector('[name="' + f + '"]');
      if (el && el.parentNode) el.parentNode.removeChild(el);
    });

    // If no data from old fields, use Twig data
    if (!initialOwner.name) {
      initialOwner.name = '{{ data.owner_name|default("")|e("js") }}';
      initialOwner.father_or_husband_name = '{{ data.father_or_husband_name|default("")|e("js") }}';
      initialOwner.mothers_name = '{{ data.mothers_name|default("")|e("js") }}';
      initialOwner.address = '{{ data.owner_address|default("")|e("js") }}';
      initialOwner.share = '{{ data.share|default("")|e("js") }}';
      initialOwner.nid_number = '{{ data.nid_number|default("")|e("js") }}';
    }

    createOwnerEntry(initialOwner);

    // Add button listener
    var addBtn = document.getElementById('add-owner-btn');
    if (addBtn) addBtn.addEventListener('click', function() { createOwnerEntry(null); });

    // Remove button listener (event delegation)
    container.addEventListener('click', function(e) {
      var removeBtn = e.target.closest('.remove-owner-btn');
      if (!removeBtn) return;
      var entry = removeBtn.closest('.owner-entry');
      if (entry) {
        var count = container.querySelectorAll('.owner-entry').length;
        if (count <= 1) {
          alert('অন্তত একজন মালিক থাকতে হবে।');
          return;
        }
        if (confirm('এই মালিককে সরাতে চান?')) {
          entry.remove();
          reindexOwners();
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      init();
      setTimeout(fillRandomData, 0);
    });
  } else {
    init();
    setTimeout(fillRandomData, 0);
  }
`;

// Shared init logic for edit.twig — checks data-owners first, then old fields
const EDIT_INIT_CODE = `
  function init() {
    var container = document.getElementById('owners-container');
    if (!container) return;

    // Check for pre-populated owners data (from data-owners attribute)
    var ownersData = container.getAttribute('data-owners');
    if (ownersData) {
      try {
        var owners = JSON.parse(ownersData);
        if (Array.isArray(owners) && owners.length > 0) {
          owners.forEach(function(owner) { createOwnerEntry(owner); });
          return;
        }
      } catch(e) {}
    }

    // Fallback: build from old single fields
    var initialOwner = {};
    var nameInput = document.querySelector('[name="owner_name"]');
    if (nameInput && nameInput.value) initialOwner.name = nameInput.value;
    var fatherInput = document.querySelector('[name="father_or_husband_name"]');
    if (fatherInput && fatherInput.value) initialOwner.father_or_husband_name = fatherInput.value;
    var motherInput = document.querySelector('[name="mothers_name"]');
    if (motherInput && motherInput.value) initialOwner.mothers_name = motherInput.value;
    var addressInput = document.querySelector('[name="owner_address"]');
    if (addressInput && addressInput.value) initialOwner.address = addressInput.value;
    var shareInput = document.querySelector('[name="share"]');
    if (shareInput && shareInput.value) initialOwner.share = shareInput.value;
    var nidInput = document.querySelector('[name="nid_number"]');
    if (nidInput && nidInput.value) initialOwner.nid_number = nidInput.value;

    // Remove old single fields
    var oldFields = ['owner_name', 'father_or_husband_name', 'mothers_name', 'owner_address', 'share', 'nid_number'];
    oldFields.forEach(function(f) {
      var el = document.querySelector('[name="' + f + '"]');
      if (el && el.parentNode) el.parentNode.removeChild(el);
    });

    // Fallback to Twig data
    if (!initialOwner.name) {
      initialOwner.name = '{{ data.owner_name|default("")|e("js") }}';
      initialOwner.father_or_husband_name = '{{ data.father_or_husband_name|default("")|e("js") }}';
      initialOwner.mothers_name = '{{ data.mothers_name|default("")|e("js") }}';
      initialOwner.address = '{{ data.owner_address|default("")|e("js") }}';
      initialOwner.share = '{{ data.share|default("")|e("js") }}';
      initialOwner.nid_number = '{{ data.nid_number|default("")|e("js") }}';
    }

    createOwnerEntry(initialOwner);

    // Add button listener
    var addBtn = document.getElementById('add-owner-btn');
    if (addBtn) addBtn.addEventListener('click', function() { createOwnerEntry(null); });

    // Remove button listener (event delegation)
    container.addEventListener('click', function(e) {
      var removeBtn = e.target.closest('.remove-owner-btn');
      if (!removeBtn) return;
      var entry = removeBtn.closest('.owner-entry');
      if (entry) {
        var count = container.querySelectorAll('.owner-entry').length;
        if (count <= 1) {
          alert('অন্তত একজন মালিক থাকতে হবে।');
          return;
        }
        if (confirm('এই মালিককে সরাতে চান?')) {
          entry.remove();
          reindexOwners();
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
`;

// Build the new script blocks
function buildNewScript(initCode) {
  return `<script>
/**
 * Dynamic Kharij Owner Manager
 * Handles add/remove of multiple land owners in the form.
 * Core functions shared via kharij/_owner_manager.twig
 */
(function() {
  'use strict';

  {% include 'kharij/_owner_manager.twig' %}
${initCode}
})();
</script>`;
}

// ─── Process create.twig ───

let createContent = fs.readFileSync(path.join(root, 'app/Views/kharij/create.twig'), 'utf8');

// Find the Owner Manager script block — starts at the validation script's closing </script>
// and runs until the next <script> tag (the Land Calculator)
const createScriptStart = '<script>\n/**\n * Dynamic Kharij Owner Manager\n * Handles add/remove of multiple land owners in the form.\n */\n(function() {\n  \'use strict\';';
const createScriptEnd = '\n</script>\n\n<script>\n/**\n * Kharij Land Calculator';

const cStartIdx = createContent.indexOf(createScriptStart);
const cEndIdx = createContent.indexOf(createScriptEnd);

if (cStartIdx === -1 || cEndIdx === -1) {
  console.error('ERROR: Could not find Owner Manager script block in create.twig');
  console.error('  start found:', cStartIdx !== -1);
  console.error('  end found:', cEndIdx !== -1);
  process.exit(1);
}

const cBefore = createContent.substring(0, cStartIdx);
const cAfter = createContent.substring(cEndIdx);
const cNew = cBefore + buildNewScript(CREATE_INIT_CODE) + cAfter;

fs.writeFileSync(path.join(root, 'app/Views/kharij/create.twig'), cNew, 'utf8');
console.log('✅ create.twig updated');

// ─── Process edit.twig ───

let editContent = fs.readFileSync(path.join(root, 'app/Views/kharij/edit.twig'), 'utf8');

// Find the Owner Manager script block in edit.twig
const editScriptStart = '<script>\n(function() {\n  \'use strict\';\n\n  var OWNER_TEMPLATE';
const editScriptEnd = '\n</script>\n\n<script>\n/**\n * Kharij Land Calculator';

const eStartIdx = editContent.indexOf(editScriptStart);
const eEndIdx = editContent.indexOf(editScriptEnd);

if (eStartIdx === -1 || eEndIdx === -1) {
  console.error('ERROR: Could not find Owner Manager script block in edit.twig');
  console.error('  start found:', eStartIdx !== -1);
  console.error('  end found:', eEndIdx !== -1);
  process.exit(1);
}

const eBefore = editContent.substring(0, eStartIdx);
const eAfter = editContent.substring(eEndIdx);
const eNew = eBefore + buildNewScript(EDIT_INIT_CODE) + eAfter;

fs.writeFileSync(path.join(root, 'app/Views/kharij/edit.twig'), eNew, 'utf8');
console.log('✅ edit.twig updated');

console.log('\nDone. Both files now use the shared kharij/_owner_manager.twig include.');
