import { createCvBuilderRenderers } from './cv-builder-renderers.js';

const STATE = {
  currentStep: 0,
  data: {},
  cvId: null,
  csrf: '',
  selectedTemplate: 'modern',
  profilePhoto: '',
  isSaving: false,
  isUploading: false,
  saveTimer: null,
  _editingSection: null,
  _editingIndex: -1,
};

const STEPS = [
  { id: 'personal', title: 'Personal Information', icon: 'user', desc: 'Tell us about yourself', },
  { id: 'professional', title: 'Professional Details', icon: 'briefcase', desc: 'Experience, education, skills & languages', },
  { id: 'extras', title: 'Social, Sections & References', icon: 'share-2', desc: 'Social links, custom sections & references', },
];

const { renderStepContent, renderSkillTags, } = createCvBuilderRenderers({ STATE, STEPS, escHtml, });

function renderStep(index) {
  const content = renderStepContent(index);
  const el = document.getElementById('bld-step-content');
  if (el) {
    el.innerHTML = content;
    el.style.animation = 'none';
    el.offsetHeight;
    el.style.animation = '';
  }
  updateProgress();
  const prevBtn = document.getElementById('bld-btn-prev');
  const nextBtn = document.getElementById('bld-btn-next');
  if (prevBtn) prevBtn.style.display = index === 0 ? 'none' : '';
  if (nextBtn) {
    nextBtn.innerHTML = index >= STEPS.length - 1
      ? '<i class="lucide lucide-check" style="width:1em;height:1em;"></i> Finish'
      : 'Next <i class="lucide lucide-chevron-right" style="width:1em;height:1em;"></i>';
  }
}

function collectStepData(stepId) {
  switch (stepId) {
  case 'personal':
    return { full_name: val('bld-field-full_name'), job_title: val('bld-field-job_title'), email: val('bld-field-email'), phone: val('bld-field-phone'), date_of_birth: val('bld-field-dob'), nationality: val('bld-field-nationality'), gender: val('bld-field-gender'), address: val('bld-field-address'), website: val('bld-field-website'), linkedin: val('bld-field-linkedin'), github: val('bld-field-github'), twitter: val('bld-field-twitter'), national_id_no: val('bld-field-national_id_no'), passport_no: val('bld-field-passport_no'), birth_certificate_no: val('bld-field-birth_certificate_no'), religion: val('bld-field-religion'), };
  case 'professional': {
    // Collect experience
    const exps = [];
    const expCards = document.querySelectorAll('#bld-experience-list .bld-entry-card');
    for (let ei = 0; ei < expCards.length; ei++) {
      exps.push({ company: qval(expCards[ei], '.exp-company'), position: qval(expCards[ei], '.exp-position'), location: qval(expCards[ei], '.exp-location'), start_date: qval(expCards[ei], '.exp-start_date'), end_date: qval(expCards[ei], '.exp-end_date'), is_current: qchecked(expCards[ei], '.exp-current'), responsibilities: qval(expCards[ei], '.exp-responsibilities'), });
    }
    STATE.data.experience = exps;
    // Collect education
    const eds = [];
    const eduCards = document.querySelectorAll('#bld-education-list .bld-entry-card');
    for (let edi = 0; edi < eduCards.length; edi++) {
      eds.push({ institution: qval(eduCards[edi], '.edu-institution'), degree: qval(eduCards[edi], '.edu-degree'), field: qval(eduCards[edi], '.edu-field'), year: qval(eduCards[edi], '.edu-year'), });
    }
    STATE.data.education = eds;
    // Collect skills
    STATE.data.skills = { technical: collectSkills('technical'), soft: collectSkills('soft'), };
    // Collect languages
    const langs = [];
    const langCards = document.querySelectorAll('#bld-languages-list .bld-entry-card');
    for (let li = 0; li < langCards.length; li++) {
      langs.push({ name: qval(langCards[li], '.lang-name'), proficiency: qval(langCards[li], '.lang-proficiency'), });
    }
    STATE.data.languages = langs;
    return { _combined: true, };
  }
  case 'extras': {
    // Collect all three sub-sections at once
    const links = [];
    const linkCards = document.querySelectorAll('#bld-social-links-list .bld-entry-card');
    for (let li = 0; li < linkCards.length; li++) {
      links.push({ platform: qval(linkCards[li], '.link-platform'), url: qval(linkCards[li], '.link-url'), });
    }
    STATE.data.social_links = links;
    const secs = [];
    const secCards = document.querySelectorAll('#bld-custom-sections-list .bld-entry-card');
    for (let si = 0; si < secCards.length; si++) {
      secs.push({ title: qval(secCards[si], '.custom-title'), content: qval(secCards[si], '.custom-content'), });
    }
    STATE.data.custom_sections = secs;
    const refs = [];
    const refCards = document.querySelectorAll('#bld-references-list .bld-entry-card');
    for (let ri = 0; ri < refCards.length; ri++) {
      refs.push({ name: qval(refCards[ri], '.ref-name'), title: qval(refCards[ri], '.ref-title'), email: qval(refCards[ri], '.ref-email'), phone: qval(refCards[ri], '.ref-phone'), });
    }
    STATE.data.references = refs;
    return { _combined: true, };
  }
  }
  return {};
}

