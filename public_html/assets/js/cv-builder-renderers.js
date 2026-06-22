export function createCvBuilderRenderers(deps) {
  const { STATE, STEPS, escHtml, } = deps;

  function renderStepContent(index) {
    const stepId = STEPS[index].id;
    const d = STATE.data[stepId] || {};
    const fill = function (key, def) { return d && d[key] !== undefined && d[key] !== null ? escHtml(String(d[key])) : def || ''; };

    switch (stepId) {
      case 'personal': {
        const photoUrl = STATE.profilePhoto || '';
        const hasPhoto = Boolean(photoUrl);
        let photoHtml = '<div class="bld-form-group">';
        photoHtml += '<label class="bld-label">Profile Photo</label>';
        photoHtml += `<div class="bld-photo-upload${ hasPhoto ? ' has-photo' : '' }" id="bld-photo-upload-area">`;
        photoHtml += '<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="bld-photo-file-input" id="bld-photo-input" data-action="upload-photo">';
        if (hasPhoto) {
          photoHtml += `<img src="${ escHtml(photoUrl) }" alt="Profile Photo" class="bld-photo-preview" id="bld-photo-preview-img">`;
        } else {
          photoHtml += '<div class="bld-photo-placeholder" id="bld-photo-placeholder"><i class="lucide lucide-camera" style="width:1em;height:1em;"></i></div>';
        }
        photoHtml += `<div class="bld-photo-label">${ hasPhoto ? 'Change Photo' : 'Add a Profile Photo' }</div>`;
        photoHtml += '<div class="bld-photo-hint">JPG, PNG, WebP or GIF &middot; Max 5MB</div>';
        photoHtml += '<div class="bld-photo-actions">';
        photoHtml += `<button type="button" class="bld-photo-btn bld-photo-btn-upload" data-action="trigger-photo-input"><i class="lucide lucide-upload" style="width:1em;height:1em;"></i> ${ hasPhoto ? 'Change' : 'Upload' }</button>`;
        if (hasPhoto) {
          photoHtml += '<button type="button" class="bld-photo-btn bld-photo-btn-remove" data-action="remove-photo"><i class="lucide lucide-trash-2" style="width:1em;height:1em;"></i> Remove</button>';
        }
        photoHtml += '</div>';
        photoHtml += '<div class="bld-photo-progress" id="bld-photo-progress"><div class="bld-photo-progress-bar" id="bld-photo-progress-bar"></div></div>';
        photoHtml += '</div></div>';

        return `${photoHtml
        }<div class="bld-input-group">` +
          `<div class="bld-form-group"><label class="bld-label">Full Name *</label><input class="bld-input" id="bld-field-full_name" value="${fill('full_name')}" placeholder="e.g. John Doe"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Job Title *</label><input class="bld-input" id="bld-field-job_title" value="${fill('job_title')}" placeholder="e.g. Software Engineer"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Email</label><input class="bld-input" id="bld-field-email" type="email" value="${fill('email')}" placeholder="john@example.com"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Phone</label><input class="bld-input" id="bld-field-phone" type="tel" value="${fill('phone')}" placeholder="+1 555-0000"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Date of Birth</label><input class="bld-input datepicker" id="bld-field-dob" type="date" data-disable-future="true" value="${fill('date_of_birth')}"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Nationality</label><input class="bld-input" id="bld-field-nationality" value="${fill('nationality')}" placeholder="e.g. American"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Gender</label><select class="bld-select" id="bld-field-gender"><option value="">Select...</option><option value="male"${d.gender === 'male' ? ' selected' : ''}>Male</option><option value="female"${d.gender === 'female' ? ' selected' : ''}>Female</option><option value="other"${d.gender === 'other' ? ' selected' : ''}>Other</option></select></div>` +
          `<div class="bld-form-group"><label class="bld-label">Address</label><input class="bld-input" id="bld-field-address" value="${fill('address')}" placeholder="City, Country"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Website / Portfolio</label><input class="bld-input" id="bld-field-website" value="${fill('website')}" placeholder="https://yoursite.com"></div>` +
          `<div class="bld-form-group"><label class="bld-label">LinkedIn</label><input class="bld-input" id="bld-field-linkedin" value="${fill('linkedin')}" placeholder="linkedin.com/in/yourprofile"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">GitHub</label><input class="bld-input" id="bld-field-github" value="${fill('github')}" placeholder="github.com/yourusername"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Twitter / X</label><input class="bld-input" id="bld-field-twitter" value="${fill('twitter')}" placeholder="twitter.com/yourhandle"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">National ID No.</label><input class="bld-input" id="bld-field-national_id_no" value="${fill('national_id_no')}" placeholder="e.g. Aadhaar, SSN, NID"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Passport No.</label><input class="bld-input" id="bld-field-passport_no" value="${fill('passport_no')}" placeholder="e.g. AB123456"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Birth Certificate No.</label><input class="bld-input" id="bld-field-birth_certificate_no" value="${fill('birth_certificate_no')}" placeholder="Birth certificate number"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Religion</label><select class="bld-select" id="bld-field-religion"><option value="">Select...</option><option value="islam"${d.religion === 'islam' ? ' selected' : ''}>Islam</option><option value="hinduism"${d.religion === 'hinduism' ? ' selected' : ''}>Hinduism</option><option value="christianity"${d.religion === 'christianity' ? ' selected' : ''}>Christianity</option><option value="buddhism"${d.religion === 'buddhism' ? ' selected' : ''}>Buddhism</option><option value="other"${d.religion === 'other' ? ' selected' : ''}>Other</option></select></div></div>`;
      }

      case 'professional': {
        const exps = Array.isArray(STATE.data.experience) ? STATE.data.experience : [];
        let expHtml = '';
        for (let ei = 0; ei < exps.length; ei++) {
          expHtml += renderExperienceEntry(exps[ei], ei);
        }
        const eds = Array.isArray(STATE.data.education) ? STATE.data.education : [];
        let eduHtml = '';
        for (let edi = 0; edi < eds.length; edi++) {
          eduHtml += renderEducationEntry(eds[edi], edi);
        }
        const sk = STATE.data.skills || {};
        const technical = Array.isArray(sk.technical) ? sk.technical : [];
        const soft = Array.isArray(sk.soft) ? sk.soft : [];
        const langs = Array.isArray(STATE.data.languages) ? STATE.data.languages : [];
        let langHtml = '';
        for (let li = 0; li < langs.length; li++) {
          langHtml += renderLanguageEntry(langs[li], li);
        }
        return '<div style="margin-bottom:2rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-briefcase" style="width:1.2em;height:1.2em;color:#f59e0b;"></i> Work Experience</h3>' +
          `<div id="bld-experience-list">${ expHtml }</div>` +
          '<button class="bld-add-entry" data-action="add-experience"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Experience</button></div>' +
          '<div style="margin-bottom:2rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-graduation-cap" style="width:1.2em;height:1.2em;color:#10b981;"></i> Education</h3>' +
          `<div id="bld-education-list">${ eduHtml }</div>` +
          '<button class="bld-add-entry" data-action="add-education"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Education</button></div>' +
          '<div style="margin-bottom:2rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-zap" style="width:1.2em;height:1.2em;color:#06b6d4;"></i> Skills</h3>' +
          '<div class="bld-form-group"><label class="bld-label">Technical Skills</label>' +
          `<div class="bld-skills-area" id="bld-skills-technical">${ renderSkillTags(technical, 'technical') }</div>` +
          '<input class="bld-skill-input" id="bld-skill-input-technical" placeholder="Type and press Enter..." data-action="add-skill"></div>' +
          '<div class="bld-form-group"><label class="bld-label">Soft Skills</label>' +
          `<div class="bld-skills-area" id="bld-skills-soft">${ renderSkillTags(soft, 'soft') }</div>` +
          '<input class="bld-skill-input" id="bld-skill-input-soft" placeholder="Type and press Enter..." data-action="add-skill"></div></div>' +
          '<div style="margin-bottom:1rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-globe" style="width:1.2em;height:1.2em;color:#3b82f6;"></i> Languages</h3>' +
          '<p style="color:#6b7280;font-size:0.85rem;margin-bottom:1rem;">Add languages you speak and your proficiency level.</p>' +
          `<div id="bld-languages-list">${ langHtml }</div>` +
          '<button class="bld-add-entry" data-action="add-language"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Language</button></div>';
      }

      case 'extras': {
      // Combined step: Social Links + Custom Sections + References
        const socialLinks = Array.isArray(STATE.data.social_links) ? STATE.data.social_links : [];
        let linkHtml = '';
        for (let li = 0; li < socialLinks.length; li++) {
          linkHtml += renderSocialLinkEntry(socialLinks[li], li);
        }
        const customSecs = Array.isArray(STATE.data.custom_sections) ? STATE.data.custom_sections : [];
        let secHtml = '';
        for (let si = 0; si < customSecs.length; si++) {
          secHtml += renderCustomSectionEntry(customSecs[si], si);
        }
        const refs = Array.isArray(STATE.data.references) ? STATE.data.references : [];
        let refHtml = '';
        for (let ri = 0; ri < refs.length; ri++) {
          refHtml += renderReferenceEntry(refs[ri], ri);
        }
        return '<div style="margin-bottom:2rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-share-2" style="width:1.2em;height:1.2em;color:#6366f1;"></i> Social Links</h3>' +
          '<p style="color:#6b7280;font-size:0.85rem;margin-bottom:1rem;">Add your social media profiles and online presence links.</p>' +
          `<div id="bld-social-links-list">${ linkHtml }</div>` +
          '<button class="bld-add-entry" data-action="add-social-link"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Social Link</button></div>' +
          '<div style="margin-bottom:2rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-layout" style="width:1.2em;height:1.2em;color:#f59e0b;"></i> Custom Sections</h3>' +
          '<p style="color:#6b7280;font-size:0.85rem;margin-bottom:1rem;">Add custom sections like Volunteer Work, Publications, Hobbies, etc.</p>' +
          `<div id="bld-custom-sections-list">${ secHtml }</div>` +
          '<button class="bld-add-entry" data-action="add-custom-section"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Custom Section</button></div>' +
          '<div style="margin-bottom:1rem;">' +
          '<h3 style="font-size:1.1rem;font-weight:700;color:#374151;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;gap:0.5rem;">' +
          '<i class="lucide lucide-users" style="width:1.2em;height:1.2em;color:#10b981;"></i> References</h3>' +
          '<p style="color:#6b7280;font-size:0.85rem;margin-bottom:1rem;">References are optional. You can skip this step.</p>' +
          `<div id="bld-references-list">${ refHtml }</div>` +
          '<button class="bld-add-entry" data-action="add-reference"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Reference</button></div>';
      }

      case 'review': {
        return renderReviewStep();
      }

      default:
        return '<p>Step not available.</p>';
    }
  }

  function renderReviewStep() {
    const templates = window.__bldTemplates || ['modern', 'minimal', 'ats', 'professional',];
    const templateLabels = {
      modern: 'Modern',
      minimal: 'Minimal',
      ats: 'ATS',
      professional: 'Professional',
      creative: 'Creative',
      classic: 'Classic',
      technical: 'Technical',
      executive: 'Executive',
    };
    let html = '<div class="bld-step-header">' +
      '<div class="bld-step-number"><i class="lucide lucide-eye" style="width:1em;height:1em;"></i> Final Step</div>' +
      '<h2 class="bld-step-title">Live Preview &amp; Finish</h2>' +
      '<p class="bld-step-desc">Preview your CV in a template, apply the one you want, and download the finished PDF.</p></div>';

    // Template-specific gradient backgrounds for card thumbnails
    const templateGradients = {
      modern: 'linear-gradient(135deg, #4f46e5, #7c3aed)' ,
      minimal: 'linear-gradient(135deg, #334155, #64748b)',
      ats: 'linear-gradient(135deg, #065f46, #10b981)',
      professional: 'linear-gradient(135deg, #1d4ed8, #3b82f6)',
      creative: 'linear-gradient(135deg, #ec4899, #f97316)',
      classic: 'linear-gradient(135deg, #1b2a4a, #374151)',
      technical: 'linear-gradient(135deg, #0f172a, #0f766e)',
      executive: 'linear-gradient(135deg, #1a1a2e, #16213e)',
    };
    // Template layout icons for card thumbnails
    const templateIcons = {
      modern: '⚙',
      minimal: '↔',
      ats: '⚑',
      professional: '✔',
      creative: '✨',
      classic: '⚐',
      technical: '⚙',
      executive: '♛',
    };
    html += '<div class="bld-template-grid">';
    for (let ti = 0; ti < templates.length; ti++) {
      const tmpl = templates[ti];
      const isSelected = STATE.selectedTemplate === tmpl;
      const label = templateLabels[tmpl] || (tmpl.charAt(0).toUpperCase() + tmpl.slice(1));
      const gradient = templateGradients[tmpl] || 'linear-gradient(135deg, #4f46e5, #7c3aed)';
      const icon = templateIcons[tmpl] || '⚙';
      const nameFirst = (STATE.data.personal && STATE.data.personal.full_name
        ? STATE.data.personal.full_name : 'Alex Morgan').charAt(0).toUpperCase() || 'A';
      html += `<div class="bld-template-card${ isSelected ? ' selected' : '' }" data-template="${ tmpl }">` +
        `<div class="bld-template-card-preview" style="background: ${ gradient };">` +
        `<div class="bld-template-card-badge">${ isSelected ? 'Default' : 'Template' }</div>` +
        `<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">` +
        `<span style="width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,0.2);display:inline-flex;align-items:center;justify-content:center;font-size:0.9rem;">${ icon }</span>` +
        `<div class="bld-template-card-name">${ label }</div></div>` +
        `<div class="bld-template-card-slug">${ tmpl }</div>` +
        `<div style="margin-top:0.5rem;font-size:0.65rem;opacity:0.6;">${ escHtml(nameFirst) }'s CV</div>` +
        '</div>' +
        '<div class="bld-template-card-actions">' +
        `<button type="button" class="bld-template-btn bld-template-btn-preview" data-action="open-template-preview" data-template="${ tmpl }">Preview</button>` +
        `<button type="button" class="bld-template-btn bld-template-btn-apply${ isSelected ? ' is-active' : '' }" data-action="select-template" data-template="${ tmpl }">${ isSelected ? 'Applied' : 'Apply as Default' }</button>` +
        '</div></div>';
    }
    html += '</div>';
    html += '<div class="bld-review-note">Click <strong>Preview</strong> on any template to inspect the full CV before applying it.</div>';
    html += '<div id="bld-download-wrap" class="bld-download-wrap" style="display:none;">' +
      '<a id="bld-download-link" href="#" class="bld-download-btn" target="_blank" rel="noopener">' +
      '<i class="lucide lucide-download" style="width:1em;height:1em;"></i> Download PDF' +
      '</a>' +
      '</div>';
    html += '<div id="bld-template-modal" class="bld-template-modal" aria-hidden="true" hidden>' +
      '<div class="bld-template-modal-backdrop" data-action="close-template-preview"></div>' +
      '<div class="bld-template-modal-panel" role="dialog" aria-modal="true" aria-labelledby="bld-template-modal-title">' +
        '<div class="bld-template-modal-head">' +
          '<div>' +
            '<div class="bld-template-modal-kicker">Template Preview</div>' +
            '<h3 id="bld-template-modal-title" class="bld-template-modal-title">Preview</h3>' +
          '</div>' +
          '<div style="display:flex;gap:0.5rem;align-items:center;">' +
            '<button type="button" class="bld-template-btn bld-template-btn-preview" data-action="download-preview-template" style="font-size:0.75rem;padding:0.4rem 0.8rem;" title="Download this template as PDF">Download</button>' +
            '<button type="button" class="bld-template-modal-close" data-action="close-template-preview" aria-label="Close preview"><i class="lucide lucide-x"></i></button>' +
          '</div>' +
        '</div>' +
        '<div class="bld-template-modal-body">' +
          '<div id="bld-preview-loading" class="bld-preview-loading">' +
            '<div class="bld-spinner"></div>' +
            '<span>Loading preview with your data...</span>' +
          '</div>' +
          '<iframe id="bld-preview-iframe" class="bld-preview-iframe" sandbox="allow-scripts allow-same-origin" title="CV Template Preview"></iframe>' +
          '<div id="bld-preview-mockup" class="bld-preview-mockup" aria-live="polite" style="display:none;"></div>' +
        '</div>' +
        '<div class="bld-template-modal-actions">' +
          '<button type="button" class="bld-template-btn bld-template-btn-secondary" data-action="close-template-preview">Close</button>' +
          '<button type="button" class="bld-template-btn bld-template-btn-primary" data-action="select-preview-template">Apply as Default</button>' +
          '<a id="bld-template-download-link" class="bld-template-btn bld-template-btn-download" href="#" target="_blank" rel="noopener" style="display:none;">Download PDF</a>' +
        '</div>' +
      '</div>' +
    '</div>' +
    '<div id="bld-toast" class="bld-toast" style="display:none;"></div>';
    return html;
  }

  function renderLanguageEntry(lang, idx) {
    const l = lang || {};
    const isEditing = STATE._editingSection === 'languages' && STATE._editingIndex === idx;
    const proficiencies = ['basic', 'intermediate', 'advanced', 'fluent', 'native',];
    let profOptions = '';
    for (let pi = 0; pi < proficiencies.length; pi++) {
      const p = proficiencies[pi];
      profOptions += `<option value="${ p }"${ l.proficiency === p ? ' selected' : '' }>${ p.charAt(0).toUpperCase() }${p.slice(1) }</option>`;
    }
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" data-action="move-entry" data-section="languages" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" data-action="move-entry" data-section="languages" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" data-action="remove-entry" data-section="languages" data-idx="${ idx }"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Language</label><input class="bld-input lang-name" value="${ escHtml(l.name || '') }" placeholder="e.g. English"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Proficiency</label><select class="bld-select lang-proficiency">${ profOptions }</select></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" data-action="done-editing"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" data-action="remove-entry" data-section="languages" data-idx="${ idx }">Remove</button></div></div>`;
    }
    const name = l.name || 'New Language';
    const prof = l.proficiency ? l.proficiency.charAt(0).toUpperCase() + l.proficiency.slice(1) : '';
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" data-action="move-entry" data-section="languages" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" data-action="move-entry" data-section="languages" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(name) }</div>${
        prof ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(prof) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" data-action="edit-entry" data-section="languages" data-idx="${ idx }">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" data-action="remove-entry" data-section="languages" data-idx="${ idx }"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
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
        `<button class="bld-entry-move-up" data-action="move-entry" data-section="social_links" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" data-action="move-entry" data-section="social_links" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" data-action="remove-entry" data-section="social_links" data-idx="${ idx }"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Platform</label><select class="bld-select link-platform">${ platOptions }</select></div>` +
        `<div class="bld-form-group"><label class="bld-label">URL</label><input class="bld-input link-url" value="${ escHtml(l.url || '') }" placeholder="https://..."></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" data-action="done-editing"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" data-action="remove-entry" data-section="social_links" data-idx="${ idx }">Remove</button></div></div>`;
    }
    const iconMap = { linkedin: 'linkedin', github: 'github', twitter: 'twitter', website: 'globe', facebook: 'facebook', instagram: 'instagram', youtube: 'youtube', dribbble: 'dribbble', behance: 'behance', other: 'link', };
    const plat = l.platform || 'other';
    const icon = iconMap[plat] || 'link';
    const url = l.url || '';
    const displayUrl = url.length > 40 ? `${url.slice(0, 40)}...` : url;
    const platLabel = plat.charAt(0).toUpperCase() + plat.slice(1);
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" data-action="move-entry" data-section="social_links" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" data-action="move-entry" data-section="social_links" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      '<div style="display:flex;align-items:center;gap:0.4rem;">' +
      `<i class="lucide lucide-${ icon }" style="width:1em;height:1em;color:#6366f1;flex-shrink:0;"></i>` +
      `<span style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(platLabel) }</span></div>${
        displayUrl ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;word-break:break-all;">${ escHtml(displayUrl) }</div>` : ''
      }</n` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" data-action="edit-entry" data-section="social_links" data-idx="${ idx }">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" data-action="remove-entry" data-section="social_links" data-idx="${ idx }"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderCustomSectionEntry(sec, idx) {
    const s = sec || {};
    const isEditing = STATE._editingSection === 'custom_sections' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" data-action="move-entry" data-section="custom_sections" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" data-action="move-entry" data-section="custom_sections" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" data-action="remove-entry" data-section="custom_sections" data-idx="${ idx }"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-form-group"><label class="bld-label">Section Title</label><input class="bld-input custom-title" value="${ escHtml(s.title || '') }" placeholder="e.g. Volunteer Work"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Content</label><textarea class="bld-textarea custom-content" style="min-height:80px;" placeholder="Describe this section...">${ escHtml(s.content || '') }</textarea></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" data-action="done-editing"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" data-action="remove-entry" data-section="custom_sections" data-idx="${ idx }">Remove</button></div></div>`;
    }
    const title = s.title || 'New Section';
    let preview = (s.content || '').slice(0, 100);
    if (preview.length >= 100) preview += '...';
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" data-action="move-entry" data-section="custom_sections" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" data-action="move-entry" data-section="custom_sections" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(title) }</div>${
        preview ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(preview) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" data-action="edit-entry" data-section="custom_sections" data-idx="${ idx }">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" data-action="remove-entry" data-section="custom_sections" data-idx="${ idx }"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderExperienceEntry(exp, idx) {
    const e = exp || {};
    const isEditing = STATE._editingSection === 'experience' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" data-action="move-entry" data-section="experience" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" data-action="move-entry" data-section="experience" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" data-action="remove-entry" data-section="experience" data-idx="${ idx }"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Company</label><input class="bld-input exp-company" value="${ escHtml(e.company || '') }" placeholder="Company name"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Position</label><input class="bld-input exp-position" value="${ escHtml(e.position || '') }" placeholder="Job title"></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Location</label><input class="bld-input exp-location" value="${ escHtml(e.location || '') }" placeholder="City"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Start Date</label><input class="bld-input exp-start_date" value="${ escHtml(e.start_date || '') }" placeholder="Jan 2020"></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">End Date</label><input class="bld-input exp-end_date" value="${ escHtml(e.end_date || '') }" placeholder="Present"></div>` +
        `<div class="bld-form-group"><label class="bld-checkbox"><input type="checkbox" class="exp-current" ${ e.is_current ? 'checked' : '' }> Currently here</label></div></div>` +
        `<div class="bld-form-group"><label class="bld-label">Responsibilities</label><textarea class="bld-textarea exp-responsibilities" style="min-height:80px;">${ escHtml(e.responsibilities || '') }</textarea></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" data-action="done-editing"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" data-action="remove-entry" data-section="experience" data-idx="${ idx }">Remove</button></div></div>`;
    }
    const title = [e.company, e.position,].filter(Boolean).join(' · ') || 'New Experience';
    const dateRange = [e.start_date, e.is_current ? 'Present' : e.end_date,].filter(Boolean).join(' - ');
    const loc = e.location || '';
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" data-action="move-entry" data-section="experience" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" data-action="move-entry" data-section="experience" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(title) }</div>${
        dateRange ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(dateRange) }${loc ? ` · ${ escHtml(loc)}` : '' }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" data-action="edit-entry" data-section="experience" data-idx="${ idx }">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" data-action="remove-entry" data-section="experience" data-idx="${ idx }"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderEducationEntry(edu, idx) {
    const e = edu || {};
    const isEditing = STATE._editingSection === 'education' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" data-action="move-entry" data-section="education" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" data-action="move-entry" data-section="education" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" data-action="remove-entry" data-section="education" data-idx="${ idx }"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Institution</label><input class="bld-input edu-institution" value="${ escHtml(e.institution || '') }" placeholder="University"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Degree</label><input class="bld-input edu-degree" value="${ escHtml(e.degree || '') }" placeholder="B.Sc."></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Field</label><input class="bld-input edu-field" value="${ escHtml(e.field || '') }" placeholder="Computer Science"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Year</label><input class="bld-input edu-year" value="${ escHtml(e.year || '') }" placeholder="2024"></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" data-action="done-editing"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" data-action="remove-entry" data-section="education" data-idx="${ idx }">Remove</button></div></div>`;
    }
    const title = [e.institution, e.degree,].filter(Boolean).join(' · ') || 'New Education';
    const detail = [e.field, e.year,].filter(Boolean).join(' | ');
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" data-action="move-entry" data-section="education" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" data-action="move-entry" data-section="education" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(title) }</div>${
        detail ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(detail) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" data-action="edit-entry" data-section="education" data-idx="${ idx }">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" data-action="remove-entry" data-section="education" data-idx="${ idx }"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderReferenceEntry(ref, idx) {
    const r = ref || {};
    const isEditing = STATE._editingSection === 'references' && STATE._editingIndex === idx;
    if (isEditing) {
      return `<div class="bld-entry-card" data-idx="${ idx }">` +
        '<div class="bld-entry-moves">' +
        `<button class="bld-entry-move-up" data-action="move-entry" data-section="references" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
        `<button class="bld-entry-move-down" data-action="move-entry" data-section="references" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
        `<button class="bld-entry-remove" data-action="remove-entry" data-section="references" data-idx="${ idx }"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Name</label><input class="bld-input ref-name" value="${ escHtml(r.name || '') }" placeholder="Reference name"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Title</label><input class="bld-input ref-title" value="${ escHtml(r.title || '') }" placeholder="e.g. Manager"></div></div>` +
        `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Email</label><input class="bld-input ref-email" value="${ escHtml(r.email || '') }" placeholder="email@example.com"></div>` +
        `<div class="bld-form-group"><label class="bld-label">Phone</label><input class="bld-input ref-phone" value="${ escHtml(r.phone || '') }" placeholder="+1 555-0000"></div></div>` +
        '<div style="display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e5e7eb;">' +
        '<button class="bld-btn-sm" style="background:#6366f1;color:white;border:none;border-radius:8px;padding:0.4rem 1rem;cursor:pointer;font-weight:600;font-size:0.8rem;" data-action="done-editing"><i class="lucide lucide-check" style="width:0.85em;height:0.85em;"></i> Done</button>' +
        `<button class="bld-btn-sm bld-btn-remove" style="padding:0.4rem 1rem;" data-action="remove-entry" data-section="references" data-idx="${ idx }">Remove</button></div></div>`;
    }
    const name = r.name || 'New Reference';
    const detail = [r.title, r.email,].filter(Boolean).join(' · ');
    return `<div class="bld-entry-card" draggable="true" data-idx="${ idx }">` +
      '<div class="bld-drag-handle" title="Drag to reorder"><i class="lucide lucide-grip-horizontal" style="width:1em;height:1em;"></i></div>' +
      '<div class="bld-entry-moves">' +
      `<button class="bld-entry-move-up" data-action="move-entry" data-section="references" data-idx="${ idx }" data-dir="up" title="Move up"><i class="lucide lucide-chevron-up" style="width:0.9em;height:0.9em;"></i></button>` +
      `<button class="bld-entry-move-down" data-action="move-entry" data-section="references" data-idx="${ idx }" data-dir="down" title="Move down"><i class="lucide lucide-chevron-down" style="width:0.9em;height:0.9em;"></i></button></div>` +
      '<div style="flex:1;min-width:0;">' +
      `<div style="font-weight:600;font-size:0.95rem;color:#1f2937;">${ escHtml(name) }</div>${
        detail ? `<div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem;">${ escHtml(detail) }</div>` : ''
      }</div>` +
      '<div style="display:flex;gap:0.35rem;flex-shrink:0;align-items:center;">' +
      `<button style="background:#6366f1;color:white;border:none;border-radius:6px;padding:0.3rem 0.7rem;cursor:pointer;font-weight:600;font-size:0.78rem;" data-action="edit-entry" data-section="references" data-idx="${ idx }">Edit</button>` +
      `<button style="background:transparent;color:#ef4444;border:1px solid #fecaca;border-radius:6px;padding:0.3rem 0.5rem;cursor:pointer;font-size:0.78rem;" data-action="remove-entry" data-section="references" data-idx="${ idx }"><i class="lucide lucide-trash-2" style="width:0.85em;height:0.85em;"></i></button></div></div>`;
  }

  function renderSkillTags(skills, category) {
    let html = '';
    for (let i = 0; i < skills.length; i++) {
      html += `<span class="bld-skill-tag" style="cursor:pointer;" data-action="edit-skill" data-category="${ category }" data-idx="${ i }" title="Click to edit">${ escHtml(skills[i]) }<button class="bld-skill-remove" data-action="remove-skill" data-category="${ category }" data-idx="${ i }"><i class="lucide lucide-x" style="width:0.8em;height:0.8em;"></i></button></span>`;
    }
    return html;
  }

  return {
    renderStepContent,
    renderSkillTags,
  };
}
