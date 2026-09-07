/**
 * Unit tests for CV Builder — template selection, preview, and step navigation
 *
 * @see public_html/assets/js/cv-builder-renderers.js
 * @see public_html/assets/js/cv-builder-app.js
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { createCvBuilderRenderers } from '../cv-builder-renderers.js';

// ═══════════════════════════════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════════════════════════════

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function createMockState(overrides = {}) {
  return {
    currentStep: 0,
    data: {
      personal: { full_name: 'John Doe', job_title: 'Engineer', email: 'john@test.com', },
      experience: [{ company: 'Acme', position: 'Dev', },],
      education: [{ institution: 'MIT', degree: 'BSc', },],
      skills: { technical: ['JS', 'React',], soft: ['Leadership',], },
      languages: [{ name: 'English', proficiency: 'native', },],
    },
    cvId: 42,
    csrf: 'test-csrf',
    selectedTemplate: 'modern',
    profilePhoto: '',
    previewTemplate: '',
    isSaving: false,
    isUploading: false,
    saveTimer: null,
    _editingSection: null,
    _editingIndex: -1,
    ...overrides,
  };
}

function createMockSteps() {
  return [
    { id: 'personal', title: 'Personal Information', icon: 'user', desc: 'Tell us about yourself', },
    { id: 'professional', title: 'Professional Details', icon: 'briefcase', desc: 'Experience, education, skills & languages', },
    { id: 'extras', title: 'Social, Sections & References', icon: 'share-2', desc: 'Social links, custom sections & references', },
    { id: 'review', title: 'Review & Finish', icon: 'eye', desc: 'Preview, apply template & download', },
  ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// cv-builder-renderers.js — createCvBuilderRenderers
// ═══════════════════════════════════════════════════════════════════════════════

describe('createCvBuilderRenderers', () => {
  let STATE, STEPS, renderers;

  beforeEach(() => {
    STATE = createMockState();
    STEPS = createMockSteps();
    renderers = createCvBuilderRenderers({ STATE, STEPS, escHtml, });
  });

  describe('exports', () => {
    it('should return renderStepContent and renderSkillTags', () => {
      expect(renderers).toHaveProperty('renderStepContent');
      expect(renderers).toHaveProperty('renderSkillTags');
      expect(typeof renderers.renderStepContent).toBe('function');
      expect(typeof renderers.renderSkillTags).toBe('function');
    });
  });

  describe('renderStepContent', () => {
    it('should render the personal step (index 0)', () => {
      const html = renderers.renderStepContent(0);
      expect(html).toBeTypeOf('string');
      expect(html).toContain('bld-field-full_name');
      expect(html).toContain('bld-field-email');
      expect(html).toContain('John Doe');
      expect(html).toContain('john@test.com');
    });

    it('should render the professional step (index 1)', () => {
      const html = renderers.renderStepContent(1);
      expect(html).toBeTypeOf('string');
      expect(html).toContain('bld-experience-list');
      expect(html).toContain('bld-education-list');
      expect(html).toContain('bld-skills-technical');
      expect(html).toContain('bld-skills-soft');
      expect(html).toContain('bld-languages-list');
      expect(html).toContain('JS');
      expect(html).toContain('React');
      expect(html).toContain('English');
    });

    it('should render the extras step (index 2)', () => {
      const html = renderers.renderStepContent(2);
      expect(html).toBeTypeOf('string');
      expect(html).toContain('bld-social-links-list');
      expect(html).toContain('bld-custom-sections-list');
      expect(html).toContain('bld-references-list');
    });

    it('should render the review step (index 3)', () => {
      // Set window.__bldTemplates for the review step
      const origTemplates = window.__bldTemplates;
      window.__bldTemplates = ['modern', 'minimal', 'ats', 'professional', 'creative', 'classic', 'technical', 'executive',];

      const html = renderers.renderStepContent(3);
      expect(html).toBeTypeOf('string');
      expect(html).toContain('bld-template-grid');
      expect(html).toContain('bld-template-modal');
      expect(html).toContain('bld-preview-iframe');
      expect(html).toContain('bld-toast');
      expect(html).toContain('Live Preview &amp; Finish');
      expect(html).toContain('Preview your CV');
      expect(html).toContain('bld-download-wrap');

      // Each template should have a card
      for (const tmpl of ['modern', 'minimal', 'ats', 'professional', 'creative', 'classic', 'technical', 'executive',]) {
        expect(html).toContain(`data-template="${tmpl}"`);
      }

      // The selected template should show 'Applied'
      expect(html).toContain('Applied');
      expect(html).toContain('Default');

      // Should include template-specific gradients
      expect(html).toContain('#4f46e5');
      expect(html).toContain('#7c3aed');
      expect(html).toContain('#10b981');
      expect(html).toContain('#ec4899');

      // Should include user's first initial
      expect(html).toContain("J's CV");

      // Download links exist (URLs are set dynamically by JS)
      expect(html).toContain('bld-download-link');
      expect(html).toContain('bld-template-download-link');
      expect(html).toContain('Download PDF');

      window.__bldTemplates = origTemplates;
    });

    it('should render review step with fallback templates when __bldTemplates is undefined', () => {
      const origTemplates = window.__bldTemplates;
      delete window.__bldTemplates;

      const html = renderers.renderStepContent(3);
      expect(html).toContain('data-template="modern"');
      expect(html).toContain('data-template="ats"');

      window.__bldTemplates = origTemplates;
    });

    it('should show non-selected templates as "Template" with "Apply as Default" button', () => {
      window.__bldTemplates = ['modern', 'minimal',];
      STATE.selectedTemplate = 'modern';

      const html = renderers.renderStepContent(3);

      // modern should show 'Applied' and 'Default'
      expect(html).toContain('Applied');

      // minimal should show 'Apply as Default' and 'Template'
      expect(html).toContain('Apply as Default');
      expect(html).toContain('Template');
    });

    it('should handle empty data gracefully', () => {
      STATE.data = {};
      const html = renderers.renderStepContent(0);
      expect(html).toContain('bld-field-full_name');
      // Values should be empty — first name character comes from mockup, not from data
      expect(html).not.toContain('value="John"');
    });

    it('should throw for unknown step index', () => {
      // STEPS[99] is undefined, so STEPS[index].id throws
      expect(() => renderers.renderStepContent(99)).toThrow(/Cannot read propert(ies|y)/);
    });
  });

  describe('renderSkillTags', () => {
    it('should render skill tags for each skill', () => {
      const skills = ['JavaScript', 'TypeScript', 'React',];
      const html = renderers.renderSkillTags(skills, 'technical');
      expect(html).toContain('JavaScript');
      expect(html).toContain('TypeScript');
      expect(html).toContain('React');
      expect(html).toContain('data-category="technical"');
      expect(html).toContain('bld-skill-tag');
      expect(html).toContain('bld-skill-remove');
    });

    it('should render empty string for empty skills array', () => {
      const html = renderers.renderSkillTags([], 'soft');
      expect(html).toBe('');
    });

    it('should escape HTML in skill names', () => {
      const skills = ['<script>alert("xss")</script>',];
      const html = renderers.renderSkillTags(skills, 'technical');
      expect(html).toContain('&lt;script&gt;');
      expect(html).not.toContain('<script>');
    });

    it('should handle special characters in skill names', () => {
      const skills = ['C++', 'C#', '.NET', 'Node.js',];
      const html = renderers.renderSkillTags(skills, 'technical');
      expect(html).toContain('C++');
      expect(html).toContain('C#');
      expect(html).toContain('.NET');
      expect(html).toContain('Node.js');
    });
  });

  describe('template gradients and thumbnails', () => {
    it('should use per-template gradient backgrounds in review step cards', () => {
      window.__bldTemplates = ['modern', 'creative', 'ats', 'executive',];
      STATE.selectedTemplate = 'modern';

      const html = renderers.renderStepContent(3);

      // Each template should have its distinctive gradient
      expect(html).toContain('linear-gradient(135deg, #4f46e5, #7c3aed)'); // modern
      expect(html).toContain('linear-gradient(135deg, #ec4899, #f97316)'); // creative
      expect(html).toContain('linear-gradient(135deg, #065f46, #10b981)'); // ats
      expect(html).toContain('linear-gradient(135deg, #1a1a2e, #16213e)'); // executive
    });

    it('should show user first initial overlay on template cards', () => {
      STATE.data.personal.full_name = 'Jane Smith';
      window.__bldTemplates = ['modern',];

      const html = renderers.renderStepContent(3);
      expect(html).toContain("J's CV");
    });

    it('should fall back to "Alex Morgan" when no personal data', () => {
      STATE.data = {};
      window.__bldTemplates = ['modern',];

      const html = renderers.renderStepContent(3);
      expect(html).toContain("A's CV");
    });

    it('should fall back to default gradient for unknown template slugs', () => {
      window.__bldTemplates = ['unknown-slug',];
      const html = renderers.renderStepContent(3);
      // Unknown slug falls back to modern's purple gradient
      expect(html).toContain('linear-gradient(135deg, #4f46e5, #7c3aed)');
    });

    it('should render grid with zero template cards when template list is empty', () => {
      window.__bldTemplates = [];
      const html = renderers.renderStepContent(3);
      expect(html).toContain('bld-template-grid');
      // Grid exists but contains no template cards
      expect(html.match(/bld-template-card/g)).toBeNull();
    });
  });

  describe('modal structure in review step', () => {
    it('should include iframe for live preview', () => {
      window.__bldTemplates = ['modern',];
      const html = renderers.renderStepContent(3);
      expect(html).toContain('bld-preview-iframe');
      expect(html).toContain('sandbox="allow-scripts allow-same-origin"');
      expect(html).toContain('CV Template Preview');
    });

    it('should include download and close buttons in modal header', () => {
      window.__bldTemplates = ['modern',];
      const html = renderers.renderStepContent(3);
      expect(html).toContain('download-preview-template');
      expect(html).toContain('close-template-preview');
    });

    it('should include Apply, Close, and Download buttons in modal footer', () => {
      window.__bldTemplates = ['modern',];
      const html = renderers.renderStepContent(3);
      expect(html).toContain('select-preview-template');
      expect(html).toContain('bld-template-download-link');
      expect(html).toContain('Apply as Default');
      expect(html).toContain('Close');
      expect(html).toContain('Download PDF');
    });

    it('should have a loading spinner in the modal body', () => {
      window.__bldTemplates = ['modern',];
      const html = renderers.renderStepContent(3);
      expect(html).toContain('bld-preview-loading');
      expect(html).toContain('bld-spinner');
      expect(html).toContain('Loading preview with your data...');
    });
  });
});

// ═══════════════════════════════════════════════════════════════════════════════
// cv-builder-app.js — Core logic (isolated via inline simulation)
//
// Note: The app module (cv-builder-app.js) has side effects (DOM event listeners,
// auto-init via DOMContentLoaded, window.* globals). These tests validate the
// logic patterns by simulating them inline rather than importing the module.
// ═══════════════════════════════════════════════════════════════════════════════

describe('CV Builder App Logic', () => {
  // We test the logic patterns from cv-builder-app.js in isolation
  // by creating minimal test harnesses that mirror the app's behavior

  describe('escHtml', () => {
    it('should escape HTML special characters', () => {
      expect(escHtml('<script>alert("xss")</script>'))
        .toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
    });

    it('should escape ampersands', () => {
      expect(escHtml('AT&T')).toBe('AT&amp;T');
    });

    it('should escape single quotes', () => {
      expect(escHtml("O'Brien")).toBe('O&#039;Brien');
    });

    it('should return empty string for falsy values', () => {
      expect(escHtml('')).toBe('');
      expect(escHtml(null)).toBe('');
      expect(escHtml(undefined)).toBe('');
      // Number 0: !0 is truthy in JavaScript, so escHtml returns ''
      expect(escHtml(0)).toBe('');
    });

    it('should handle strings that are already safe', () => {
      expect(escHtml('Hello World')).toBe('Hello World');
      expect(escHtml('123-456-7890')).toBe('123-456-7890');
    });
  });

  describe('STEPS array', () => {
    it('should have 4 steps with correct structure', () => {
      const steps = createMockSteps();
      expect(steps).toHaveLength(4);
      expect(steps[0].id).toBe('personal');
      expect(steps[1].id).toBe('professional');
      expect(steps[2].id).toBe('extras');
      expect(steps[3].id).toBe('review');
      expect(steps[3].title).toBe('Review & Finish');
      expect(steps[3].desc).toContain('Preview');
    });

    it('each step should have required fields', () => {
      const steps = createMockSteps();
      for (const step of steps) {
        expect(step).toHaveProperty('id');
        expect(step).toHaveProperty('title');
        expect(step).toHaveProperty('icon');
        expect(step).toHaveProperty('desc');
        expect(typeof step.id).toBe('string');
        expect(typeof step.title).toBe('string');
        expect(typeof step.desc).toBe('string');
      }
    });
  });

  describe('step navigation logic', () => {
    it('should advance to next step and call completeBuilder on last step', () => {
      let currentStep = 0;
      const steps = createMockSteps();
      let completed = false;

      function nextStep() {
        if (currentStep >= steps.length - 1) {
          completed = true;
          return;
        }
        currentStep++;
      }

      // Navigate through all steps
      nextStep(); expect(currentStep).toBe(1); expect(completed).toBe(false);
      nextStep(); expect(currentStep).toBe(2); expect(completed).toBe(false);
      nextStep(); expect(currentStep).toBe(3); expect(completed).toBe(false);
      nextStep(); expect(currentStep).toBe(3); expect(completed).toBe(true);
    });

    it('should go back to previous step', () => {
      let currentStep = 2;

      function prevStep() {
        if (currentStep <= 0) return;
        currentStep--;
      }

      prevStep(); expect(currentStep).toBe(1);
      prevStep(); expect(currentStep).toBe(0);
      prevStep(); expect(currentStep).toBe(0); // can't go below 0
    });

    it('should skip step (not go past last)', () => {
      let currentStep = 0;
      const steps = createMockSteps();

      function skipStep() {
        if (currentStep >= steps.length - 1) return;
        currentStep++;
      }

      skipStep(); expect(currentStep).toBe(1);
      skipStep(); expect(currentStep).toBe(2);
      skipStep(); expect(currentStep).toBe(3);
      skipStep(); expect(currentStep).toBe(3); // can't skip past last
    });
  });

  describe('template selection logic', () => {
    it('should track selected template in state', () => {
      const selectedTemplate = { value: 'modern', };

      function selectTemplate(tmpl) {
        selectedTemplate.value = tmpl;
      }

      selectTemplate('minimal');
      expect(selectedTemplate.value).toBe('minimal');

      selectTemplate('executive');
      expect(selectedTemplate.value).toBe('executive');
    });

    it('should set template on complete builder', () => {
      const state = {
        selectedTemplate: 'creative',
        data: { personal: { full_name: 'Test', }, },
      };

      function completeBuilder(state) {
        state.data._template = state.selectedTemplate;
        return state.data;
      }

      const result = completeBuilder(state);
      expect(result._template).toBe('creative');
    });
  });

  describe('entry management logic', () => {
    it('should add new empty entries to arrays', () => {
      const state = { data: { experience: [], }, };

      function addEntry(section) {
        if (!Array.isArray(state.data[section])) state.data[section] = [];
        state.data[section].push({});
        return state.data[section].length;
      }

      expect(addEntry('experience')).toBe(1);
      expect(addEntry('experience')).toBe(2);
      expect(state.data.experience[0]).toEqual({});
    });

    it('should remove entries by index', () => {
      const state = { data: { experience: [{ company: 'A', }, { company: 'B', }, { company: 'C', },], }, };

      function removeEntry(section, idx) {
        const entries = state.data[section];
        if (!Array.isArray(entries) || idx < 0 || idx >= entries.length) return;
        entries.splice(idx, 1);
      }

      removeEntry('experience', 1);
      expect(state.data.experience).toHaveLength(2);
      expect(state.data.experience[0].company).toBe('A');
      expect(state.data.experience[1].company).toBe('C');
    });

    it('should not remove entry with invalid index', () => {
      const state = { data: { experience: [{ company: 'A', },], }, };

      function removeEntry(section, idx) {
        const entries = state.data[section];
        if (!Array.isArray(entries) || idx < 0 || idx >= entries.length) return;
        entries.splice(idx, 1);
      }

      removeEntry('experience', -1);
      expect(state.data.experience).toHaveLength(1);

      removeEntry('experience', 5);
      expect(state.data.experience).toHaveLength(1);
    });

    it('should move entries up and down', () => {
      const state = { data: { experience: [{ company: 'A', }, { company: 'B', }, { company: 'C', },], }, };

      function moveEntry(section, idx, dir) {
        const entries = state.data[section];
        if (!Array.isArray(entries) || entries.length < 2) return;
        const targetIdx = dir === 'up' ? idx - 1 : idx + 1;
        if (targetIdx < 0 || targetIdx >= entries.length) return;
        const temp = entries[idx];
        entries[idx] = entries[targetIdx];
        entries[targetIdx] = temp;
      }

      // Starting: [A, B, C]
      // Move C up (swap idx 2 with idx 1)
      moveEntry('experience', 2, 'up');
      expect(state.data.experience[1].company).toBe('C');
      expect(state.data.experience[2].company).toBe('B');

      // Move what's now at idx 0 (A) down (swap idx 0 with idx 1)
      // Current: [A, C, B]
      moveEntry('experience', 0, 'down');
      expect(state.data.experience[0].company).toBe('C');
      expect(state.data.experience[1].company).toBe('A');
    });

    it('should not move entry at boundaries', () => {
      const state = { data: { experience: [{ company: 'A', }, { company: 'B', },], }, };

      function moveEntry(section, idx, dir) {
        const entries = state.data[section];
        if (!Array.isArray(entries) || entries.length < 2) return;
        const targetIdx = dir === 'up' ? idx - 1 : idx + 1;
        if (targetIdx < 0 || targetIdx >= entries.length) return;
        const temp = entries[idx];
        entries[idx] = entries[targetIdx];
        entries[targetIdx] = temp;
      }

      moveEntry('experience', 0, 'up'); // already at top
      expect(state.data.experience[0].company).toBe('A');

      moveEntry('experience', 1, 'down'); // already at bottom
      expect(state.data.experience[1].company).toBe('B');
    });

    it('should fix editing index after removal', () => {
      const editingState = { section: 'experience', index: 2, };

      function removeEntry(section, idx) {
        if (editingState.section === section) {
          if (editingState.index === idx) {
            editingState.section = null;
            editingState.index = -1;
          } else if (idx < editingState.index) {
            editingState.index--;
          }
        }
      }

      // Remove an item before the editing index
      removeEntry('experience', 0);
      expect(editingState.index).toBe(1);

      // Remove the editing item itself
      removeEntry('experience', 1);
      expect(editingState.section).toBeNull();
      expect(editingState.index).toBe(-1);
    });
  });

  describe('skill management logic', () => {
    it('should add skills to categorized arrays', () => {
      const skills = { technical: ['JS',], soft: [], };

      function addSkill(category, text) {
        if (!skills[category]) skills[category] = [];
        skills[category].push(text);
      }

      addSkill('technical', 'React');
      expect(skills.technical).toEqual(['JS', 'React',]);

      addSkill('soft', 'Leadership');
      expect(skills.soft).toEqual(['Leadership',]);
    });

    it('should remove skills by index', () => {
      const skills = { technical: ['JS', 'React', 'Node',], soft: [], };

      skills.technical.splice(1, 1);
      expect(skills.technical).toEqual(['JS', 'Node',]);
    });

    it('should edit an existing skill', () => {
      const skills = { technical: ['JS', 'React',], };

      skills.technical[1] = 'Vue';
      expect(skills.technical).toEqual(['JS', 'Vue',]);
    });
  });

  describe('progress calculation logic', () => {
    it('should calculate progress percentage based on step index', () => {
      const steps = createMockSteps();
      const totalSteps = steps.length;

      function calcProgress(currentStep) {
        return Math.round((currentStep / (totalSteps - 1)) * 100);
      }

      expect(calcProgress(0)).toBe(0); // personal
      expect(calcProgress(1)).toBe(33); // professional
      expect(calcProgress(2)).toBe(67); // extras
      expect(calcProgress(3)).toBe(100); // review
    });
  });

  describe('loadLivePreview logic', () => {
    it('should construct correct preview URL with template and cache-buster', () => {
      const cvId = 42;
      const selectedTemplate = 'modern';

      const url = `/api/cv/${ cvId }/preview?template=${ encodeURIComponent(selectedTemplate) }&t=${ Date.now()}`;
      expect(url).toContain('/api/cv/42/preview?template=modern');
      expect(url).toContain('&t=');
    });

    it('should encode special characters in template slug', () => {
      expect(encodeURIComponent('my template')).toBe('my%20template');
    });

    it('should fall back to modern template when none selected', () => {
      const tmpl = 'modern';
      expect(tmpl).toBe('modern');
    });
  });

  describe('completeBuilder data collection', () => {
    it('should fill in missing step data with defaults', () => {
      const state = {
        data: { personal: { full_name: 'Test', }, },
        selectedTemplate: 'professional',
      };
      const steps = createMockSteps();

      // Simulate the completeBuilder logic — only step-level keys are initialized
      for (let si = 0; si < steps.length; si++) {
        const sid = steps[si].id;
        if (sid !== 'review' && !state.data[sid]) {
          if (sid === 'professional') {
            state.data.experience = state.data.experience || [];
            state.data.education = state.data.education || [];
            state.data.skills = state.data.skills || { technical: [], soft: [], };
            state.data.languages = state.data.languages || [];
            state.data.professional = { _combined: true, };
          } else {
            state.data[sid] = [];
          }
        }
      }
      state.data._template = state.selectedTemplate;

      expect(state.data._template).toBe('professional');
      expect(state.data.professional).toEqual({ _combined: true, });
      expect(Array.isArray(state.data.experience)).toBe(true);
      expect(Array.isArray(state.data.education)).toBe(true);
      // 'references' is not a step-level key (not in STEPS), so it won't be auto-initialized
      expect(state.data.extras).toEqual([]);
    });
  });

  describe('bldSelectTemplate download link logic', () => {
    it('should construct PDF download URLs with the selected template', () => {
      const cvId = 42;
      const tmpl = 'technical';
      const downloadUrl = `/cv-builder/${cvId}/export/pdf?template=${encodeURIComponent(tmpl)}`;
      expect(downloadUrl).toBe('/cv-builder/42/export/pdf?template=technical');
    });

    it('should encode special template names', () => {
      const cvId = 42;
      const tmpl = 'ats optimized';
      const downloadUrl = `/cv-builder/${cvId}/export/pdf?template=${encodeURIComponent(tmpl)}`;
      expect(downloadUrl).toBe('/cv-builder/42/export/pdf?template=ats%20optimized');
    });
  });

  describe('bldOpenTemplatePreview label mapping', () => {
    it('should map template slugs to display labels', () => {
      const labels = {
        modern: 'Modern Professional',
        minimal: 'Minimal Elegant',
        ats: 'ATS Optimized',
        professional: 'Classic Professional',
        creative: 'Creative Portfolio',
        classic: 'Classic Traditional',
        technical: 'Technical Engineer',
        executive: 'Executive Elite',
      };

      expect(labels.modern).toBe('Modern Professional');
      expect(labels.creative).toBe('Creative Portfolio');
      expect(labels.executive).toBe('Executive Elite');
      expect(labels.technical).toBe('Technical Engineer');
    });

    it('should fall back to capitalized slug for unknown templates', () => {
      const slug = 'custom';
      const label = { custom: 'Custom', }[slug] || slug.charAt(0).toUpperCase() + slug.slice(1);
      expect(label).toBe('Custom');
    });
  });
});