window.bldNextStep = function () {
  const stepId = STEPS[STATE.currentStep].id;
  if (stepId !== 'review') {
    STATE.data[stepId] = collectStepData(stepId);
  }
  if (STATE.currentStep >= STEPS.length - 1) { completeBuilder(); return; }
  STATE.currentStep++;
  renderStep(STATE.currentStep);
};

window.bldPrevStep = function () {
  if (STATE.currentStep <= 0) return;
  const stepId = STEPS[STATE.currentStep].id;
  if (stepId !== 'review') {
    STATE.data[stepId] = collectStepData(stepId);
  }
  STATE.currentStep--;
  renderStep(STATE.currentStep);
};

window.bldSkipStep = function () {
  if (STATE.currentStep >= STEPS.length - 1) return;
  STATE.currentStep++;
  renderStep(STATE.currentStep);
};

window.bldSaveDraft = function () {
  const stepId = STEPS[STATE.currentStep].id;
  if (stepId !== 'review') {
    STATE.data[stepId] = collectStepData(stepId);
  }
  saveBuilderData();
};

function setupAutoSave() {
  document.addEventListener('input', () => {
    if (STATE.saveTimer) clearTimeout(STATE.saveTimer);
    STATE.saveTimer = setTimeout(() => {
      const stepId = STEPS[STATE.currentStep].id;
      if (stepId !== 'review') {
        STATE.data[stepId] = collectStepData(stepId);
      }
      saveBuilderData(true);
    }, 1000);
  });
}

function refreshReviewPreviewIfOpen() {
  const iframe = document.getElementById('bld-preview-iframe');
  if (!iframe || !iframe.src) return;
  const currentSrc = iframe.src;
  // Only refresh if the review preview iframe is open (not the old modal one)
  if (currentSrc && currentSrc.includes('/api/cv/')) {
    const base = currentSrc.replace(/[?&]_t=\d+/g, '');
    const sep = base.includes('?') ? '&' : '?';
    iframe.src = `${base}${sep}_t=${Date.now()}`;
  }
}

function showAutoSaveIndicator() {
  const el = document.getElementById('bld-save-indicator');
  if (!el) return;
  el.classList.add('show');
  setTimeout(() => { el.classList.remove('show'); }, 2000);
}

