/**
 * Autonomous Article Writer - Admin Interface
 * Handles article generation, preview, publishing, and draft saving.
 */
'use strict';
const form = document.getElementById('articleWriterForm');
const topicInput = document.getElementById('articleTopic');
const toneSelect = document.getElementById('articleTone');
const lengthSelect = document.getElementById('articleLength');
const langSelect = document.getElementById('articleLanguage');
const keywordsInput = document.getElementById('articleKeywords');
const styleInput = document.getElementById('articleStyle');
const generateBtn = document.getElementById('generateBtn');
const clearBtn = document.getElementById('clearBtn');
const regenerateBtn = document.getElementById('regenerateBtn');
const publishBtn = document.getElementById('publishBtn');
const saveDraftBtn = document.getElementById('saveDraftBtn');
const editBtn = document.getElementById('editInPostsBtn');
const previewSection = document.getElementById('articlePreviewSection');
const emptyState = document.getElementById('emptyState');
const statusEl = document.getElementById('articleWriterStatus');
const articleContent = document.getElementById('articleContent');
const articleMetaBar = document.getElementById('articleMetaBar');
const articleSEOInfo = document.getElementById('articleSEOInfo');
const articleTags = document.getElementById('articleTags');
const articleKeyPoints = document.getElementById('articleKeyPoints');
const recentSection = document.getElementById('recentArticlesSection');
const recentList = document.getElementById('recentArticlesList');

// ---------- URL param prefill ----------
try {
  const params = new URLSearchParams(window.location.search);
  const topic = params.get('topic') || params.get('q') || '';
  const tone = params.get('tone') || '';
  const length = params.get('length') || '';
  const keywords = params.get('keywords') || '';
  const style = params.get('style') || '';

  if (topic && topicInput) topicInput.value = topic;
  if (tone && toneSelect) {
    for (let ti = 0; ti < toneSelect.options.length; ti++) {
      if (toneSelect.options[ti].value === tone) { toneSelect.selectedIndex = ti; break; }
    }
  }
  if (length && lengthSelect) {
    for (let li = 0; li < lengthSelect.options.length; li++) {
      if (lengthSelect.options[li].value === length) { lengthSelect.selectedIndex = li; break; }
    }
  }
  if (keywords && keywordsInput) keywordsInput.value = keywords;
  if (style && styleInput) styleInput.value = style;

  // Auto-generate if topic prefilled
  if (topic && generateBtn && !generateBtn.disabled) {
    setTimeout(() => { generateBtn.click(); }, 300);
  }
} catch { /* ignore */ }


let currentArticle = null;
let isGenerating = false;
let isPublishing = false;
const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '';
const userId = (document.querySelector('meta[name="user-id"]') || {}).getAttribute('content') || '0';
const articleCategorySelect = document.getElementById('articleCategory');
const editMetaTitle = document.getElementById('editMetaTitle');
const editMetaDescription = document.getElementById('editMetaDescription');
const editTags = document.getElementById('editTags');
const editCategory = document.getElementById('editCategory');
const articleMetaEditFields = document.getElementById('articleMetaEditFields');

function showStatus(msg, type, timeout) {
  statusEl.textContent = msg;
  statusEl.className = `status-message ${type}`;
  if (timeout !== false) {
    setTimeout(() => { statusEl.className = 'status-message'; }, timeout || 5000);
  }
}

function setGenerating(state) {
  isGenerating = state;
  generateBtn.disabled = state;
  if (regenerateBtn) regenerateBtn.disabled = state;
  generateBtn.innerHTML = state
    ? '<span class="loading-spinner"></span> Generating\u2026'
    : '<i class="lucide lucide-wand-sparkles mr-1"></i> <span id="generateBtnText">Generate Article</span>';
}

function setPublishing(state) {
  isPublishing = state;
  publishBtn.disabled = state;
  saveDraftBtn.disabled = state;
  publishBtn.innerHTML = state
    ? '<span class="loading-spinner"></span> Publishing\u2026'
    : '<i class="lucide lucide-globe mr-1"></i> Publish Now';
  saveDraftBtn.innerHTML = state
    ? '<span class="loading-spinner"></span> Saving\u2026'
    : '<i class="lucide lucide-save mr-1"></i> Save as Draft';
}

