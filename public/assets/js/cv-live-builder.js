/**
 * cv-live-builder.js — Live CV Builder
 *
 * Two-column form + live preview interface.
 * - Form inputs auto-update the preview
 * - Preview rendered server-side via iframe
 * - Auto-save to builder_data on changes
 * - Template switching, zoom, PDF export
 */

/* global window, document, fetch, setTimeout, clearTimeout */

(function () {
  'use strict';

  // ── State ──
  const STATE = {
    cvId: window.__lbCvId || null,
    csrf: window.__lbCsrf || '',
    templates: Array.isArray(window.__lbTemplates) ? window.__lbTemplates : [],
    selectedTemplate: window.__lbSelectedTemplate || 'modern-blue',
    data: window.__lbData || {},
    previewUrl: window.__lbPreviewUrl || '/api/cv/live-preview',
    pdfUrl: window.__lbPdfUrl || '',
    guestMode: window.__lbGuestMode === true,
    zoom: 1.0,
    isDirty: false,
    isSaving: false,
    saveTimer: null,
    previewTimer: null,
    experienceCounter: 0,
    educationCounter: 0,
    projectCounter: 0,
    certCounter: 0,
    _pendingPreview: null,
    autoFitScale: 1.0,
  };

  // ── LocalStorage key ──
  // Scoped by cvId so different CVs don't collide
  const STORAGE_KEY = 'cv_live_builder_' + (STATE.cvId || 'guest') + '_data';

  const inputClass = 'w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50 text-slate-700 caret-indigo-500 transition-all duration-150 hover:border-indigo-300 hover:bg-white focus:outline-none focus:border-indigo-500 focus:bg-white focus:shadow-[0_0_0_3px_rgba(99,102,241,0.1)] placeholder:text-slate-400 font-sans';
  const labelClass = 'block text-[0.65rem] font-semibold text-slate-500 mb-1 tracking-tight uppercase';

  // ── Layout Type Map for thumbnail mockups ──
  const LAYOUT_TYPES = {
    // ── Sidebar Left ──
    'modern-blue': 'sidebar-left',
    'corporate-green': 'sidebar-left',
    'nordic': 'sidebar-left',
    'premium-black': 'sidebar-left',
    'startup': 'sidebar-left',
    'glassmorphism': 'sidebar-left',
    'engineer': 'sidebar-left',
    'bold-gradient': 'sidebar-left',
    'creative-purple': 'sidebar-left',
    'executive': 'sidebar-left',
    'double-column': 'sidebar-left',
    'timeline-modern': 'sidebar-left',
    'material-design': 'sidebar-left',
    'bold-contrast': 'sidebar-left',
    'gradient-modern': 'sidebar-left',
    'magazine': 'sidebar-left',
    'swiss-design': 'sidebar-left',
    'creative-artist': 'sidebar-left',
    'education-teacher': 'sidebar-left',
    'lawyer': 'sidebar-left',
    'teacher': 'sidebar-left',
    'developer': 'sidebar-left',
    'doctor': 'sidebar-left',
    'engineering': 'sidebar-left',
    'developer-portfolio': 'sidebar-left',
    'construction': 'sidebar-left',
    'customer-support': 'sidebar-left',
    'electrician': 'sidebar-left',
    'plumber': 'sidebar-left',
    'sales-marketing': 'sidebar-left',
    'finance-banking': 'sidebar-left',
    'healthcare': 'sidebar-left',
    'hospitality': 'sidebar-left',
    'legal-professional': 'sidebar-left',

    // ── Sidebar Right ──
    'dark-professional': 'sidebar-right',
    'sidebar-right': 'sidebar-right',

    // ── Single Column (No Sidebar) ──
    'apple-style': 'single-column',
    'minimal-white': 'single-column',
    'luxury': 'single-column',
    'elegant-gold': 'single-column',
    'japanese-minimal': 'single-column',
    'ats-friendly': 'single-column',
    'magazine-layout': 'single-column',
    'card-based': 'single-column',
    'two-timeline': 'single-column',
    'infographic': 'single-column',

    // ── Banner Header ──
    'swiss-style': 'banner-header',
  };

  function getLayoutType(slug) {
    return LAYOUT_TYPES[slug] || 'sidebar-left';
  }

  function getLayoutIcon(layout) {
    if (layout === 'sidebar-right') {
      return '<svg class="lb-layout-svg" viewBox="0 0 40 28" fill="none"><rect x="0.5" y="0.5" width="27" height="27" rx="1.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="1"/><rect x="30.5" y="0.5" width="9" height="27" rx="1.5" fill="currentColor" fill-opacity="0.25" stroke="currentColor" stroke-opacity="0.3" stroke-width="1"/></svg>';
    }
    if (layout === 'single-column') {
      return '<svg class="lb-layout-svg" viewBox="0 0 40 28" fill="none"><rect x="3" y="0.5" width="34" height="27" rx="1.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="1"/><rect x="8" y="3" width="24" height="3" rx="1" fill="currentColor" fill-opacity="0.15"/><rect x="8" y="9" width="24" height="2" rx="1" fill="currentColor" fill-opacity="0.1"/><rect x="8" y="14" width="20" height="2" rx="1" fill="currentColor" fill-opacity="0.1"/></svg>';
    }
    if (layout === 'banner-header') {
      return '<svg class="lb-layout-svg" viewBox="0 0 40 28" fill="none"><rect x="0.5" y="0.5" width="39" height="27" rx="1.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="1"/><rect x="0.5" y="0.5" width="39" height="7" rx="1.5" fill="currentColor" fill-opacity="0.15"/><rect x="4" y="4" width="12" height="2" rx="1" fill="currentColor" fill-opacity="0.2"/><rect x="26" y="4" width="10" height="2" rx="1" fill="currentColor" fill-opacity="0.15"/><rect x="4" y="11" width="32" height="1.5" rx="0.5" fill="currentColor" fill-opacity="0.08"/><rect x="4" y="15" width="32" height="1.5" rx="0.5" fill="currentColor" fill-opacity="0.08"/></svg>';
    }
    // Default: sidebar-left
    return '<svg class="lb-layout-svg" viewBox="0 0 40 28" fill="none"><rect x="0.5" y="0.5" width="9" height="27" rx="1.5" fill="currentColor" fill-opacity="0.25" stroke="currentColor" stroke-opacity="0.3" stroke-width="1"/><rect x="12.5" y="0.5" width="27" height="27" rx="1.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="1"/></svg>';
  }

  // ── DOM refs ──
  const $ = (sel) => document.querySelector(sel);

  const el = {
    form: $('#lb-form'),
    previewFrame: $('#lb-preview-frame'),
    previewLoading: $('#lb-preview-loading'),
    previewCanvas: $('#lb-preview-canvas'),
    zoomLabel: $('#lb-zoom-label'),
    saveIndicator: $('#lb-save-indicator'),

    experienceList: $('#lb-experience-list'),
    educationList: $('#lb-education-list'),
    projectsList: $('#lb-projects-list'),
    certsList: $('#lb-certificates-list'),

    technicalSkills: $('#lb-technical-skills'),
    softSkills: $('#lb-soft-skills'),
    technicalInput: $('#lb-technical-input'),
    softInput: $('#lb-soft-input'),

    languagesList: $('#lb-languages-list'),
    languageInput: $('#lb-language-input'),
    proficiencySelect: $('#lb-proficiency-select'),
    addLanguageBtn: $('#lb-add-language-btn'),
  };

  // ── Form Data Collector ──
  function collectFormData() {
    const data = {};

    // Personal info (text inputs with data-bld attribute)
    const inputs = el.form.querySelectorAll('[data-bld]');
    inputs.forEach((inp) => {
      const key = inp.getAttribute('data-bld');
      if (key && inp.type !== 'checkbox') {
        data[key] = inp.value.trim();
      }
    });

    // Experience
    data.experience = collectEntries('experience', [
      'company', 'position', 'location', 'start_date', 'end_date', 'description',
    ]);

    // Education
    data.education = collectEntries('education', [
      'institution', 'degree', 'field', 'start_date', 'end_date', 'gpa',
    ]);

    // Projects
    data.projects = collectEntries('project', [
      'name', 'description', 'technologies', 'url',
    ]);

    // Certificates
    data.certificates = collectEntries('certificate', [
      'name', 'organization', 'date', 'credential_url',
    ]);

    // Skills
    data.technical_skills = collectTags(el.technicalSkills);
    data.soft_skills = collectTags(el.softSkills);

    // Languages
    data.languages = [];
    el.languagesList.querySelectorAll('.lb-tag').forEach((tag) => {
      const text = tag.textContent.replace('×', '').trim();
      if (text) data.languages.push(text);
    });

    return data;
  }

  function collectEntries(prefix, fields) {
    const containers = document.querySelectorAll(`[data-entry-type="${prefix}"]`);
    const entries = [];
    containers.forEach((container) => {
      const entry = {};
      fields.forEach((f) => {
        const inp = container.querySelector(`[data-field="${prefix}_${f}"]`);
        if (inp) entry[f] = inp.value.trim();
      });
      if (entry[fields[0]] || entry[fields[1]]) {
        entries.push(entry);
      }
    });
    return entries;
  }

  function collectTags(container) {
    const tags = [];
    container.querySelectorAll('.lb-tag').forEach((tag) => {
      const text = tag.textContent.replace('×', '').trim();
      if (text) tags.push(text);
    });
    return tags;
  }

  // ── Build structured builder_data from form ──
  function buildBuilderData(formData) {
    return {
      personal: {
        full_name: formData.full_name || '',
        job_title: formData.job_title || '',
        email: formData.email || '',
        phone: formData.phone || '',
        address: formData.address || '',
        website: formData.website || '',
        linkedin: formData.linkedin || '',
        github: formData.github || '',
        portfolio: formData.portfolio || '',
      },
      summary: {
        professional_summary: formData.summary || '',
        career_objective: formData.objective || '',
        text: formData.summary || '',
      },
      experience: formData.experience || [],
      education: formData.education || [],
      projects: formData.projects || [],
      certificates: formData.certificates || [],
      skills: {
        technical: formData.technical_skills || [],
        soft: formData.soft_skills || [],
      },
      languages: formData.languages.map((l) => ({
        name: l,
        proficiency: 'Fluent',
      })),
    };
  }

  // ── LocalStorage Persistence ──
  function saveToLocalStorage() {
    try {
      var formData = collectFormData();
      var builderData = buildBuilderData(formData);
      var payload = {
        template: STATE.selectedTemplate,
        data: formData,
        builderData: builderData,
        savedAt: Date.now(),
      };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {
      // localStorage might be full or unavailable — silently ignore
    }
  }

  function loadFromLocalStorage() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.data || !parsed.savedAt) return null;
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function restoreFromLocalStorage() {
    var cached = loadFromLocalStorage();
    if (!cached) return false;

    // Merge cached data into STATE.data so renderInitialData() picks it up
    // Cached data takes precedence over server data (it's the latest user edits)
    STATE.data = STATE.data || {};
    Object.keys(cached.builderData || {}).forEach(function (key) {
      STATE.data[key] = cached.builderData[key];
    });

    // Also restore selected template if cached
    if (cached.template && cached.template !== STATE.selectedTemplate) {
      STATE.selectedTemplate = cached.template;
      updateFormSelection();
    }

    return true;
  }

  // ── Preview Updater ──
  function schedulePreviewUpdate() {
    if (STATE.previewTimer) {
      clearTimeout(STATE.previewTimer);
    }
    STATE.previewTimer = setTimeout(updatePreview, 250);
  }

  async function updatePreview() {
    const slug = STATE.selectedTemplate;
    if (!slug) return;

    const formData = collectFormData();

    // Hide iframe (prevents blank white flash when srcdoc is replaced)
    // Show subtle updating bar indicator instead of obtrusive loading overlay
    el.previewFrame.style.display = 'none';
    el.previewCanvas.classList.add('lb-updating');

    try {
      const resp = await fetch(STATE.previewUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          template: slug,
          data: buildBuilderData(formData),
          _csrf: STATE.csrf,
        }),
      });

      if (!resp.ok) {
        throw new Error(`Preview request failed: ${resp.status}`);
      }

      const result = await resp.json();
      if (!result.success) {
        throw new Error(result.error || 'Preview render failed');
      }

      // Write HTML into iframe (still hidden to prevent content flash)
      const frame = el.previewFrame;
      if (!frame) return;
      frame.classList.remove('fade-in');
      frame.srcdoc = result.html;

      // Reveal handler: apply zoom BEFORE showing, then fade in smoothly
      var _revealPreview = function () {
        applyAutoFit();
        frame.style.transform = 'scale(' + (STATE.zoom * STATE.autoFitScale) + ')';
        frame.style.transformOrigin = 'top center';
        frame.style.width = '210mm';
        frame.style.height = '297mm';
        frame.style.overflow = 'hidden';
        frame.style.display = 'block';
        el.previewCanvas.classList.remove('lb-updating');

        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            frame.classList.add('fade-in');
          });
        });
      };

      // One-time load handler — apply zoom BEFORE showing, no flash
      frame.addEventListener('load', function onLoad() {
        frame.removeEventListener('load', onLoad);
        clearTimeout(_revealTimer);
        _revealPreview();
      });

      // Safety fallback: reveal even if load event never fires
      var _revealTimer = setTimeout(function () {
        _revealPreview();
      }, 1000);

      STATE._pendingPreview = result.html;
    } catch (err) {
      console.error('[LiveBuilder] Preview error:', err);
      el.previewCanvas.classList.remove('lb-updating');
      el.previewLoading.style.display = 'flex';
      el.previewLoading.innerHTML = `
        <div class="flex flex-col items-center justify-center gap-2 text-center">
          <i class="lucide lucide-alert-circle text-red-500 opacity-70" style="width:1.8rem;height:1.8rem"></i>
          <p class="text-sm font-semibold text-red-500">Preview unavailable</p>
          <p class="text-xs text-slate-400 mt-1 max-w-[300px]">${escapeHtml(err.message)}</p>
        </div>
      `;
      el.previewFrame.style.display = 'none';
    }
  }

  // ── Auto-Save ──
  function scheduleSave() {
    STATE.isDirty = true;
    if (STATE.saveTimer) {
      clearTimeout(STATE.saveTimer);
    }
    STATE.saveTimer = setTimeout(saveData, 2000);
    // Also save to localStorage immediately on every change (no debounce — it's sync)
    saveToLocalStorage();
  }

  async function saveData() {
    if (STATE.isSaving || !STATE.cvId) return;
    STATE.isSaving = true;
    STATE.isDirty = false;

    el.saveIndicator.textContent = 'Saving...';
    el.saveIndicator.className = 'lb-save-indicator saving';

    try {
      const formData = collectFormData();
      const builderData = buildBuilderData(formData);

      // Save builder data
      const resp = await fetch(`/api/cv/${STATE.cvId}/step`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': STATE.csrf,
        },
        body: JSON.stringify({
          step: 'personal',
          data: builderData.personal,
          all_data: builderData,
        }),
      });

      if (!resp.ok) {
        throw new Error(`Save failed: ${resp.status}`);
      }

      const result = await resp.json();
      if (result.success) {
        el.saveIndicator.textContent = '✓ Saved';
        el.saveIndicator.className = 'lb-save-indicator saved';
      } else {
        el.saveIndicator.textContent = '✗ Save failed';
        el.saveIndicator.className = 'lb-save-indicator';
      }
    } catch (err) {
      console.error('[LiveBuilder] Save error:', err);
      el.saveIndicator.textContent = '✗ Save error';
      el.saveIndicator.className = 'lb-save-indicator';
    } finally {
      STATE.isSaving = false;
    }

    // Reset indicator after delay
    setTimeout(() => {
      el.saveIndicator.textContent = 'Ready';
      el.saveIndicator.className = 'lb-save-indicator';
    }, 3000);
  }

  // ── Entry Renderers ──
  function renderExperience(data) {
    const id = ++STATE.experienceCounter;
    return `
      <div class="lb-entry bg-white border border-slate-200 rounded-xl p-3.5 mb-3 relative transition-all duration-200 border-l-[3px] border-l-transparent hover:border-slate-300 hover:border-l-indigo-300 hover:shadow-[0_2px_8px_rgba(99,102,241,0.06)]" data-entry-type="experience" data-entry-id="${id}">
        <div class="lb-entry-header flex items-center justify-between mb-2.5">
          <span class="lb-entry-title text-sm font-semibold text-slate-800 flex items-center gap-1.5 before:w-1.5 before:h-1.5 before:rounded-full before:bg-indigo-300 before:flex-shrink-0 hover:before:bg-indigo-400">${escapeHtml(data.position || 'New Position')}</span>
          <button type="button" class="lb-entry-remove w-6 h-6 flex items-center justify-center rounded-md border-0 bg-transparent text-slate-400 opacity-40 cursor-pointer transition-all duration-150 hover:opacity-100 hover:bg-red-50 hover:text-red-500" data-action="remove-entry" data-entry-type="experience" data-entry-id="${id}">
            <i class="lucide lucide-x" style="width:0.85em;height:0.85em"></i>
          </button>
        </div>
        <div class="grid grid-cols-2 gap-2.5 mb-2.5">
          <div class="mb-2">
            <label class="${labelClass}">Company</label>
            <input type="text" class="${inputClass}" data-field="experience_company" value="${escapeHtml(data.company || '')}" placeholder="Company name">
          </div>
          <div class="mb-2">
            <label class="${labelClass}">Position</label>
            <input type="text" class="${inputClass}" data-field="experience_position" value="${escapeHtml(data.position || '')}" placeholder="Job title">
          </div>
        </div>
        <div class="mb-2">
          <label class="${labelClass}">Location</label>
          <input type="text" class="${inputClass}" data-field="experience_location" value="${escapeHtml(data.location || '')}" placeholder="City, Country">
        </div>
        <div class="grid grid-cols-2 gap-2.5 mb-2.5">
          <div class="mb-2">
            <label class="${labelClass}">Start Date</label>
            <input type="text" class="${inputClass}" data-field="experience_start_date" value="${escapeHtml(data.start_date || '')}" placeholder="e.g. Jan 2020">
          </div>
          <div class="mb-2">
            <label class="${labelClass}">End Date</label>
            <input type="text" class="${inputClass}" data-field="experience_end_date" value="${escapeHtml(data.end_date || '')}" placeholder="e.g. Present">
          </div>
        </div>
        <div class="mb-1">
          <label class="${labelClass}">Description</label>
          <textarea class="${inputClass} min-h-[56px] resize-y leading-relaxed" data-field="experience_description" rows="3" placeholder="Describe your responsibilities and achievements...">${escapeHtml(data.description || '')}</textarea>
        </div>
      </div>
    `;
  }

  function renderEducation(data) {
    const id = ++STATE.educationCounter;
    return `
      <div class="lb-entry bg-white border border-slate-200 rounded-xl p-3.5 mb-3 relative transition-all duration-200 border-l-[3px] border-l-transparent hover:border-slate-300 hover:border-l-indigo-300 hover:shadow-[0_2px_8px_rgba(99,102,241,0.06)]" data-entry-type="education" data-entry-id="${id}">
        <div class="lb-entry-header flex items-center justify-between mb-2.5">
          <span class="lb-entry-title text-sm font-semibold text-slate-800 flex items-center gap-1.5 before:w-1.5 before:h-1.5 before:rounded-full before:bg-indigo-300 before:flex-shrink-0 hover:before:bg-indigo-400">${escapeHtml(data.institution || 'New Education')}</span>
          <button type="button" class="lb-entry-remove w-6 h-6 flex items-center justify-center rounded-md border-0 bg-transparent text-slate-400 opacity-40 cursor-pointer transition-all duration-150 hover:opacity-100 hover:bg-red-50 hover:text-red-500" data-action="remove-entry" data-entry-type="education" data-entry-id="${id}">
            <i class="lucide lucide-x" style="width:0.85em;height:0.85em"></i>
          </button>
        </div>
        <div class="grid grid-cols-2 gap-2.5 mb-2.5">
          <div class="mb-2">
            <label class="${labelClass}">Institution</label>
            <input type="text" class="${inputClass}" data-field="education_institution" value="${escapeHtml(data.institution || '')}" placeholder="University / School">
          </div>
          <div class="mb-2">
            <label class="${labelClass}">Degree</label>
            <input type="text" class="${inputClass}" data-field="education_degree" value="${escapeHtml(data.degree || '')}" placeholder="e.g. Bachelor of Science">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2.5 mb-2.5">
          <div class="mb-2">
            <label class="${labelClass}">Field of Study</label>
            <input type="text" class="${inputClass}" data-field="education_field" value="${escapeHtml(data.field || '')}" placeholder="e.g. Computer Science">
          </div>
          <div class="mb-2">
            <label class="${labelClass}">GPA</label>
            <input type="text" class="${inputClass}" data-field="education_gpa" value="${escapeHtml(data.gpa || '')}" placeholder="e.g. 3.8">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="mb-2">
            <label class="${labelClass}">Start</label>
            <input type="text" class="${inputClass}" data-field="education_start_date" value="${escapeHtml(data.start_date || '')}" placeholder="2016">
          </div>
          <div class="mb-2">
            <label class="${labelClass}">End</label>
            <input type="text" class="${inputClass}" data-field="education_end_date" value="${escapeHtml(data.end_date || '')}" placeholder="2020">
          </div>
        </div>
      </div>
    `;
  }

  function renderProject(data) {
    const id = ++STATE.projectCounter;
    return `
      <div class="lb-entry bg-white border border-slate-200 rounded-xl p-3.5 mb-3 relative transition-all duration-200 border-l-[3px] border-l-transparent hover:border-slate-300 hover:border-l-indigo-300 hover:shadow-[0_2px_8px_rgba(99,102,241,0.06)]" data-entry-type="project" data-entry-id="${id}">
        <div class="lb-entry-header flex items-center justify-between mb-2.5">
          <span class="lb-entry-title text-sm font-semibold text-slate-800 flex items-center gap-1.5 before:w-1.5 before:h-1.5 before:rounded-full before:bg-indigo-300 before:flex-shrink-0 hover:before:bg-indigo-400">${escapeHtml(data.name || 'New Project')}</span>
          <button type="button" class="lb-entry-remove w-6 h-6 flex items-center justify-center rounded-md border-0 bg-transparent text-slate-400 opacity-40 cursor-pointer transition-all duration-150 hover:opacity-100 hover:bg-red-50 hover:text-red-500" data-action="remove-entry" data-entry-type="project" data-entry-id="${id}">
            <i class="lucide lucide-x" style="width:0.85em;height:0.85em"></i>
          </button>
        </div>
        <div class="mb-2">
          <label class="${labelClass}">Project Name</label>
          <input type="text" class="${inputClass}" data-field="project_name" value="${escapeHtml(data.name || '')}" placeholder="Project name">
        </div>
        <div class="mb-2">
          <label class="${labelClass}">Technologies</label>
          <input type="text" class="${inputClass}" data-field="project_technologies" value="${escapeHtml(data.technologies || '')}" placeholder="e.g. React, Node.js, PostgreSQL">
        </div>
        <div class="mb-2">
          <label class="${labelClass}">URL</label>
          <input type="url" class="${inputClass}" data-field="project_url" value="${escapeHtml(data.url || '')}" placeholder="https://...">
        </div>
        <div class="mb-1">
          <label class="${labelClass}">Description</label>
          <textarea class="${inputClass} min-h-[48px] resize-y leading-relaxed" data-field="project_description" rows="2" placeholder="Brief description...">${escapeHtml(data.description || '')}</textarea>
        </div>
      </div>
    `;
  }

  function renderCertificate(data) {
    const id = ++STATE.certCounter;
    return `
      <div class="lb-entry bg-white border border-slate-200 rounded-xl p-3.5 mb-3 relative transition-all duration-200 border-l-[3px] border-l-transparent hover:border-slate-300 hover:border-l-indigo-300 hover:shadow-[0_2px_8px_rgba(99,102,241,0.06)]" data-entry-type="certificate" data-entry-id="${id}">
        <div class="lb-entry-header flex items-center justify-between mb-2.5">
          <span class="lb-entry-title text-sm font-semibold text-slate-800 flex items-center gap-1.5 before:w-1.5 before:h-1.5 before:rounded-full before:bg-indigo-300 before:flex-shrink-0 hover:before:bg-indigo-400">${escapeHtml(data.name || 'New Certificate')}</span>
          <button type="button" class="lb-entry-remove w-6 h-6 flex items-center justify-center rounded-md border-0 bg-transparent text-slate-400 opacity-40 cursor-pointer transition-all duration-150 hover:opacity-100 hover:bg-red-50 hover:text-red-500" data-action="remove-entry" data-entry-type="certificate" data-entry-id="${id}">
            <i class="lucide lucide-x" style="width:0.85em;height:0.85em"></i>
          </button>
        </div>
        <div class="mb-2">
          <label class="${labelClass}">Certificate Name</label>
          <input type="text" class="${inputClass}" data-field="certificate_name" value="${escapeHtml(data.name || '')}" placeholder="Certificate name">
        </div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="mb-2">
            <label class="${labelClass}">Organization</label>
            <input type="text" class="${inputClass}" data-field="certificate_organization" value="${escapeHtml(data.organization || '')}" placeholder="Issuing org">
          </div>
          <div class="mb-2">
            <label class="${labelClass}">Date</label>
            <input type="text" class="${inputClass}" data-field="certificate_date" value="${escapeHtml(data.date || '')}" placeholder="2023">
          </div>
        </div>
      </div>
    `;
  }

  // ── Render existing data into form ──
  function renderInitialData() {
    const bd = STATE.data;
    const personal = bd.personal || {};

    // Personal fields already filled by Twig

    // Experience
    const expList = bd.experience || [];
    if (expList.length === 0) {
      // Add one empty entry as default
      el.experienceList.innerHTML = renderExperience({});
    } else {
      el.experienceList.innerHTML = expList.map((e) => renderExperience(e)).join('');
    }

    // Education
    const eduList = bd.education || [];
    if (eduList.length === 0) {
      el.educationList.innerHTML = renderEducation({});
    } else {
      el.educationList.innerHTML = eduList.map((e) => renderEducation(e)).join('');
    }

    // Projects
    const projList = bd.projects || [];
    el.projectsList.innerHTML = projList.map((p) => renderProject(p)).join('');

    // Certificates
    const certList = bd.certificates || [];
    el.certsList.innerHTML = certList.map((c) => renderCertificate(c)).join('');

    // Skills
    const skills = bd.skills || {};
    const techSkills = skills.technical || [];
    const softSkills = skills.soft || [];
    el.technicalSkills.innerHTML = techSkills.map((s) => makeTag(s)).join('');
    el.softSkills.innerHTML = softSkills.map((s) => makeTag(s)).join('');

    // Languages
    const langs = bd.languages || [];
    el.languagesList.innerHTML = langs.map((l) => {
      const name = typeof l === 'string' ? l : l.name || '';
      return name ? makeTag(name) : '';
    }).join('');
  }

  function makeTag(text) {
    return `<span class="lb-tag inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200 transition-all duration-150 animate-[tagIn_0.2s_ease-out]">${escapeHtml(text)}<button type="button" class="lb-tag-remove ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full text-indigo-400 hover:text-red-500 hover:bg-red-50 transition-colors duration-150 cursor-pointer border-0 bg-transparent p-0 leading-none text-sm" data-action="remove-tag">
            <i class="lucide lucide-x" style="width:0.75em;height:0.75em"></i>
          </button></span>`;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

// ── Zoom Presets ──
  const ZOOM_PRESETS = [
    { label: 'Fit Page', value: 'fit-page' },
    { label: 'Fit Width', value: 'fit-width' },
    { label: '100%', value: 1.0 },
    { label: '75%', value: 0.75 },
    { label: '50%', value: 0.5 },
  ];
  let _zoomPresetIndex = -1; // -1 = custom

  function applyZoomPreset(presetValue) {
    if (presetValue === 'fit-page') {
      STATE.zoom = 1.0;
      applyAutoFit();
      applyZoom();
      el.zoomLabel.textContent = 'Fit Page';
      _zoomPresetIndex = 0;
      updateZoomPresetUI();
      return;
    }
    if (presetValue === 'fit-width') {
      STATE.zoom = 1.0;
      // Scale to fit width only
      const canvas = el.previewCanvas;
      if (canvas) {
        const cs = getComputedStyle(canvas);
        const cw = Math.max(canvas.clientWidth - parseFloat(cs.paddingLeft || '16') - parseFloat(cs.paddingRight || '16') - 16, 400);
        const a4w = 210 * 3.78;
        STATE.autoFitScale = Math.max(0.3, Math.min(cw / a4w, 1.2));
      }
      applyZoom();
      el.zoomLabel.textContent = 'Fit Width';
      _zoomPresetIndex = 1;
      updateZoomPresetUI();
      return;
    }
    // Numeric preset
    STATE.zoom = presetValue;
    applyZoom();
    el.zoomLabel.textContent = Math.round(presetValue * 100) + '%';
    // Find index
    _zoomPresetIndex = ZOOM_PRESETS.findIndex(function (p) {
      return p.value === presetValue;
    });
    updateZoomPresetUI();
  }

  function cycleZoomPreset() {
    _zoomPresetIndex = (_zoomPresetIndex + 1) % ZOOM_PRESETS.length;
    const preset = ZOOM_PRESETS[_zoomPresetIndex];
    applyZoomPreset(preset.value);
  }

  function updateZoomPresetUI() {
    // Toggle active class on preset button if present
    var fitBtn = document.querySelector('[data-action="zoom-fit"]');
    if (fitBtn) {
      fitBtn.classList.toggle('active', _zoomPresetIndex <= 1);
    }
  }

  // ── Zoom Controls ──
  function applyAutoFit() {
    const canvas = el.previewCanvas;
    const frame = el.previewFrame;
    if (!canvas || !frame) return;
    const cs = getComputedStyle(canvas);
    const cw = Math.max(canvas.clientWidth - parseFloat(cs.paddingLeft || '16') - parseFloat(cs.paddingRight || '16') - 16, 400);
    const ch = Math.max(canvas.clientHeight - parseFloat(cs.paddingTop || '32') - parseFloat(cs.paddingBottom || '32') - 16, 400);
    // A4 in px: 210mm ≈ 794px, 297mm ≈ 1123px
    const a4w = 210 * 3.78;
    const a4h = 297 * 3.78;
    const fit = Math.min(cw / a4w, ch / a4h, 1.2);
    STATE.autoFitScale = Math.max(0.3, fit);
  }

  function applyZoom() {
    const frame = el.previewFrame;
    if (!frame || frame.style.display === 'none') {
      el.zoomLabel.textContent = Math.round(STATE.zoom * 100) + '%';
      return;
    }
    // Always show percentage — preset labels are set by applyZoomPreset()
    el.zoomLabel.textContent = Math.round(STATE.zoom * 100) + '%';
    _zoomPresetIndex = -1;
    updateZoomPresetUI();
    frame.style.transform = 'scale(' + (STATE.zoom * STATE.autoFitScale) + ')';
    frame.style.transformOrigin = 'top center';
    frame.style.width = '210mm';
    frame.style.height = '297mm';
    frame.style.overflow = 'hidden';
  }

  // ── PDF Export ──
  function exportPdf() {
    const slug = STATE.selectedTemplate;
    if (!slug || !STATE.cvId) return;

    const url = STATE.pdfUrl || `/cv-builder/${STATE.cvId}/export/pdf?template=${encodeURIComponent(slug)}`;
    window.open(url, '_blank');
  }

  // ── Section Toggle ──
  function setupSectionToggles() {
    document.querySelectorAll('[data-toggle-section]').forEach(function (title) {
      title.addEventListener('click', function () {
        const section = title.closest('.lb-section');
        if (!section) return;
        const body = section.querySelector('.lb-section-body');
        const toggle = title.querySelector('.lb-section-toggle');
        if (!body) return;
        const isCollapsed = body.classList.toggle('collapsed');
        if (toggle) toggle.classList.toggle('collapsed', isCollapsed);
      });
    });
  }

  // ── Keyboard Shortcuts ──
  function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function (e) {
      // Ctrl+S or Cmd+S to save
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        // Trigger save immediately
        if (STATE.saveTimer) {
          clearTimeout(STATE.saveTimer);
          STATE.saveTimer = null;
        }
        saveData();
      }
    });
  }

  // ── Event Delegation ──
  function setupEvents() {
    // Form input changes → preview + save
    el.form.addEventListener('input', function (e) {
      schedulePreviewUpdate();
      scheduleSave();
    });

    el.form.addEventListener('change', function (e) {
      schedulePreviewUpdate();
      scheduleSave();
    });

    // Section toggles
    setupSectionToggles();

    // Keyboard shortcuts
    setupKeyboardShortcuts();

    // Zoom controls
    document.querySelectorAll('[data-action="zoom-in"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        STATE.zoom = Math.min(2.0, STATE.zoom + 0.1);
        applyZoom();
      });
    });
    document.querySelectorAll('[data-action="zoom-out"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        STATE.zoom = Math.max(0.5, STATE.zoom - 0.1);
        applyZoom();
      });
    });
    document.querySelectorAll('[data-action="zoom-fit"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        cycleZoomPreset();
      });
    });
    document.querySelectorAll('[data-action="reset-zoom"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        applyZoomPreset('fit-page');
      });
    });

    // Click zoom label to cycle presets
    if (el.zoomLabel) {
      el.zoomLabel.addEventListener('click', cycleZoomPreset);
      el.zoomLabel.style.cursor = 'pointer';
    }

    // PDF export
    document.querySelectorAll('[data-action="export-pdf"]').forEach((btn) => {
      btn.addEventListener('click', exportPdf);
    });

    // Add entry buttons
    document.querySelectorAll('[data-action="add-experience"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        el.experienceList.insertAdjacentHTML('beforeend', renderExperience({}));
        schedulePreviewUpdate();
      });
    });
    document.querySelectorAll('[data-action="add-education"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        el.educationList.insertAdjacentHTML('beforeend', renderEducation({}));
        schedulePreviewUpdate();
      });
    });
    document.querySelectorAll('[data-action="add-project"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        el.projectsList.insertAdjacentHTML('beforeend', renderProject({}));
        schedulePreviewUpdate();
      });
    });
    document.querySelectorAll('[data-action="add-certificate"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        el.certsList.insertAdjacentHTML('beforeend', renderCertificate({}));
        schedulePreviewUpdate();
      });
    });

    // Remove entry (delegated)
    el.form.addEventListener('click', (e) => {
      const removeBtn = e.target.closest('[data-action="remove-entry"]');
      if (removeBtn) {
        const entry = removeBtn.closest('.lb-entry');
        if (entry) {
          entry.remove();
          schedulePreviewUpdate();
          scheduleSave();
        }
        return;
      }

      const removeTag = e.target.closest('[data-action="remove-tag"]');
      if (removeTag) {
        const tag = removeTag.closest('.lb-tag');
        if (tag) {
          tag.remove();
          schedulePreviewUpdate();
          scheduleSave();
        }
        return;
      }
    });

    // Skill tag inputs (Enter key)
    function setupTagInput(inputEl, container) {
      inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
          e.preventDefault();
          const val = inputEl.value.trim();
          if (val) {
            container.insertAdjacentHTML('beforeend', makeTag(val));
            inputEl.value = '';
            schedulePreviewUpdate();
            scheduleSave();
          }
        }
      });
      // Also allow Tab
      inputEl.addEventListener('blur', () => {
        const val = inputEl.value.trim();
        if (val) {
          container.insertAdjacentHTML('beforeend', makeTag(val));
          inputEl.value = '';
          schedulePreviewUpdate();
          scheduleSave();
        }
      });
    }

    setupTagInput(el.technicalInput, el.technicalSkills);
    setupTagInput(el.softInput, el.softSkills);

    // Add language
    el.addLanguageBtn.addEventListener('click', () => {
      const lang = el.languageInput.value.trim();
      if (lang) {
        el.languagesList.insertAdjacentHTML('beforeend', makeTag(lang));
        el.languageInput.value = '';
        schedulePreviewUpdate();
        scheduleSave();
      }
    });
    el.languageInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        el.addLanguageBtn.click();
      }
    });

    // Remove entries via delegation (tag remove via click handled above)
  }

  // ── Form Template Grid ──
  function renderFormTemplateGrid() {
    var grid = document.getElementById('lb-form-tpl-grid');
    if (!grid) return;

    grid.innerHTML = STATE.templates.map(function (t) {
      var color = t.primary_color || '#6366f1';
      var selected = (t.slug === STATE.selectedTemplate) ? ' selected' : '';
      var premium = t.is_premium ? '<div class="lb-tpl-form-card-badge">Premium</div>' : '';
      var layout = getLayoutType(t.slug);
      // Use server-generated thumbnail if available, fall back to layout icon
      var thumbUrl = t.thumbnail_url || null;
      var layoutSvg = thumbUrl ? '' : getLayoutIcon(layout);
      return '<div class="lb-tpl-form-card' + selected + ' border border-slate-200 rounded-lg overflow-hidden cursor-pointer transition-all duration-150 bg-white hover:border-indigo-300 hover:-translate-y-px hover:shadow-[0_4px_12px_rgba(99,102,241,0.1)]" data-slug="' + t.slug + '" data-name="' + (t.name || '').toLowerCase() + '" data-cat="' + (t.category || 'general').toLowerCase() + '" title="' + escapeHtml(t.description || t.best_for || t.name || '') + '">' +
        '<div class="lb-tpl-form-card-thumb" style="background:' + (thumbUrl ? '#ffffff' : color) + ';">' +
          (thumbUrl
            ? '<img src="' + escapeHtml(thumbUrl) + '" alt="' + escapeHtml(t.name || t.slug) + '" style="width:100%;height:100%;object-fit:contain;position:absolute;inset:0;" loading="lazy">'
            : '<div class="lb-tpl-card-pattern"></div>' +
              '<div class="lb-tpl-form-card-layout">' + layoutSvg + '</div>'
          ) +
          '<div class="lb-tpl-form-card-name text-[0.55rem] font-bold drop-shadow-sm">' + escapeHtml(t.name || t.slug) + '</div>' +
          premium +
        '</div>' +
        '<div class="lb-tpl-form-card-info flex items-center justify-between gap-1 px-1.5 py-1 bg-gradient-to-b from-indigo-50/50 to-white">' +
          '<span class="lb-tpl-form-card-cat text-[0.5rem] font-semibold uppercase tracking-wider text-indigo-500">' + escapeHtml(t.category || 'General') + '</span>' +
          '<span class="lb-tpl-form-card-version text-[0.45rem] text-slate-400 font-medium">v' + (t.version || '1.0') + '</span>' +
        '</div>' +
      '</div>';
    }).join('');

    applyFormFilters();
    updateFormSelection();
    setupFormGridEvents();
  }

  function setupFormGridEvents() {
    var grid = document.getElementById('lb-form-tpl-grid');
    if (!grid) return;

    // Card click — select template
    grid.addEventListener('click', function (e) {
      var card = e.target.closest('.lb-tpl-form-card');
      if (!card) return;
      var slug = card.getAttribute('data-slug');
      if (!slug) return;

      selectFormTemplate(slug);
    });

    // Search filter
    var searchInput = document.getElementById('lb-form-tpl-search');
    if (searchInput) {
      searchInput.addEventListener('input', applyFormFilters);
    }

    // Category pills
    var catsEl = document.getElementById('lb-form-tpl-cats');
    if (catsEl) {
      catsEl.addEventListener('click', function (e) {
        var pill = e.target.closest('.lb-tpl-cat-pill');
        if (!pill) return;
        catsEl.querySelectorAll('.lb-tpl-cat-pill').forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        applyFormFilters();
      });
    }
  }

  function applyFormFilters() {
    var grid = document.getElementById('lb-form-tpl-grid');
    var noneEl = document.getElementById('lb-form-tpl-none');
    var searchInput = document.getElementById('lb-form-tpl-search');
    var catsEl = document.getElementById('lb-form-tpl-cats');
    if (!grid) return;

    var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    var activeCat = catsEl ? catsEl.querySelector('.lb-tpl-cat-pill.active') : null;
    var cat = activeCat ? activeCat.getAttribute('data-cat') : 'all';

    var visibleCount = 0;
    grid.querySelectorAll('.lb-tpl-form-card').forEach(function (card) {
      var name = card.getAttribute('data-name') || '';
      var cardCat = card.getAttribute('data-cat') || '';
      var matchesSearch = !query || name.indexOf(query) !== -1;
      var matchesCat = cat === 'all' || cardCat === cat;
      var visible = matchesSearch && matchesCat;
      card.classList.toggle('hidden', !visible);
      if (visible) visibleCount++;
    });

    if (noneEl) noneEl.classList.toggle('show', visibleCount === 0);
  }

  function updateFormSelection() {
    var grid = document.getElementById('lb-form-tpl-grid');
    if (!grid) return;
    grid.querySelectorAll('.lb-tpl-form-card').forEach(function (card) {
      var slug = card.getAttribute('data-slug');
      card.classList.toggle('selected', slug === STATE.selectedTemplate);
    });
  }

  function selectFormTemplate(slug) {
    if (slug === STATE.selectedTemplate) return;

    STATE.selectedTemplate = slug;

    // Update form grid
    updateFormSelection();

    // Trigger preview refresh & save
    schedulePreviewUpdate();
    scheduleSave();

    // Persist
    if (STATE.cvId) {
      fetch('/api/cv/builder/' + STATE.cvId + '/step', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf },
        body: JSON.stringify({
          step: '_template',
          data: { template: slug },
          all_data: { _template: slug }
        }),
      }).catch(function (err) {
        console.warn('[LiveBuilder] Failed to persist template selection:', err);
      });
    }
  }

  // ── Init ──
  function init() {
    // Ensure templates is flat array of {slug, name}
    if (!Array.isArray(STATE.templates) && typeof STATE.templates === 'object') {
      STATE.templates = Object.entries(STATE.templates).map(([slug, t]) => ({
        slug,
        name: t.name || slug,
        ...t,
      }));
    }

    // Restore any unsaved data from localStorage (survives page refresh)
    restoreFromLocalStorage();

    renderFormTemplateGrid();
    renderInitialData();
    setupEvents();

    // Initial preview load
    if (STATE.selectedTemplate) {
      // Show initial loading state
      el.previewLoading.style.display = 'flex';
      // Small delay to ensure DOM is fully rendered
      setTimeout(function () {
        applyAutoFit();
        updatePreview().then(function () {
          // Apply fit-page preset AFTER load handler has revealed iframe
          // Load handler applies transform before showing, so no visual flash
          applyZoomPreset('fit-page');
        }).catch(function () {
          // Preview might fail for some templates, that's OK
          el.previewLoading.innerHTML = '' +
            '<div class="lb-preview-empty flex flex-col items-center justify-center gap-3 text-slate-400 text-center min-h-[300px] p-8 animate-[lbFadeIn_0.3s_ease]">' +
            '<i class="lucide lucide-file-text lb-preview-empty-icon text-4xl opacity-40" style="width:2.8rem;height:2.8rem"></i>' +
            '<div class="lb-preview-empty-text text-sm font-semibold text-slate-500">Select a template to preview</div>' +
            '<div class="lb-preview-empty-sub text-xs text-slate-400 max-w-[280px] leading-relaxed">Click the template selector above to browse available layouts</div>' +
            '</div>';
        });
      }, 100);
    } else {
      // No template selected — show empty state
      el.previewLoading.innerHTML = '' +
        '<div class="lb-preview-empty flex flex-col items-center justify-center gap-3 text-slate-400 text-center min-h-[300px] p-8 animate-[lbFadeIn_0.3s_ease]">' +
        '<i class="lucide lucide-palette lb-preview-empty-icon text-4xl opacity-40" style="width:2.8rem;height:2.8rem"></i>' +
        '<div class="lb-preview-empty-text text-sm font-semibold text-slate-500">Choose a template to start</div>' +
        '<div class="lb-preview-empty-sub text-xs text-slate-400 max-w-[280px] leading-relaxed">Pick from 50+ professionally designed layouts</div>' +
        '</div>';
    }

    // Each updatePreview() call registers its own one-time load handler,
    // so no persistent listener is needed here.

    // Save to localStorage immediately before page unload
    window.addEventListener('beforeunload', function () {
      saveToLocalStorage();
    });

    // Window resize — recalculate auto-fit
    var resizeTimer;
    window.addEventListener('resize', function () {
      if (resizeTimer) clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        applyAutoFit();
        applyZoom();
      }, 150);
    });

    console.log('[LiveBuilder] Initialized', {
      cvId: STATE.cvId,
      template: STATE.selectedTemplate,
      templates: STATE.templates.length,
    });
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