function saveBuilderData(silent) {
  if (STATE.isSaving) return;
  STATE.isSaving = true;
  const stepId = STEPS[STATE.currentStep].id;
  const stepData = STATE.data[stepId] || {};
  const payload = { step: stepId, data: stepData, all_data: STATE.data, };
  // First save to the standard builder_data JSON
  fetch(`/ api / cv / builder / ${ STATE.cvId }/step`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf, },
    body: JSON.stringify(payload),
  })
    .then((r) => { return r.json(); })
    .then((res) => {
      if (!res.success) return;
      // Refresh preview if open after successful save
      refreshReviewPreviewIfOpen();
      // If on personal step, also save to the structured cv_personal_info table
      if (stepId === 'personal') {
        savePersonalInfo(stepData, silent);
      } else if (!silent) {
        showAutoSaveIndicator();
      }
    })
    .catch(() => { })
    .finally(() => { STATE.isSaving = false; });
}

function savePersonalInfo(data, silent) {
  fetch(`/api/cv/${ STATE.cvId }/personal-info`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf, },
    body: JSON.stringify(data),
  })
    .then((r) => { return r.json(); })
    .then((res) => {
      if (res.success && !silent) showAutoSaveIndicator();
    })
    .catch(() => { });
}

function completeBuilder() {
  if (STATE.isSaving) return;
  STATE.isSaving = true;
  // Collect all remaining step data before finalizing
  for (let si = 0; si < STEPS.length; si++) {
    const sid = STEPS[si].id;
    if (sid !== 'review' && !STATE.data[sid]) {
      if (sid === 'professional') {
        STATE.data.experience = STATE.data.experience || [];
        STATE.data.education = STATE.data.education || [];
        STATE.data.skills = STATE.data.skills || { technical: [], soft: [], };
        STATE.data.languages = STATE.data.languages || [];
        STATE.data.professional = { _combined: true, };
      } else {
        STATE.data[sid] = [];
      }
    }
  }
  STATE.data._template = STATE.selectedTemplate;
  // Save personal info to the structured table before completing
  if (STATE.data.personal) {
    savePersonalInfo(STATE.data.personal, true);
  }
  const nextBtn = document.getElementById('bld-btn-next');
  if (nextBtn) { nextBtn.disabled = true; nextBtn.innerHTML = '<i class="lucide lucide-hourglass" style="width:1em;height:1em;"></i> Finalizing...'; }
  fetch(`/api/cv/builder/${STATE.cvId}/complete`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf, },
    body: JSON.stringify({ all_data: STATE.data, template: STATE.selectedTemplate, }),
  })
    .then((r) => { return r.json(); })
    .then((res) => {
      if (res.success) { window.location.href = res.redirect || `/cv/${STATE.cvId}`; }
      else { window.showMessage(res.error || 'Failed to complete', 'danger'); if (nextBtn) { nextBtn.disabled = false; nextBtn.innerHTML = 'Finish and Save'; } }
    })
    .catch(() => { window.showMessage('An error occurred', 'danger'); if (nextBtn) { nextBtn.disabled = false; nextBtn.innerHTML = 'Finish and Save'; } })
    .finally(() => { STATE.isSaving = false; });
}

window.bldSelectJob = function (slug) {
  if (!slug) return;
  // Store the job title selection for reference
  STATE.data._selectedJob = slug;
  if (STEPS[STATE.currentStep].id === 'personal') {
    renderStep(STATE.currentStep);
  }
};

window.bldEditEntry = function (section, idx) {
  // Save any current edit before switching
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  STATE._editingSection = section;
  STATE._editingIndex = idx;
  renderStep(STATE.currentStep);
};