function esc(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

function wordCount(html) {
  if (!html) return 0;
  const text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  return text ? text.split(' ').length : 0;
}

function truncate(str, max) {
  if (!str || str.length <= max) return str || '';
  return `${str.substring(0, max - 3)}\u2026`;
}

function saveRecent(article) {
  try {
    let recent = JSON.parse(localStorage.getItem('ai-article-writer-recent') || '[]');
    recent.unshift({ title: article.title, date: new Date().toISOString(), words: wordCount(article.content), });
    if (recent.length > 10) recent = recent.slice(0, 10);
    localStorage.setItem('ai-article-writer-recent', JSON.stringify(recent));
    renderRecent(recent);
  } catch { /* ignore */ }
}

function renderRecent(items) {
  if (!items || !items.length) { recentSection.style.display = 'none'; return; }
  recentSection.style.display = 'block';
  recentList.innerHTML = items.map((item) => {
    return `<button type="button" class="flex items-center justify-between w-full px-4 py-3 rounded-lg border border-slate-200 hover:bg-slate-50 hover:border-indigo-200 transition-colors text-left cursor-pointer" data-title="${esc(item.title)}">` +
        '<div class="truncate mr-3">' +
        `<div class="font-semibold truncate">${esc(item.title)}</div>` +
        `<small class="text-slate-500">${item.words || 0} words</small></div>` +
        '<i class="lucide lucide-arrow-right text-indigo-600"></i></button>';
  }).join('');
  recentList.querySelectorAll('[data-title]').forEach((btn) => {
    btn.addEventListener('click', function () {
      topicInput.value = this.getAttribute('data-title');
      topicInput.focus();
      window.scrollTo({ top: 0, behavior: 'smooth', });
    });
  });
}

function loadRecent() {
  try { renderRecent(JSON.parse(localStorage.getItem('ai-article-writer-recent') || '[]')); } catch { /* ignore */ }
}

function populateEditableFields(article) {
  if (!article) return;
  editMetaTitle.value = article.seo_title || '';
  editMetaDescription.value = article.seo_description || '';
  editTags.value = (article.tags || []).join(', ');
  // Try to match the category name from the article metadata
  const catName = article.category || article.category_name || '';
  if (catName && editCategory) {
    for (let ci = 0; ci < editCategory.options.length; ci++) {
      if (editCategory.options[ci].text.toLowerCase() === catName.toLowerCase()) {
        editCategory.value = editCategory.options[ci].value;
        break;
      }
    }
  }
}

function renderArticle(article) {
  currentArticle = article;
  previewSection.style.display = 'block';
  emptyState.style.display = 'none';

  const wc = wordCount(article.content);
  const readTime = article.reading_time_minutes || Math.max(1, Math.round(wc / 200));
  articleMetaBar.innerHTML =
      `<div class="article-meta-item"><div class="label">Words</div><div class="value">${wc.toLocaleString()}</div></div>` +
      `<div class="article-meta-item"><div class="label">Reading Time</div><div class="value">${readTime} min</div></div>` +
      `<div class="article-meta-item"><div class="label">Tone</div><div class="value">${esc(toneSelect.options[toneSelect.selectedIndex].text.split('(')[0].trim())}</div></div>`;

  const seoTitle = article.seo_title || article.title || '';
  const seoDesc = article.seo_description || '';
  articleSEOInfo.innerHTML =
      `<div class="mb-2"><strong>SEO Title:</strong> ${esc(truncate(seoTitle, 60))
      } <span class="seo-char-count">(${seoTitle.length}/60)</span></div>` +
      `<div><strong>Meta Description:</strong> ${esc(truncate(seoDesc, 160))
      } <span class="seo-char-count">(${seoDesc.length}/160)</span></div>`;

  // Populate editable SEO fields
  populateEditableFields(article);
  articleMetaEditFields.style.display = 'block';

  const tags = article.tags || [];
  if (tags.length) {
    articleTags.innerHTML = `<strong class="block text-sm uppercase tracking-wider text-slate-500 mb-1">Tags</strong>${tags.map((t) => { return `<span class="tag-badge">${esc(t)}</span>`; }).join('')}`;
    articleTags.style.display = 'block';
  } else {
    articleTags.style.display = 'none';
  }

  const kp = article.key_points || [];
  if (kp.length) {
    articleKeyPoints.style.display = 'block';
    articleKeyPoints.innerHTML = '<h6 class="font-semibold mb-2"><i class="lucide lucide-list-checks mr-1"></i>Key Points</h6>' +
        `<ul class="key-points-list">${kp.map((p) => { return `<li>${esc(p)}</li>`; }).join('')}</ul>`;
  } else {
    articleKeyPoints.style.display = 'none';
  }

  articleContent.innerHTML = article.content || '<p class="text-slate-500">No content generated.</p>';
  saveRecent(article);

  publishBtn.innerHTML = '<i class="lucide lucide-globe mr-1"></i> Publish Now';
  publishBtn.disabled = false;
  publishBtn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors';
  saveDraftBtn.innerHTML = '<i class="lucide lucide-save mr-1"></i> Save as Draft';
  saveDraftBtn.disabled = false;
  saveDraftBtn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg border border-indigo-600 text-indigo-600 text-sm font-medium hover:bg-indigo-50 transition-colors';
  editBtn.style.display = 'none';
  editBtn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition-colors';
  editBtn.innerHTML = '<i class="lucide lucide-external-link mr-1"></i> Continue in Editor';
  delete editBtn.dataset.postId;
  delete editBtn.dataset.slug;

  if (window.innerWidth < 768) previewSection.scrollIntoView({ behavior: 'smooth', block: 'start', });
}

function resetInterface() {
  currentArticle = null;
  previewSection.style.display = 'none';
  emptyState.style.display = 'block';
  articleContent.innerHTML = '';
  articleMetaBar.innerHTML = '';
  articleSEOInfo.innerHTML = '';
  articleTags.innerHTML = '';
  articleTags.style.display = 'none';
  articleKeyPoints.style.display = 'none';
  articleMetaEditFields.style.display = 'none';
  statusEl.className = 'status-message';
  publishBtn.innerHTML = '<i class="lucide lucide-globe mr-1"></i> Publish Now';
  publishBtn.disabled = false;
  publishBtn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors';
  saveDraftBtn.innerHTML = '<i class="lucide lucide-save mr-1"></i> Save as Draft';
  saveDraftBtn.disabled = false;
  saveDraftBtn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg border border-indigo-600 text-indigo-600 text-sm font-medium hover:bg-indigo-50 transition-colors';
  editBtn.style.display = 'none';
  editBtn.className = 'inline-flex items-center justify-center px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50 transition-colors';
  editBtn.innerHTML = '<i class="lucide lucide-external-link mr-1"></i> Continue in Editor';
}

function generateArticle(topic, opts) {
  if (isGenerating) return;
  setGenerating(true);
  showStatus('Generating article\u2026 This may take 30-60 seconds.', 'info', 60000);

  fetch('/api/admin/ai/article-writer/generate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, },
    body: JSON.stringify({ topic: topic, tone: opts.tone, length: opts.length, language: opts.language, keywords: opts.keywords, style: opts.style, _csrf_token: csrfToken, }),
  })
    .then((r) => {
      if (!r.ok) return r.json().then((d) => { throw new Error(d.error || `HTTP ${r.status}`); });
      return r.json();
    })
    .then((d) => {
      setGenerating(false);
      if (!d.success) { showStatus(d.error || 'Generation failed.', 'error'); return; }
      if (!d.article || !d.article.title || !d.article.content) { showStatus('Generated article is incomplete.', 'error'); return; }
      showStatus('Article generated successfully!', 'success', 4000);
      renderArticle(d.article);
    })
    .catch((err) => {
      setGenerating(false);
      let msg = err.message || 'Network error.';
      if (msg.indexOf('502') >= 0) msg = 'AI provider took too long. Try a shorter length.';
      else if (msg.indexOf('500') >= 0) msg = 'Server error. Check AI System settings.';
      showStatus(msg, 'error', 8000);
    });
}

