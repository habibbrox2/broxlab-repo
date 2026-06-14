(function () {
  'use strict';

  const STEPS = [
    { id: 'personal', title: 'Personal Information', icon: 'user', desc: 'Tell us about yourself', },
    { id: 'summary', title: 'Professional Summary', icon: 'file-text', desc: 'Write a compelling summary', },
    { id: 'experience', title: 'Work Experience', icon: 'briefcase', desc: 'Add your work history', },
    { id: 'education', title: 'Education', icon: 'graduation-cap', desc: 'Your academic background', },
    { id: 'skills', title: 'Skills', icon: 'zap', desc: 'Technical and soft skills', },
    { id: 'languages', title: 'Languages', icon: 'globe', desc: 'Languages you speak', },
    { id: 'certificates', title: 'Certifications', icon: 'award', desc: 'Professional certifications', },
    { id: 'projects', title: 'Projects', icon: 'folder', desc: 'Notable projects you worked on', },
    { id: 'social_links', title: 'Social Links', icon: 'share-2', desc: 'Link your profiles', },
    { id: 'custom_sections', title: 'Custom Sections', icon: 'layout', desc: 'Add extra sections', },
    { id: 'references', title: 'References', icon: 'users', desc: 'Optional references', },
    { id: 'review', title: 'Review & Template', icon: 'check-circle', desc: 'Review and choose a template', },
  ];

  const STATE = {
    currentStep: 0,
    data: {},
    selectedTemplate: 'modern',
    cvId: 0,
    csrf: '',
    isSaving: false,
    saveTimer: null,
    jobData: null,
  };

  function init() {
    STATE.cvId = window.__bldCvId || 0;
    STATE.csrf = window.__bldCsrf || '';
    STATE.selectedTemplate = window.__bldSelectedTemplate || 'modern';
    STATE.data = window.__bldData && typeof window.__bldData === 'object' ? window.__bldData : {};
    STATE.jobData = window.__bldJobPositions || null;
    if (!STATE.cvId) return;
    // Load personal info from the structured cv_personal_info table
    loadPersonalInfo().then(function () {
      renderStep(0);
      updateProgress();
      setupAutoSave();
      const jobSlug = STATE.data.summary && STATE.data.summary.job_title ? STATE.data.summary.job_title : '';
      if (jobSlug) {
        const sel = document.getElementById('bld-job-title');
        if (sel) sel.value = jobSlug;
      }
    });
  }

  function loadPersonalInfo() {
    return fetch('/api/cv/' + STATE.cvId + '/personal-info', {
      headers: { 'X-CSRF-Token': STATE.csrf }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success && res.data) {
          // Merge personal info from structured table into builder_data
          if (!STATE.data.personal) STATE.data.personal = {};
          for (var key in res.data) {
            if (res.data.hasOwnProperty(key) && res.data[key]) {
              STATE.data.personal[key] = res.data[key];
            }
          }
        }
      })
      .catch(function () {});
  }

  function renderStep(index) {
    const step = STEPS[index];
    if (!step) return;
    STATE.currentStep = index;
    let html = '<div class="bld-step-header">' +
      `<div class="bld-step-number"><i class="lucide lucide-${ step.icon }" style="width:1em;height:1em;"></i> Step ${ index + 1 } of ${ STEPS.length }</div>` +
      `<h2 class="bld-step-title">${ step.title }</h2>` +
      `<p class="bld-step-desc">${ step.desc }</p></div>`;
    html += renderStepContent(index);
    const container = document.getElementById('bld-step-content');
    if (container) container.innerHTML = html;
    updateProgress();
    const nextBtn = document.getElementById('bld-btn-next');
    if (nextBtn) {
      if (index === STEPS.length - 1) {
        nextBtn.innerHTML = '<i class="lucide lucide-check-circle" style="width:1em;height:1em;"></i> Finish and Save';
        nextBtn.className = 'bld-btn bld-btn-finish';
      } else {
        nextBtn.innerHTML = 'Next <i class="lucide lucide-chevron-right" style="width:1em;height:1em;"></i>';
        nextBtn.className = 'bld-btn bld-btn-next';
      }
    }
    scrollToBuilderTop();
  }

  function scrollToBuilderTop() {
    const container = document.querySelector('.bld-container');
    if (!container) return;
    if (window.scrollY <= 0) return;
    const headerOffset = 80;
    const rect = container.getBoundingClientRect();
    if (rect.top < headerOffset) {
      const top = window.scrollY + rect.top - headerOffset;
      window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }
  }

  function renderStepContent(index) {
    const stepId = STEPS[index].id;
    const d = STATE.data[stepId] || {};
    const fill = function (key, def) { return d && d[key] !== undefined && d[key] !== null ? escHtml(String(d[key])) : def || ''; };

    switch (stepId) {
    case 'personal':
      return '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Full Name *</label><input class="bld-input" id="bld-field-full_name" value="${ fill('full_name') }" placeholder="e.g. John Doe"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Job Title *</label><input class="bld-input" id="bld-field-job_title" value="${ fill('job_title') }" placeholder="e.g. Software Engineer"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Email</label><input class="bld-input" id="bld-field-email" type="email" value="${ fill('email') }" placeholder="john@example.com"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Phone</label><input class="bld-input" id="bld-field-phone" type="tel" value="${ fill('phone') }" placeholder="+1 555-0000"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Date of Birth</label><input class="bld-input" id="bld-field-dob" type="date" value="${ fill('date_of_birth') }"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Nationality</label><input class="bld-input" id="bld-field-nationality" value="${ fill('nationality') }" placeholder="e.g. American"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Gender</label><select class="bld-select" id="bld-field-gender"><option value="">Select...</option><option value="male"${ d.gender === 'male' ? ' selected' : '' }>Male</option><option value="female"${ d.gender === 'female' ? ' selected' : '' }>Female</option><option value="other"${ d.gender === 'other' ? ' selected' : '' }>Other</option></select></div>` +
          `<div class="bld-form-group"><label class="bld-label">Address</label><input class="bld-input" id="bld-field-address" value="${ fill('address') }" placeholder="City, Country"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Website / Portfolio</label><input class="bld-input" id="bld-field-website" value="${ fill('website') }" placeholder="https://yoursite.com"></div>` +
          `<div class="bld-form-group"><label class="bld-label">LinkedIn</label><input class="bld-input" id="bld-field-linkedin" value="${ fill('linkedin') }" placeholder="linkedin.com/in/yourprofile"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">GitHub</label><input class="bld-input" id="bld-field-github" value="${ fill('github') }" placeholder="github.com/yourusername"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Twitter / X</label><input class="bld-input" id="bld-field-twitter" value="${ fill('twitter') }" placeholder="twitter.com/yourhandle"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">National ID No.</label><input class="bld-input" id="bld-field-national_id_no" value="${ fill('national_id_no') }" placeholder="e.g. Aadhaar, SSN, NID"></div>` +
          `<div class="bld-form-group"><label class="bld-label">Passport No.</label><input class="bld-input" id="bld-field-passport_no" value="${ fill('passport_no') }" placeholder="e.g. AB123456"></div></div>` +
          '<div class="bld-input-group">' +
          `<div class="bld-form-group"><label class="bld-label">Birth Certificate No.</label><input class="bld-input" id="bld-field-birth_certificate_no" value="${ fill('birth_certificate_no') }" placeholder="Birth certificate number"></div>` +
          '<div class="bld-form-group"></div></div>';

    case 'summary': {
      let s = '';
      if (STATE.data.summary && STATE.data.summary._suggestions) {
        const suggs = STATE.data.summary._suggestions;
        for (let si = 0; si < suggs.length; si++) {
          s += `<div class="bld-suggest-box" data-suggest-idx="${ si }">`;
          s += `<h4>${ escHtml(suggs[si].type === 'summary' ? 'Suggested Professional Summary' : 'Suggested Career Objective') }</h4>`;
          s += `<div class="bld-suggest-text">${ escHtml(suggs[si].content) }</div>`;
          s += '<div class="bld-suggest-actions">';
          s += `<button class="bld-btn-sm bld-btn-accept" onclick="window.bldAcceptSuggestion(${ si })">Accept</button>`;
          s += `<button class="bld-btn-sm bld-btn-edit" onclick="window.bldEditSuggestion(${ si })">Edit</button>`;
          s += `<button class="bld-btn-sm bld-btn-remove" onclick="window.bldRemoveSuggestion(${ si })">Remove</button>`;
          s += '</div></div>';
        }
      }
      return `${s
      }<div class="bld-form-group"><label class="bld-label">Professional Summary</label><textarea class="bld-textarea" id="bld-field-professional_summary" placeholder="Write a brief professional summary...">${ fill('professional_summary') }</textarea></div>` +
          `<div class="bld-form-group"><label class="bld-label">Career Objective</label><textarea class="bld-textarea" id="bld-field-career_objective" placeholder="What are your career aspirations?\">${ fill('career_objective') }</textarea></div>`;
    }

    case 'experience': {
      const exps = Array.isArray(d) ? d : [];
      let expHtml = '';
      for (let ei = 0; ei < exps.length; ei++) {
        expHtml += renderExperienceEntry(exps[ei], ei);
      }
      return `<div id="bld-experience-list">${ expHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddExperience()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Experience</button>';
    }

    case 'education': {
      const eds = Array.isArray(d) ? d : [];
      let eduHtml = '';
      for (let edi = 0; edi < eds.length; edi++) {
        eduHtml += renderEducationEntry(eds[edi], edi);
      }
      return `<div id="bld-education-list">${ eduHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddEducation()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Education</button>';
    }

    case 'skills': {
      const sk = d || {};
      const technical = Array.isArray(sk.technical) ? sk.technical : [];
      const soft = Array.isArray(sk.soft) ? sk.soft : [];
      return '<div class="bld-form-group"><label class="bld-label">Technical Skills</label>' +
          `<div class="bld-skills-area" id="bld-skills-technical">${ renderSkillTags(technical, 'technical') }</div>` +
          '<input class="bld-skill-input" id="bld-skill-input-technical" placeholder="Type and press Enter..." onkeydown="window.bldAddSkill(event)"></div>' +
          '<div class="bld-form-group"><label class="bld-label">Soft Skills</label>' +
          `<div class="bld-skills-area" id="bld-skills-soft">${ renderSkillTags(soft, 'soft') }</div>` +
          '<input class="bld-skill-input" id="bld-skill-input-soft" placeholder="Type and press Enter..." onkeydown="window.bldAddSkill(event)"></div>';
    }

    case 'languages': {
      const langs = Array.isArray(d) ? d : [];
      let langHtml = '';
      for (let li = 0; li < langs.length; li++) {
        langHtml += renderLanguageEntry(langs[li], li);
      }
      return '<p style="color: var(--bld-gray-400); font-size: 0.85rem; margin-bottom: 1rem;">Add languages you speak and your proficiency level.</p>' +
          `<div id="bld-languages-list">${ langHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddLanguage()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Language</button>';
    }

    case 'certificates': {
      const certs = Array.isArray(d) ? d : [];
      let certHtml = '';
      for (let ci = 0; ci < certs.length; ci++) {
        certHtml += renderCertificateEntry(certs[ci], ci);
      }
      return `<div id="bld-certificates-list">${ certHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddCertificate()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Certification</button>';
    }

    case 'projects': {
      const projs = Array.isArray(d) ? d : [];
      let projHtml = '';
      for (let pi = 0; pi < projs.length; pi++) {
        projHtml += renderProjectEntry(projs[pi], pi);
      }
      return `<div id="bld-projects-list">${ projHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddProject()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Project</button>';
    }

    case 'social_links': {
      const links = Array.isArray(d) ? d : [];
      let linkHtml = '';
      for (let li = 0; li < links.length; li++) {
        linkHtml += renderSocialLinkEntry(links[li], li);
      }
      return '<p style="color: var(--bld-gray-400); font-size: 0.85rem; margin-bottom: 1rem;">Add links to your professional profiles.</p>' +
          `<div id="bld-social-links-list">${ linkHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddSocialLink()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Social Link</button>';
    }

    case 'custom_sections': {
      const secs = Array.isArray(d) ? d : [];
      let secHtml = '';
      for (let si = 0; si < secs.length; si++) {
        secHtml += renderCustomSectionEntry(secs[si], si);
      }
      return '<p style="color: var(--bld-gray-400); font-size: 0.85rem; margin-bottom: 1rem;">Add custom sections like Volunteer Work, Publications, Hobbies, etc.</p>' +
          `<div id="bld-custom-sections-list">${ secHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddCustomSection()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Custom Section</button>';
    }

    case 'references': {
      const refs = Array.isArray(d) ? d : [];
      let refHtml = '';
      for (let ri = 0; ri < refs.length; ri++) {
        refHtml += renderReferenceEntry(refs[ri], ri);
      }
      return '<p style="color: var(--bld-gray-400); font-size: 0.85rem; margin-bottom: 1rem;">References are optional. You can skip this step.</p>' +
          `<div id="bld-references-list">${ refHtml }</div>` +
          '<button class="bld-add-entry" onclick="window.bldAddReference()"><i class="lucide lucide-plus-circle" style="width:1em;height:1em;"></i> Add Reference</button>';
    }

    case 'review': {
      return renderReviewStep();
    }

    default:
      return '<p>Step not available.</p>';
    }
  }

  function renderReviewStep() {
    let html = '<div class="bld-step-header">' +
      '<div class="bld-step-number"><i class="lucide lucide-list-checks" style="width:1em;height:1em;"></i> Final Step</div>' +
      '<h2 class="bld-step-title">Review &amp; Choose Template</h2>' +
      '<p class="bld-step-desc">Review your CV summary and select a template style before finalizing.</p></div>';

    // Section summary
    html += '<div style="margin-bottom: 1.5rem;">';
    html += '<h4 style="font-size:1rem;font-weight:600;color:var(--bld-gray-700);margin-bottom:0.75rem;">CV Summary</h4>';
    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">';
    for (let si = 0; si < STEPS.length - 1; si++) {
      const step = STEPS[si];
      const data = STATE.data[step.id];
      const hasData = data && (
        (typeof data === 'object' && !Array.isArray(data) && Object.keys(data).length > 0 && Object.values(data).some(v => v && (typeof v !== 'object' || (Array.isArray(v) && v.length > 0)))) ||
        (Array.isArray(data) && data.length > 0)
      );
      html += `<div style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;background:${ hasData ? 'var(--bld-primary-subtle)' : 'var(--bld-gray-50)' };border-radius:8px;border:1px solid ${ hasData ? '#c7d2fe' : 'var(--bld-gray-200)' };">`;
      html += `<i class="lucide lucide-${ step.icon }" style="width:1em;height:1em;color:${ hasData ? 'var(--bld-primary)' : 'var(--bld-gray-400)' };flex-shrink:0;"></i>`;
      html += `<span style="flex:1;font-size:0.85rem;font-weight:500;color:${ hasData ? 'var(--bld-gray-800)' : 'var(--bld-gray-400)' };">${ step.title }</span>`;
      html += hasData ? '<span style="color:var(--bld-success);font-size:0.75rem;">✓ Done</span>' : '<span style="color:var(--bld-gray-400);font-size:0.75rem;">—</span>';
      html += '</div>';
    }
    html += '</div></div>';

    // Template selection
    html += '<h4 style="font-size:1rem;font-weight:600;color:var(--bld-gray-700);margin-bottom:0.75rem;">Choose Template</h4>';
    html += '<div class="bld-template-grid">';
    const templates = window.__bldTemplates || ['modern', 'minimal', 'ats', 'professional'];
    for (let ti = 0; ti < templates.length; ti++) {
      const tmpl = templates[ti];
      const isSelected = STATE.selectedTemplate === tmpl;
      const gradient = tmpl === 'modern' ? '#6366f1,#8b5cf6' : tmpl === 'minimal' ? '#374151,#6b7280' : tmpl === 'ats' ? '#059669,#10b981' : '#1e40af,#3b82f6';
      const icon = tmpl === 'modern' ? 'palette' : tmpl === 'minimal' ? 'minus' : tmpl === 'ats' ? 'bot' : 'briefcase';
      html += `<div class="bld-template-card${ isSelected ? ' selected' : '' }" data-template="${ tmpl }" onclick="window.bldSelectTemplate('${ tmpl }')" style="cursor:pointer;">`;
      html += `<div class="bld-template-icon" style="background:linear-gradient(135deg,${ gradient });">`;
      html += `<i class="lucide lucide-${ icon }" style="width:1em;height:1em;"></i></div>`;
      html += `<div class="bld-template-name">${ tmpl.charAt(0).toUpperCase() + tmpl.slice(1) }</div></div>`;
    }
    html += '</div>';

    // Marketplace link
    html += '<div style="margin-top:1rem;text-align:center;">';
    html += '<a href="/cv-builder/templates" style="display:inline-flex;align-items:center;gap:0.5rem;color:#6366f1;font-size:0.875rem;font-weight:600;text-decoration:none;padding:0.5rem 1rem;border-radius:8px;transition:all 0.2s;" onmouseover="this.style.background=\'#eef2ff\'" onmouseout="this.style.background=\'transparent\'">';
    html += '<i class="lucide lucide-external-link" style="width:1em;height:1em;"></i>';
    html += 'Browse Full Template Marketplace';
    html += '</a></div>';

    return html;
  }

  function renderLanguageEntry(lang, idx) {
    const l = lang || {};
    const proficiencies = ['basic', 'intermediate', 'advanced', 'fluent', 'native'];
    let profOptions = '';
    for (let pi = 0; pi < proficiencies.length; pi++) {
      const p = proficiencies[pi];
      profOptions += `<option value="${ p }"${ l.proficiency === p ? ' selected' : '' }>${ p.charAt(0).toUpperCase() + p.slice(1) }</option>`;
    }
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('languages',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Language</label><input class="bld-input lang-name" value="${ escHtml(l.name || '') }" placeholder="e.g. English"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Proficiency</label><select class="bld-select lang-proficiency">${ profOptions }</select></div></div></div>`;
  }

  function renderSocialLinkEntry(link, idx) {
    const l = link || {};
    const platforms = ['linkedin', 'github', 'twitter', 'website', 'facebook', 'instagram', 'youtube', 'dribbble', 'behance', 'other'];
    let platOptions = '';
    for (let pi = 0; pi < platforms.length; pi++) {
      const p = platforms[pi];
      platOptions += `<option value="${ p }"${ l.platform === p ? ' selected' : '' }>${ p.charAt(0).toUpperCase() + p.slice(1) }</option>`;
    }
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('social_links',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Platform</label><select class="bld-select link-platform">${ platOptions }</select></div>` +
      `<div class="bld-form-group"><label class="bld-label">URL</label><input class="bld-input link-url" value="${ escHtml(l.url || '') }" placeholder="https://..."></div></div></div>`;
  }

  function renderCustomSectionEntry(sec, idx) {
    const s = sec || {};
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('custom_sections',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-form-group"><label class="bld-label">Section Title</label><input class="bld-input custom-title" value="${ escHtml(s.title || '') }" placeholder="e.g. Volunteer Work"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Content</label><textarea class="bld-textarea custom-content" style="min-height:80px;" placeholder="Describe this section...">${ escHtml(s.content || '') }</textarea></div></div>`;
  }

  function renderExperienceEntry(exp, idx) {
    const e = exp || {};
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('experience',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Company</label><input class="bld-input exp-company" value="${ escHtml(e.company || '') }" placeholder="Company name"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Position</label><input class="bld-input exp-position" value="${ escHtml(e.position || '') }" placeholder="Job title"></div></div>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Location</label><input class="bld-input exp-location" value="${ escHtml(e.location || '') }" placeholder="City"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Start Date</label><input class="bld-input exp-start_date" value="${ escHtml(e.start_date || '') }" placeholder="Jan 2020"></div></div>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">End Date</label><input class="bld-input exp-end_date" value="${ escHtml(e.end_date || '') }" placeholder="Present"></div>` +
      `<div class="bld-form-group"><label class="bld-checkbox"><input type="checkbox" class="exp-current" ${ e.is_current ? 'checked' : '' }> Currently here</label></div></div>` +
      `<div class="bld-form-group"><label class="bld-label">Responsibilities</label><textarea class="bld-textarea exp-responsibilities" style="min-height:80px;">${ escHtml(e.responsibilities || '') }</textarea></div></div>`;
  }

  function renderEducationEntry(edu, idx) {
    const e = edu || {};
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('education',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Institution</label><input class="bld-input edu-institution" value="${ escHtml(e.institution || '') }" placeholder="University"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Degree</label><input class="bld-input edu-degree" value="${ escHtml(e.degree || '') }" placeholder="B.Sc."></div></div>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Field</label><input class="bld-input edu-field" value="${ escHtml(e.field || '') }" placeholder="Computer Science"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Year</label><input class="bld-input edu-year" value="${ escHtml(e.year || '') }" placeholder="2024"></div></div></div>`;
  }

  function renderProjectEntry(proj, idx) {
    const p = proj || {};
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('projects',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Name</label><input class="bld-input proj-name" value="${ escHtml(p.name || '') }" placeholder="Project name"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Technologies</label><input class="bld-input proj-technologies" value="${ escHtml(p.technologies || '') }" placeholder="React, Node.js"></div></div>` +
      `<div class="bld-form-group"><label class="bld-label">Description</label><textarea class="bld-textarea proj-description" style="min-height:80px;">${ escHtml(p.description || '') }</textarea></div>` +
      `<div class="bld-form-group"><label class="bld-label">URL</label><input class="bld-input proj-url" value="${ escHtml(p.url || '') }" placeholder="https://github.com/..."></div></div>`;
  }

  function renderCertificateEntry(cert, idx) {
    const c = cert || {};
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('certificates',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Name</label><input class="bld-input cert-name" value="${ escHtml(c.name || '') }" placeholder="Certificate name"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Organization</label><input class="bld-input cert-organization" value="${ escHtml(c.organization || '') }" placeholder="Issuing org"></div></div>` +
      `<div class="bld-form-group"><label class="bld-label">Date</label><input class="bld-input cert-date" value="${ escHtml(c.date || '') }" placeholder="June 2024"></div></div>`;
  }

  function renderReferenceEntry(ref, idx) {
    const r = ref || {};
    return `<div class="bld-entry-card" data-idx="${ idx }">` +
      `<button class="bld-entry-remove" onclick="window.bldRemoveEntry('references',${ idx })"><i class="lucide lucide-x" style="width:1em;height:1em;"></i></button>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Name</label><input class="bld-input ref-name" value="${ escHtml(r.name || '') }" placeholder="Reference name"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Title</label><input class="bld-input ref-title" value="${ escHtml(r.title || '') }" placeholder="Manager"></div></div>` +
      `<div class="bld-input-group"><div class="bld-form-group"><label class="bld-label">Email</label><input class="bld-input ref-email" value="${ escHtml(r.email || '') }" placeholder="email@example.com"></div>` +
      `<div class="bld-form-group"><label class="bld-label">Phone</label><input class="bld-input ref-phone" value="${ escHtml(r.phone || '') }" placeholder="+1 555-0000"></div></div></div>`;
  }

  function renderSkillTags(skills, category) {
    let html = '';
    for (let i = 0; i < skills.length; i++) {
      html += `<span class="bld-skill-tag">${ escHtml(skills[i])
      } <button class="bld-skill-remove" onclick="window.bldRemoveSkill('${ category }',${ i })\"><i class=\"lucide lucide-x\" style=\"width:0.8em;height:0.8em;\"></i></button></span>`;
    }
    return html;
  }

  function collectStepData(stepId) {
    switch (stepId) {
    case 'personal':
      return { full_name: val('bld-field-full_name'), job_title: val('bld-field-job_title'), email: val('bld-field-email'), phone: val('bld-field-phone'), date_of_birth: val('bld-field-dob'), nationality: val('bld-field-nationality'), gender: val('bld-field-gender'), address: val('bld-field-address'), website: val('bld-field-website'), linkedin: val('bld-field-linkedin'), github: val('bld-field-github'), twitter: val('bld-field-twitter'), national_id_no: val('bld-field-national_id_no'), passport_no: val('bld-field-passport_no'), birth_certificate_no: val('bld-field-birth_certificate_no'), };
    case 'summary':
      return { professional_summary: val('bld-field-professional_summary'), career_objective: val('bld-field-career_objective'), job_title: (document.getElementById('bld-job-title') || {}).value || '', };
    case 'experience': {
      const exps = [];
      const cards = document.querySelectorAll('#bld-experience-list .bld-entry-card');
      for (let ei = 0; ei < cards.length; ei++) {
        exps.push({ company: qval(cards[ei], '.exp-company'), position: qval(cards[ei], '.exp-position'), location: qval(cards[ei], '.exp-location'), start_date: qval(cards[ei], '.exp-start_date'), end_date: qval(cards[ei], '.exp-end_date'), is_current: qchecked(cards[ei], '.exp-current'), responsibilities: qval(cards[ei], '.exp-responsibilities'), });
      }
      return exps;
    }
    case 'education': {
      const eds = [];
      const eduCards = document.querySelectorAll('#bld-education-list .bld-entry-card');
      for (let edi = 0; edi < eduCards.length; edi++) {
        eds.push({ institution: qval(eduCards[edi], '.edu-institution'), degree: qval(eduCards[edi], '.edu-degree'), field: qval(eduCards[edi], '.edu-field'), year: qval(eduCards[edi], '.edu-year'), });
      }
      return eds;
    }
    case 'skills':
      return { technical: collectSkills('technical'), soft: collectSkills('soft'), };
    case 'languages': {
      const langs = [];
      const langCards = document.querySelectorAll('#bld-languages-list .bld-entry-card');
      for (let li = 0; li < langCards.length; li++) {
        langs.push({ name: qval(langCards[li], '.lang-name'), proficiency: qval(langCards[li], '.lang-proficiency'), });
      }
      return langs;
    }
    case 'certificates': {
      const certs = [];
      const certCards = document.querySelectorAll('#bld-certificates-list .bld-entry-card');
      for (let ci = 0; ci < certCards.length; ci++) {
        certs.push({ name: qval(certCards[ci], '.cert-name'), organization: qval(certCards[ci], '.cert-organization'), date: qval(certCards[ci], '.cert-date'), });
      }
      return certs;
    }
    case 'projects': {
      const projs = [];
      const projCards = document.querySelectorAll('#bld-projects-list .bld-entry-card');
      for (let pi = 0; pi < projCards.length; pi++) {
        projs.push({ name: qval(projCards[pi], '.proj-name'), description: qval(projCards[pi], '.proj-description'), technologies: qval(projCards[pi], '.proj-technologies'), url: qval(projCards[pi], '.proj-url'), });
      }
      return projs;
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
      }, 5000);
    });
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
    fetch(`/api/cv/builder/${ STATE.cvId }/step`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf, },
      body: JSON.stringify(payload),
    })
      .then((r) => { return r.json(); })
      .then((res) => {
        if (!res.success) return;
        // If on personal step, also save to the structured cv_personal_info table
        if (stepId === 'personal') {
          savePersonalInfo(stepData, silent);
        } else if (!silent) {
          showAutoSaveIndicator();
        }
      })
      .catch(() => {})
      .finally(() => { STATE.isSaving = false; });
  }

  function savePersonalInfo(data, silent) {
    fetch('/api/cv/' + STATE.cvId + '/personal-info', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf },
      body: JSON.stringify(data),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success && !silent) showAutoSaveIndicator();
      })
      .catch(function () {});
  }

  function completeBuilder() {
    if (STATE.isSaving) return;
    STATE.isSaving = true;
    // Collect all remaining step data before finalizing
    for (let si = 0; si < STEPS.length; si++) {
      const sid = STEPS[si].id;
      if (sid !== 'review' && !STATE.data[sid]) {
        STATE.data[sid] = sid === 'skills' ? { technical: [], soft: [] } : [];
      }
    }
    STATE.data._template = STATE.selectedTemplate;
    // Save personal info to the structured table before completing
    if (STATE.data.personal) {
      savePersonalInfo(STATE.data.personal, true);
    }
    const nextBtn = document.getElementById('bld-btn-next');
    if (nextBtn) { nextBtn.disabled = true; nextBtn.innerHTML = '<i class="lucide lucide-hourglass" style="width:1em;height:1em;"></i> Finalizing...'; }
    fetch(`/api/cv/builder/${ STATE.cvId }/complete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': STATE.csrf, },
      body: JSON.stringify({ all_data: STATE.data, template: STATE.selectedTemplate, }),
    })
      .then((r) => { return r.json(); })
      .then((res) => {
        if (res.success) { window.location.href = res.redirect || `/cv/${ STATE.cvId}`; }
        else { window.showMessage(res.error || 'Failed to complete', 'danger'); if (nextBtn) { nextBtn.disabled = false; nextBtn.innerHTML = 'Finish and Save'; } }
      })
      .catch(() => { window.showMessage('An error occurred', 'danger'); if (nextBtn) { nextBtn.disabled = false; nextBtn.innerHTML = 'Finish and Save'; } })
      .finally(() => { STATE.isSaving = false; });
  }

  window.bldSelectJob = function (slug) {
    if (!slug) return;
    if (!STATE.data.summary) STATE.data.summary = {};
    STATE.data.summary.job_title = slug;
    fetch(`/api/job-positions/slug/${ slug}`)
      .then((r) => { return r.json(); })
      .then((res) => {
        if (res.success && res.position) {
          const pos = res.position;
          const suggestions = [];
          if (pos.summaries) {
            for (let si = 0; si < pos.summaries.length; si++) {
              suggestions.push({ type: pos.summaries[si].type || 'summary', content: pos.summaries[si].content, });
            }
          }
          if (!STATE.data.summary) STATE.data.summary = {};
          STATE.data.summary._suggestions = suggestions;
          if (STEPS[STATE.currentStep].id === 'summary' || STEPS[STATE.currentStep].id === 'personal') {
            renderStep(STATE.currentStep);
          }
        }
      })
      .catch(() => {});
  };

  window.bldAcceptSuggestion = function (idx) {
    const suggs = STATE.data.summary && STATE.data.summary._suggestions;
    if (!suggs || !suggs[idx]) return;
    const s = suggs[idx];
    const target = s.type === 'summary' || s.type === 'professional_summary' ? 'bld-field-professional_summary' : 'bld-field-career_objective';
    const el = document.getElementById(target);
    if (el) el.value = s.content;
    suggs.splice(idx, 1);
    renderStep(STATE.currentStep);
  };

  window.bldEditSuggestion = window.bldAcceptSuggestion;

  window.bldRemoveSuggestion = function (idx) {
    const suggs = STATE.data.summary && STATE.data.summary._suggestions;
    if (!suggs) return;
    suggs.splice(idx, 1);
    renderStep(STATE.currentStep);
  };

  window.bldAddExperience = function () { addEntryTo('bld-experience-list', renderExperienceEntry({}, 0)); };
  window.bldAddEducation = function () { addEntryTo('bld-education-list', renderEducationEntry({}, 0)); };
  window.bldAddLanguage = function () { addEntryTo('bld-languages-list', renderLanguageEntry({}, 0)); };
  window.bldAddProject = function () { addEntryTo('bld-projects-list', renderProjectEntry({}, 0)); };
  window.bldAddCertificate = function () { addEntryTo('bld-certificates-list', renderCertificateEntry({}, 0)); };
  window.bldAddSocialLink = function () { addEntryTo('bld-social-links-list', renderSocialLinkEntry({}, 0)); };
  window.bldAddCustomSection = function () { addEntryTo('bld-custom-sections-list', renderCustomSectionEntry({}, 0)); };
  window.bldAddReference = function () { addEntryTo('bld-references-list', renderReferenceEntry({}, 0)); };

  function addEntryTo(listId, tpl) {
    const list = document.getElementById(listId);
    if (!list) return;
    list.insertAdjacentHTML('beforeend', tpl);
  }

  window.bldRemoveEntry = function (step, idx) {
    const stepId = STEPS[STATE.currentStep].id;
    const entries = STATE.data[stepId] || [];
    entries.splice(idx, 1);
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
    const area = document.getElementById(`bld-skills-${ category}`);
    if (area) area.innerHTML = renderSkillTags(current, category);
    input.value = '';
    input.focus();
  };

  window.bldRemoveSkill = function (category, idx) {
    const current = collectSkills(category);
    current.splice(idx, 1);
    STATE.data.skills = STATE.data.skills || {};
    STATE.data.skills[category] = current;
    const area = document.getElementById(`bld-skills-${ category}`);
    if (area) area.innerHTML = renderSkillTags(current, category);
  };

  function collectSkills(category) {
    if (STATE.data.skills && Array.isArray(STATE.data.skills[category])) {
      return STATE.data.skills[category].slice();
    }
    const tags = document.querySelectorAll(`#bld-skills-${ category } .bld-skill-tag`);
    const arr = [];
    for (let i = 0; i < tags.length; i++) {
      const text = tags[i].textContent.replace(/\s*\u00d7\s*/, '').replace(/\s*×\s*/, '').trim();
      if (text) arr.push(text);
    }
    return arr;
  }

  window.bldSelectTemplate = function (tmpl) {
    STATE.selectedTemplate = tmpl;
    const cards = document.querySelectorAll('.bld-template-card');
    for (let i = 0; i < cards.length; i++) {
      cards[i].classList.toggle('selected', cards[i].getAttribute('data-template') === tmpl);
    }
  };

  function updateProgress() {
    const pct = Math.round((STATE.currentStep / (STEPS.length - 1)) * 100);
    const pctEl = document.getElementById('bld-progress-pct');
    const fillEl = document.getElementById('bld-progress-fill');
    const stepEl = document.getElementById('bld-current-step');
    const dots = document.querySelectorAll('#bld-step-dots .bld-step-dot');
    if (pctEl) pctEl.textContent = String(pct);
    if (fillEl) fillEl.style.width = `${pct }%`;
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
    activeDot.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
  }

  function val(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }
  function qval(parent, selector) { const el = parent.querySelector(selector); return el ? el.value.trim() : ''; }
  function qchecked(parent, selector) { const el = parent.querySelector(selector); return el ? el.checked : false; }

  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  // Keyboard navigation
  document.addEventListener('keydown', function (e) {
    if (!e.target || !e.target.closest('.bld-card')) return;
    const isAdvanceKey = (e.shiftKey || e.altKey) && e.key === 'Enter';
    if (!isAdvanceKey) return;
    const textarea = e.target.closest('textarea');
    if (textarea) { e.preventDefault(); window.bldNextStep(); return; }
    if (e.target.matches('input:not([type=\"checkbox\"]):not([type=\"radio\"])')) { e.preventDefault(); window.bldNextStep(); }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