window.bldDoneEditing = function () {
  if (STATE._editingSection && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  STATE._editingSection = null;
  STATE._editingIndex = -1;
  renderStep(STATE.currentStep);
};

window.bldAddExperience = function () {
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  if (!Array.isArray(STATE.data.experience)) STATE.data.experience = [];
  STATE.data.experience.push({});
  STATE._editingSection = 'experience';
  STATE._editingIndex = STATE.data.experience.length - 1;
  renderStep(STATE.currentStep);
};
window.bldAddEducation = function () {
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  if (!Array.isArray(STATE.data.education)) STATE.data.education = [];
  STATE.data.education.push({});
  STATE._editingSection = 'education';
  STATE._editingIndex = STATE.data.education.length - 1;
  renderStep(STATE.currentStep);
};
window.bldAddLanguage = function () {
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  if (!Array.isArray(STATE.data.languages)) STATE.data.languages = [];
  STATE.data.languages.push({});
  STATE._editingSection = 'languages';
  STATE._editingIndex = STATE.data.languages.length - 1;
  renderStep(STATE.currentStep);
};
window.bldAddSocialLink = function () {
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  if (!Array.isArray(STATE.data.social_links)) STATE.data.social_links = [];
  STATE.data.social_links.push({});
  STATE._editingSection = 'social_links';
  STATE._editingIndex = STATE.data.social_links.length - 1;
  renderStep(STATE.currentStep);
};
window.bldAddCustomSection = function () {
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  if (!Array.isArray(STATE.data.custom_sections)) STATE.data.custom_sections = [];
  STATE.data.custom_sections.push({});
  STATE._editingSection = 'custom_sections';
  STATE._editingIndex = STATE.data.custom_sections.length - 1;
  renderStep(STATE.currentStep);
};
window.bldAddReference = function () {
  if (STATE._editingSection !== null && STATE._editingIndex >= 0) {
    collectStepData(STEPS[STATE.currentStep].id);
  }
  if (!Array.isArray(STATE.data.references)) STATE.data.references = [];
  STATE.data.references.push({});
  STATE._editingSection = 'references';
  STATE._editingIndex = STATE.data.references.length - 1;
  renderStep(STATE.currentStep);
};

window.bldRemoveEntry = function (step, idx) {
  const entries = STATE.data[step] || [];
  if (!Array.isArray(entries) || idx < 0 || idx >= entries.length) return;
  entries.splice(idx, 1);
  // Fix editing state after removal — adjust if the removed entry shifted indices
  if (STATE._editingSection === step) {
    if (STATE._editingIndex === idx) {
      STATE._editingSection = null;
      STATE._editingIndex = -1;
    } else if (idx < STATE._editingIndex) {
      STATE._editingIndex--;
    }
  }
  renderStep(STATE.currentStep);
};

window.bldMoveEntry = function (step, idx, dir) {
  // Save current form state from the active step first
  const currentStepId = STEPS[STATE.currentStep].id;
  if (currentStepId !== 'review') {
    collectStepData(currentStepId);
  }
  const entries = STATE.data[step] || [];
  if (!Array.isArray(entries) || entries.length < 2) return;
  const targetIdx = dir === 'up' ? idx - 1 : idx + 1;
  if (targetIdx < 0 || targetIdx >= entries.length) return;
  // Swap the items
  const temp = entries[idx];
  entries[idx] = entries[targetIdx];
  entries[targetIdx] = temp;
  // Fix editing index after swap
  if (STATE._editingSection === step) {
    if (STATE._editingIndex === idx) STATE._editingIndex = targetIdx;
    else if (STATE._editingIndex === targetIdx) STATE._editingIndex = idx;
  }
  window.showMessage(`Item moved ${ dir}`, 'success');
  renderStep(STATE.currentStep);
};

window.bldAddSkill = function (e) {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const input = e.target;
  if (!input || !input.id) return;
  const category = input.id.replace('bld-skill-input-', '');
  const text = input.value.trim();
  if (!text) return;
  const current = collectSkills(category);
  current.push(text);
  STATE.data.skills = STATE.data.skills || {};
  STATE.data.skills[category] = current;
  const area = document.getElementById(`bld-skills-${category}`);
  if (area) area.innerHTML = renderSkillTags(current, category);
  input.value = '';
  input.focus();
};

window.bldRemoveSkill = function (category, idx) {
  const current = collectSkills(category);
  current.splice(idx, 1);
  STATE.data.skills = STATE.data.skills || {};
  STATE.data.skills[category] = current;
  const area = document.getElementById(`bld-skills-${category}`);
  if (area) area.innerHTML = renderSkillTags(current, category);
};

window.bldEditSkill = function (category, idx) {
  const current = STATE.data.skills && Array.isArray(STATE.data.skills[category]) ? STATE.data.skills[category].slice() : [];
  const tags = document.querySelectorAll(`#bld-skills-${ category } .bld-skill-tag`);
  if (idx >= tags.length) return;
  const tag = tags[idx];
  const oldText = current[idx] || '';
  const input = document.createElement('input');
  input.className = 'bld-skill-input';
  input.value = oldText;
  input.style.display = 'inline-flex';
  input.style.width = `${Math.max(oldText.length * 8 + 40, 80) }px`;
  tag.replaceWith(input);
  input.focus();
  input.select();
  function finalizeEdit() {
    const newText = input.value.trim();
    if (newText && newText !== oldText) {
      current[idx] = newText;
      STATE.data.skills = STATE.data.skills || {};
      STATE.data.skills[category] = current;
    }
    const area = document.getElementById(`bld-skills-${ category}`);
    if (area) area.innerHTML = renderSkillTags(current, category);
  }
  input.addEventListener('blur', finalizeEdit);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
    if (e.key === 'Escape') {
      const area = document.getElementById(`bld-skills-${ category}`);
      if (area) area.innerHTML = renderSkillTags(current, category);
    }
  });
};