function buildSavePayload() {
  const payload = {
    title: currentArticle.title,
    seo_title: currentArticle.seo_title,
    seo_description: currentArticle.seo_description,
    content: currentArticle.content,
    slug: currentArticle.slug,
    tags: currentArticle.tags,
    reading_time_minutes: currentArticle.reading_time_minutes,
    key_points: currentArticle.key_points,
    category_id: '',
  };
    // Override SEO fields from editable inputs if present
  if (editMetaTitle && editMetaTitle.value.trim()) {
    payload.seo_title = editMetaTitle.value.trim();
  }
  if (editMetaDescription && editMetaDescription.value.trim()) {
    payload.seo_description = editMetaDescription.value.trim();
  }
  if (editTags && editTags.value.trim()) {
    payload.tags = editTags.value.split(',').map((t) => { return t.trim(); }).filter(Boolean);
  }
  if (editCategory && editCategory.value) {
    payload.category_id = editCategory.value;
  } else if (articleCategorySelect && articleCategorySelect.value) {
    payload.category_id = articleCategorySelect.value;
  }
  return payload;
}

function saveArticle(publish) {
  if (isPublishing || !currentArticle) return;
  setPublishing(true);
  showStatus(`${publish ? 'Publishing' : 'Saving draft'} article\u2026`, 'info', 30000);

  const data = buildSavePayload();

  fetch('/api/admin/ai/article-writer/publish', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, },
    body: JSON.stringify({ article: data, publish: publish, author_id: parseInt(userId, 10) || 0, _csrf_token: csrfToken, }),
  })
    .then((r) => {
      if (!r.ok) return r.json().then((d) => { throw new Error(d.error || `HTTP ${r.status}`); });
      return r.json();
    })
    .then((d) => {
      setPublishing(false);
      if (!d.success) { showStatus(d.error || 'Failed to save.', 'error'); return; }
      const label = publish ? 'published' : 'saved as draft';
      showStatus(`Article "${currentArticle.title || 'Untitled'}" ${label}!`, 'success', 6000);
      editBtn.style.display = 'inline-flex';
      editBtn.dataset.postId = d.post_id || '';
      editBtn.dataset.slug = d.slug || '';
      editBtn.innerHTML = '<i class="lucide lucide-external-link mr-1"></i> Continue in Editor';
      if (publish) { publishBtn.innerHTML = '<i class="lucide lucide-check-circle mr-1"></i> Published'; publishBtn.disabled = true; }
      else { saveDraftBtn.innerHTML = '<i class="lucide lucide-check-circle mr-1"></i> Draft Saved'; saveDraftBtn.disabled = true; }
    })
    .catch((err) => {
      setPublishing(false);
      showStatus(err.message || 'Network error.', 'error', 8000);
    });
}

