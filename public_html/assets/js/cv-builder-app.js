(function () {
  'use strict';

  const STEPS = [
    { id: 'personal', title: 'Personal Information', icon: 'user', desc: 'Tell us about yourself', },
    { id: 'professional', title: 'Professional Details', icon: 'briefcase', desc: 'Experience, education, skills & languages', },
    { id: 'social_links', title: 'Social Links', icon: 'share-2', desc: 'Add your social media and online profiles', },
    { id: 'custom_sections', title: 'Custom Sections', icon: 'layout', desc: 'Add extra sections', },
    { id: 'references', title: 'References', icon: 'users', desc: 'Optional references', },
    function collectStepData(stepId) {
      `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('languages',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('languages',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(name) }</div>${
        prof ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(prof) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" onclick="window.bldEditEntry('languages',${ idx })">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" onclick="window.bldRemoveEntry('languages',${ idx })"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderSocialLinkEntry(link, idx) {
    const l = link || {};
    const isEditing = STATE._editingSection === 'social_links' && STATE._editingIndex === idx;
    const platforms = ['linkedin', 'github', 'twitter', 'website', 'facebook', 'instagram', 'youtube', 'dribbble', 'behance', 'other',];
    let platOptions = '';
    for (let pi = 0; pi < platforms.length; pi++) {
      const p = platforms[pi];
      platOptions += `<option value="${ p }"${ l.platform === p ? ' selected' : '' }>${ p.charAt(0).toUpperCase() }${p.slice(1) }</option>`;
    }
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('social_links',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('social_links',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('social_links',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Platform</label><select class="bld-select link-platform">${ platOptions }</select></div>` +
        `<div class="bld-form-group"><label class="bld-label">URL</label><input class="bld-input link-url" value="${ escHtml(l.url || '') }" placeholder="https://..."></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" onclick="window.bldDoneEditing()"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" onclick="window.bldRemoveEntry('social_links',${ idx })">Remove</button></div></div>`;
    }
    const iconMap = { linkedin: 'linkedin', github: 'github', twitter: 'twitter', website: 'globe', facebook: 'facebook', instagram: 'instagram', youtube: 'youtube', dribbble: 'dribbble', behance: 'behance', other: 'link', };
    const plat = l.platform || 'other';
    const icon = iconMap[plat] || 'link';
    const url = l.url || '';
    const displayUrl = url.length > 40 ? `${url.slice(0, 40) }...` : url;
    const platLabel = plat.charAt(0).toUpperCase() + plat.slice(1);
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('social_links',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('social_links',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      '<div style="display:flex;align-items:center;gap:0.4rem;">' +
      `<i class="lucide lucide-${ icon }" style="width:1em;height:1em;color:#6366f1;flex-shrink:0;"></i>` +
      `<span style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(platLabel) }</span></div>${
        displayUrl ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;word-break:break-all;">${ escHtml(displayUrl) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" onclick="window.bldEditEntry('social_links',${ idx })">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" onclick="window.bldRemoveEntry('social_links',${ idx })"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderCustomSectionEntry(sec, idx) {
    const s = sec || {};
    const isEditing = STATE._editingSection === 'custom_sections' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('custom_sections',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('custom_sections',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('custom_sections',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-form-group"><label class="bld-label">Section Title</label><input class="bld-input custom-title" value="${ escHtml(s.title || '') }" placeholder="e.g. Volunteer Work"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Content</label><textarea class="bld-textarea custom-content" style="min-height:80px;" placeholder="Describe this section...">${ escHtml(s.content || '') }</textarea></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" onclick="window.bldDoneEditing()"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" onclick="window.bldRemoveEntry('custom_sections',${ idx })">Remove</button></div></div>`;
    }
    const title = s.title || 'New Section';
    let preview = (s.content || '').slice(0, 100);
    if (preview.length >= 100) preview += '...';
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('custom_sections',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('custom_sections',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(title) }</div>${
        preview ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(preview) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" onclick="window.bldEditEntry('custom_sections',${ idx })">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" onclick="window.bldRemoveEntry('custom_sections',${ idx })"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderExperienceEntry(exp, idx) {
    const e = exp || {};
    const isEditing = STATE._editingSection === 'experience' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('experience',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('experience',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('experience',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Company</label><input class="bld-input exp-company" value="${ escHtml(e.company || '') }" placeholder="Company name"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Position</label><input class="bld-input exp-position" value="${ escHtml(e.position || '') }" placeholder="Job title"></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Location</label><input class="bld-input exp-location" value="${ escHtml(e.location || '') }" placeholder="City"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Start Date</label><input class="bld-input exp-start_date" value="${ escHtml(e.start_date || '') }" placeholder="Jan 2020"></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">End Date</label><input class="bld-input exp-end_date" value="${ escHtml(e.end_date || '') }" placeholder="Present"></div>` +
        `<div class="bld-form-group"><label class="bld-checkbox"><input type="checkbox" class="exp-current" ${ e.is_current ? 'checked' : '' }> Currently here</label></div></div>` +
        `<div class="bld-form-group"><label class="bld-label">Responsibilities</label><textarea class="bld-textarea exp-responsibilities" style="min-height:80px;">${ escHtml(e.responsibilities || '') }</textarea></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" onclick="window.bldDoneEditing()"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" onclick="window.bldRemoveEntry('experience',${ idx })">Remove</button></div></div>`;
    }
    const title = [e.company, e.position,].filter(Boolean).join(' \u00b7 ') || 'New Experience';
    const dateRange = [e.start_date, e.is_current ? 'Present' : e.end_date,].filter(Boolean).join(' - ');
    const loc = e.location || '';
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('experience',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('experience',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(title) }</div>${
        dateRange ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(dateRange) }${loc ? ` &middot; ${ escHtml(loc)}` : '' }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" onclick="window.bldEditEntry('experience',${ idx })">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" onclick="window.bldRemoveEntry('experience',${ idx })"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderEducationEntry(edu, idx) {
    const e = edu || {};
    const isEditing = STATE._editingSection === 'education' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('education',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('education',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('education',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Institution</label><input class="bld-input edu-institution" value="${ escHtml(e.institution || '') }" placeholder="University"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Degree</label><input class="bld-input edu-degree" value="${ escHtml(e.degree || '') }" placeholder="B.Sc."></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Field</label><input class="bld-input edu-field" value="${ escHtml(e.field || '') }" placeholder="Computer Science"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Year</label><input class="bld-input edu-year" value="${ escHtml(e.year || '') }" placeholder="2024"></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" onclick="window.bldDoneEditing()"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" onclick="window.bldRemoveEntry('education',${ idx })">Remove</button></div></div>`;
    }
    const title = [e.institution, e.degree,].filter(Boolean).join(' \u00b7 ') || 'New Education';
    const detail = [e.field, e.year,].filter(Boolean).join(' | ');
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('education',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('education',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(title) }</div>${
        detail ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(detail) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" onclick="window.bldEditEntry('education',${ idx })">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" onclick="window.bldRemoveEntry('education',${ idx })"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderReferenceEntry(ref, idx) {
    const r = ref || {};
    const isEditing = STATE._editingSection === 'references' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('references',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('references',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('references',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Name</label><input class="bld-input ref-name" value="${ escHtml(r.name || '') }" placeholder="Reference name"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Title</label><input class="bld-input ref-title" value="${ escHtml(r.title || '') }" placeholder="e.g. Manager"></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Email</label><input class="bld-input ref-email" value="${ escHtml(r.email || '') }" placeholder="email@example.com"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Phone</label><input class="bld-input ref-phone" value="${ escHtml(r.phone || '') }" placeholder="+1 555-0000"></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" onclick="window.bldDoneEditing()"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" onclick="window.bldRemoveEntry('references',${ idx })">Remove</button></div></div>`;
    }
    const name = r.name || 'New Reference';
    const detail = [r.title, r.email,].filter(Boolean).join(' \u00b7 ');
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" onclick="window.bldMoveEntry('references',${ idx },'up')" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" onclick="window.bldMoveEntry('references',${ idx },'down')" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(name) }</div>${
        detail ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(detail) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" onclick="window.bldEditEntry('references',${ idx })">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" onclick="window.bldRemoveEntry('references',${ idx })"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderSkillTags(skills, category) {
    if (renderSkillTagsFn) {
      return renderSkillTagsFn(skills, category);
    }
    if (!Array.isArray(skills)) return '';
    let html = '';
    for (let i = 0; i < skills.length; i++) {
      html += `<span class="bld-skill-tag" style="cursor:pointer;" onclick="window.bldEditSkill('${ category }',${ i })" title="Click to edit">${
        escHtml(skills[i])
      }<button class="bld-skill-remove" onclick="event.stopPropagation();window.bldRemoveSkill('${ category }',${ i })"><i class="lucide lucide-x" style="width:0.8em;height:0.8em;"></i></button></span>`;
    }
    return html;
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
    case 'social_links': {
      const links = [];
      const linkCards = document.querySelectorAll('#bld-social-links-list .bld-entry-card');
      for (let li = 0; li < linkCards.length; li++) {
        links.push({ platform: qval(linkCards[li], '.link-platform'), url: qval(linkCards[li], '.link-url'), });
      }
      return links;
    }
    case 'custom_sections': {
      const secs = [];
      const secCards = document.querySelectorAll('#bld-custom-sections-list .bld-entry-card');
      for (let si = 0; si < secCards.length; si++) {
        secs.push({ title: qval(secCards[si], '.custom-title'), content: qval(secCards[si], '.custom-content'), });
      }
      return secs;
    }
    case 'references': {
      const refs = [];
      const refCards = document.querySelectorAll('#bld-references-list .bld-entry-card');
      for (let ri = 0; ri < refCards.length; ri++) {
        refs.push({ name: qval(refCards[ri], '.ref-name'), title: qval(refCards[ri], '.ref-title'), email: qval(refCards[ri], '.ref-email'), phone: qval(refCards[ri], '.ref-phone'), });
      }
      return refs;
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

  function saveAndLoadPreview() {
    // Save current data before loading preview
    const stepId = STEPS[STATE.currentStep].id;
    if (stepId !== 'review') {
      STATE.data[stepId] = collectStepData(stepId);
    }
    saveBuilderData(true);
    // Wait a brief moment for save to complete, then load iframe
    setTimeout(() => {
      loadPreviewIframe();
    }, 400);
  }

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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