function collectSkills(category) {
  if (STATE.data.skills && Array.isArray(STATE.data.skills[category])) {
    return STATE.data.skills[category].slice();
  }
  const tags = document.querySelectorAll(`#bld-skills-${category} .bld-skill-tag`);
  const arr = [];
  for (let i = 0; i < tags.length; i++) {
    const text = tags[i].textContent.replace(/\s*\u00d7\s*/, '').replace(/\s*×\s*/, '').trim();
    if (text) arr.push(text);
  }
  return arr;
}

window.bldSelectTemplate = function (tmpl) {
  STATE.selectedTemplate = tmpl;
  const pills = document.querySelectorAll('.bld-template-pill');
  for (let i = 0; i < pills.length; i++) {
    pills[i].classList.toggle('selected', pills[i].textContent.trim().toLowerCase() === tmpl);
  }
  // Reload preview if on review step
  if (STEPS[STATE.currentStep] && STEPS[STATE.currentStep].id === 'review') {
    loadPreviewIframe();
  }
};

function loadPreviewIframe() {
  const loading = document.getElementById('bld-preview-loading');
  const iframe = document.getElementById('bld-preview-iframe');
  if (!loading || !iframe) return;
  loading.style.display = 'flex';
  iframe.style.display = 'none';
  iframe.src = `/api/cv/${ STATE.cvId }/preview?template=${ encodeURIComponent(STATE.selectedTemplate) }&t=${ Date.now()}`;
  iframe.onload = function () {
    loading.style.display = 'none';
    iframe.style.display = 'block';
  };
}

// Drag-and-drop photo upload support
document.addEventListener('dragenter', (e) => {
  const area = document.getElementById('bld-photo-upload-area');
  if (!area || !area.contains(e.target)) return;
  e.preventDefault();
  area.classList.add('dragover');
});
document.addEventListener('dragleave', (e) => {
  const area = document.getElementById('bld-photo-upload-area');
  if (!area || area.contains(e.relatedTarget)) return;
  e.preventDefault();
  area.classList.remove('dragover');
});
document.addEventListener('dragover', (e) => {
  const area = document.getElementById('bld-photo-upload-area');
  if (!area || !area.contains(e.target)) return;
  e.preventDefault();
});
document.addEventListener('drop', (e) => {
  const area = document.getElementById('bld-photo-upload-area');
  if (!area || !area.contains(e.target)) return;
  e.preventDefault();
  area.classList.remove('dragover');
  const files = e.dataTransfer.files;
  if (files && files.length > 0) {
    const input = document.getElementById('bld-photo-input');
    if (input) {
      input.files = files;
      window.bldUploadPhoto(input);
    }
  }
});