// Handle article generation via click on the button (type="button")
if (generateBtn) {
  generateBtn.addEventListener('click', () => {
    const topic = topicInput ? topicInput.value.trim() : '';
    if (!topic) { showStatus('Please enter a topic.', 'error'); if (topicInput) topicInput.focus(); return; }
    generateArticle(topic, {
      tone: toneSelect ? toneSelect.value : 'informative',
      length: lengthSelect ? lengthSelect.value : 'medium',
      language: langSelect ? langSelect.value : 'en',
      keywords: keywordsInput ? keywordsInput.value.trim() : '',
      style: styleInput ? styleInput.value.trim() : '',
    });
  });
}

// Also intercept form submission as a safety net
if (form) {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    e.stopPropagation();
  });
}

if (regenerateBtn) regenerateBtn.addEventListener('click', () => {
  const topic = topicInput.value.trim();
  if (!topic) { showStatus('Enter a topic first.', 'error'); topicInput.focus(); return; }
  generateArticle(topic, { tone: toneSelect.value, length: lengthSelect.value, language: langSelect.value, keywords: keywordsInput.value.trim(), style: styleInput.value.trim(), });
});

if (clearBtn) clearBtn.addEventListener('click', () => {
  if (form) form.reset();
  resetInterface();
  if (topicInput) topicInput.focus();
  showStatus('Cleared. Ready to write.', 'info', 2000);
});

if (publishBtn) publishBtn.addEventListener('click', () => {
  currentArticle ? saveArticle(true) : showStatus('Generate an article first.', 'error');
});

if (saveDraftBtn) saveDraftBtn.addEventListener('click', () => {
  currentArticle ? saveArticle(false) : showStatus('Generate an article first.', 'error');
});

if (editBtn) editBtn.addEventListener('click', function () {
  const postId = this.dataset.postId;
  const slug = this.dataset.slug;
  if (postId) {
    window.location.href = `/admin/posts/edit?id=${postId}`;
  } else if (slug) {
    window.location.href = `/admin/posts/edit?slug=${slug}`;
  } else {
    window.location.href = '/admin/posts';
  }
});

if (topicInput) topicInput.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    e.preventDefault();
    if (generateBtn) generateBtn.click();
  }
});

const emptyIcon = emptyState && emptyState.querySelector('i');
if (emptyIcon) emptyIcon.addEventListener('click', () => {
  const examples = ['The Impact of AI on Modern Healthcare', 'A Beginner\'s Guide to Sustainable Living', 'Top 10 Web Development Trends in 2025', 'Understanding Blockchain Technology', 'The Art of Digital Storytelling', 'Remote Work Best Practices', 'Bengali Literature in the Digital Age', 'How to Start a Successful Blog',];
  topicInput.value = examples[Math.floor(Math.random() * examples.length)];
  topicInput.focus();
});

loadRecent();
if (topicInput) topicInput.focus();

export {};