window.bldUploadPhoto = function (input) {
  if (!input || !input.files || !input.files[0]) return;
  const file = input.files[0];
  const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif',];
  const maxSize = 5 * 1024 * 1024;
  if (allowed.indexOf(file.type) === -1) {
    window.showMessage('Only JPG, PNG, WebP, and GIF images are allowed.', 'danger');
    input.value = '';
    return;
  }
  if (file.size > maxSize) {
    window.showMessage('File size must be under 5MB.', 'danger');
    input.value = '';
    return;
  }
  const progressBar = document.getElementById('bld-photo-progress');
  const progressFill = document.getElementById('bld-photo-progress-bar');
  if (progressBar) progressBar.classList.add('visible');
  if (STATE.isUploading) return;
  STATE.isUploading = true;
  const fd = new FormData();
  fd.append('photo', file);
  const xhr = new XMLHttpRequest();
  xhr.open('POST', `/api/cv/${ STATE.cvId }/photo`, true);
  xhr.setRequestHeader('X-CSRF-Token', STATE.csrf);
  xhr.upload.onprogress = function (e) {
    if (e.lengthComputable && progressFill) {
      progressFill.style.width = `${e.loaded / e.total * 100 }%`;
    }
  };
  xhr.onload = function () {
    STATE.isUploading = false;
    if (progressBar) progressBar.classList.remove('visible');
    if (progressFill) progressFill.style.width = '0%';
    try {
      const res = JSON.parse(xhr.responseText);
      if (res.success && res.photo_url) {
        STATE.profilePhoto = res.photo_url;
        STATE.data.personal = collectStepData('personal');
        window.showMessage('Photo uploaded successfully!', 'success');
        renderStep(STATE.currentStep);
      } else {
        window.showMessage(res.error || 'Upload failed.', 'danger');
      }
    } catch (e) {
      window.showMessage('Upload failed. Please try again.', 'danger');
    }
    input.value = '';
  };
  xhr.onerror = function () {
    STATE.isUploading = false;
    if (progressBar) progressBar.classList.remove('visible');
    if (progressFill) progressFill.style.width = '0%';
    window.showMessage('Upload failed. Please try again.', 'danger');
    input.value = '';
  };
  xhr.send(fd);
};

window.bldRemovePhoto = function () {
  if (STATE.isUploading) return;
  if (!confirm('Remove this profile photo?')) return;
  STATE.isUploading = true;
  fetch(`/api/cv/${ STATE.cvId }/photo`, {
    method: 'DELETE',
    headers: { 'X-CSRF-Token': STATE.csrf, },
  })
    .then((r) => { return r.json(); })
    .then((res) => {
      if (res.success) {
        STATE.profilePhoto = '';
        STATE.data.personal = collectStepData('personal');
        window.showMessage('Photo removed.', 'success');
        renderStep(STATE.currentStep);
      } else {
        window.showMessage(res.error || 'Failed to remove photo.', 'danger');
      }
    })
    .catch(() => {
      window.showMessage('Failed to remove photo.', 'danger');
    })
    .finally(() => { STATE.isUploading = false; });
};

function updateProgress() {
  const pct = Math.round((STATE.currentStep / (STEPS.length - 1)) * 100);
  const pctEl = document.getElementById('bld-progress-pct');
  const fillEl = document.getElementById('bld-progress-fill');
  const stepEl = document.getElementById('bld-current-step');
  const dots = document.querySelectorAll('#bld-step-dots .bld-step-dot');
  if (pctEl) pctEl.textContent = String(pct);
  if (fillEl) fillEl.style.width = `${pct}%`;
  if (stepEl) stepEl.textContent = String(STATE.currentStep + 1);
  const stepNumEl = document.getElementById('bld-current-step-num');
  if (stepNumEl) stepNumEl.textContent = String(STATE.currentStep + 1);
  const totalEl = document.getElementById('bld-total-steps');
  if (totalEl) totalEl.textContent = String(STEPS.length);
  dots.forEach((dot) => {
    const step = parseInt(dot.getAttribute('data-step'), 10);
    dot.classList.remove('active', 'completed');
    if (step === STATE.currentStep + 1) dot.classList.add('active');
    else if (step < STATE.currentStep + 1) dot.classList.add('completed');
  });
  scrollStepIntoView();
}

function scrollStepIntoView() {
  const indicators = document.getElementById('bld-step-dots');
  if (!indicators) return;
  const activeDot = indicators.querySelector('.bld-step-dot.active');
  if (!activeDot) return;
  if (window.innerWidth > 480) return;
  activeDot.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center', });
}

function val(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }
function qval(parent, selector) { const el = parent.querySelector(selector); return el ? el.value.trim() : ''; }
function qchecked(parent, selector) { const el = parent.querySelector(selector); return el ? el.checked : false; }

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
  // Shift+Enter / Alt+Enter — Advance to next step
  if (!e.target || !e.target.closest('.bld-card')) return;
  const isAdvanceKey = (e.shiftKey || e.altKey) && e.key === 'Enter';
  if (!isAdvanceKey) return;
  const textarea = e.target.closest('textarea');
  if (textarea) { e.preventDefault(); window.bldNextStep(); return; }
  if (e.target.matches('input:not([type="checkbox"]):not([type="radio"])')) { e.preventDefault(); window.bldNextStep(); }
});

function setupEventDelegation() {
  // Click delegation via shared utility
  delegateClick(document, {
    'prev-step': () => window.bldPrevStep(),
    'next-step': () => window.bldNextStep(),
    'skip-step': () => window.bldSkipStep(),
    'save-draft': () => window.bldSaveDraft(),
    'done-editing': () => window.bldDoneEditing(),
    'add-experience': () => window.bldAddExperience(),
    'add-education': () => window.bldAddEducation(),
    'add-language': () => window.bldAddLanguage(),
    'add-social-link': () => window.bldAddSocialLink(),
    'add-custom-section': () => window.bldAddCustomSection(),
    'add-reference': () => window.bldAddReference(),
    'remove-photo': () => window.bldRemovePhoto(),
    'trigger-photo-input': () => document.getElementById('bld-photo-input')?.click(),
    'select-template': (e, t) => window.bldSelectTemplate(t.dataset.template),
    'move-entry': (e, t) => window.bldMoveEntry(t.dataset.section, parseInt(t.dataset.idx, 10), t.dataset.dir),
    'remove-entry': (e, t) => window.bldRemoveEntry(t.dataset.section, parseInt(t.dataset.idx, 10)),
    'edit-entry': (e, t) => window.bldEditEntry(t.dataset.section, parseInt(t.dataset.idx, 10)),
    'edit-skill': (e, t) => window.bldEditSkill(t.dataset.category, parseInt(t.dataset.idx, 10)),
    'remove-skill': (e, t) => { e.stopPropagation(); window.bldRemoveSkill(t.dataset.category, parseInt(t.dataset.idx, 10)); },
  });

  // Keydown delegation for skill inputs (scoped to step content)
  const stepContent = document.getElementById('bld-step-content');
  if (stepContent) {
    delegateKeydown(stepContent, {
      'add-skill': (e) => window.bldAddSkill(e),
    });
  }

  // Change delegation via shared utility
  delegateChange(document, {
    'upload-photo': (e, t) => window.bldUploadPhoto(t),
    'select-job': (e, t) => window.bldSelectJob(t.value),
  });
}

function init() {
  STATE.cvId = window.__bldCvId;
  STATE.csrf = window.__bldCsrf || '';
  STATE.data = window.__bldData || {};
  STATE.selectedTemplate = window.__bldSelectedTemplate || 'modern';
  STATE.profilePhoto = window.__bldProfilePhoto || '';
  setupEventDelegation();
  renderStep(0);
  setupAutoSave();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
