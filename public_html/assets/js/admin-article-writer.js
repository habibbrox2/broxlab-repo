/**
 * Autonomous Article Writer - Admin Interface
 * Handles article generation, preview, publishing, and draft saving.
 */
(function () {
  'use strict';
  console.log('[AW DEBUG] Article Writer IIFE starting');

  var form = document.getElementById('articleWriterForm');
  console.log('[AW DEBUG] form element:', form ? form.id : 'NOT FOUND');
  var topicInput = document.getElementById('articleTopic');
  var toneSelect = document.getElementById('articleTone');
  var lengthSelect = document.getElementById('articleLength');
  var langSelect = document.getElementById('articleLanguage');
  var keywordsInput = document.getElementById('articleKeywords');
  var styleInput = document.getElementById('articleStyle');
  var generateBtn = document.getElementById('generateBtn');
  var clearBtn = document.getElementById('clearBtn');
  var regenerateBtn = document.getElementById('regenerateBtn');
  var publishBtn = document.getElementById('publishBtn');
  var saveDraftBtn = document.getElementById('saveDraftBtn');
  var editBtn = document.getElementById('editInPostsBtn');
  var previewSection = document.getElementById('articlePreviewSection');
  var emptyState = document.getElementById('emptyState');
  var statusEl = document.getElementById('articleWriterStatus');
  var articleContent = document.getElementById('articleContent');
  var articleMetaBar = document.getElementById('articleMetaBar');
  var articleSEOInfo = document.getElementById('articleSEOInfo');
  var articleTags = document.getElementById('articleTags');
  var articleKeyPoints = document.getElementById('articleKeyPoints');
  var recentSection = document.getElementById('recentArticlesSection');
  var recentList = document.getElementById('recentArticlesList');

  var currentArticle = null;
  var isGenerating = false;
  var isPublishing = false;
  var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '';
  var userId = (document.querySelector('meta[name="user-id"]') || {}).getAttribute('content') || '0';

  function showStatus(msg, type, timeout) {
    statusEl.textContent = msg;
    statusEl.className = 'status-message ' + type;
    if (timeout !== false) {
      setTimeout(function () { statusEl.className = 'status-message'; }, timeout || 5000);
    }
  }

  function setGenerating(state) {
    isGenerating = state;
    generateBtn.disabled = state;
    if (regenerateBtn) regenerateBtn.disabled = state;
    generateBtn.innerHTML = state
      ? '<span class="loading-spinner"></span> Generating\u2026'
      : '<i class="bi bi-magic me-1"></i> <span id="generateBtnText">Generate Article</span>';
  }

  function setPublishing(state) {
    isPublishing = state;
    publishBtn.disabled = state;
    saveDraftBtn.disabled = state;
    publishBtn.innerHTML = state
      ? '<span class="loading-spinner"></span> Publishing\u2026'
      : '<i class="bi bi-globe me-1"></i> Publish Now';
    saveDraftBtn.innerHTML = state
      ? '<span class="loading-spinner"></span> Saving\u2026'
      : '<i class="bi bi-save me-1"></i> Save as Draft';
  }

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  function wordCount(html) {
    if (!html) return 0;
    var text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return text ? text.split(' ').length : 0;
  }

  function truncate(str, max) {
    if (!str || str.length <= max) return str || '';
    return str.substring(0, max - 3) + '\u2026';
  }

  function saveRecent(article) {
    try {
      var recent = JSON.parse(localStorage.getItem('ai-article-writer-recent') || '[]');
      recent.unshift({ title: article.title, date: new Date().toISOString(), words: wordCount(article.content) });
      if (recent.length > 10) recent = recent.slice(0, 10);
      localStorage.setItem('ai-article-writer-recent', JSON.stringify(recent));
      renderRecent(recent);
    } catch (e) {}
  }

  function renderRecent(items) {
    if (!items || !items.length) { recentSection.style.display = 'none'; return; }
    recentSection.style.display = 'block';
    recentList.innerHTML = items.map(function (item) {
      return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-title="' + esc(item.title) + '">' +
        '<div class="text-truncate me-3">' +
        '<div class="fw-semibold text-truncate">' + esc(item.title) + '</div>' +
        '<small class="text-muted">' + (item.words || 0) + ' words</small></div>' +
        '<i class="bi bi-arrow-right text-primary"></i></button>';
    }).join('');
    recentList.querySelectorAll('[data-title]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        topicInput.value = this.getAttribute('data-title');
        topicInput.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  function loadRecent() {
    try { renderRecent(JSON.parse(localStorage.getItem('ai-article-writer-recent') || '[]')); } catch (e) {}
  }

  function renderArticle(article) {
    currentArticle = article;
    previewSection.style.display = 'block';
    emptyState.style.display = 'none';

    var wc = wordCount(article.content);
    var readTime = article.reading_time_minutes || Math.max(1, Math.round(wc / 200));
    articleMetaBar.innerHTML =
      '<div class="article-meta-item"><div class="label">Words</div><div class="value">' + wc.toLocaleString() + '</div></div>' +
      '<div class="article-meta-item"><div class="label">Reading Time</div><div class="value">' + readTime + ' min</div></div>' +
      '<div class="article-meta-item"><div class="label">Tone</div><div class="value">' + esc(toneSelect.options[toneSelect.selectedIndex].text.split('(')[0].trim()) + '</div></div>';

    var seoTitle = article.seo_title || article.title || '';
    var seoDesc = article.seo_description || '';
    articleSEOInfo.innerHTML =
      '<div class="mb-2"><strong>SEO Title:</strong> ' + esc(truncate(seoTitle, 60)) +
      ' <span class="seo-char-count">(' + seoTitle.length + '/60)</span></div>' +
      '<div><strong>Meta Description:</strong> ' + esc(truncate(seoDesc, 160)) +
      ' <span class="seo-char-count">(' + seoDesc.length + '/160)</span></div>';

    var tags = article.tags || [];
    if (tags.length) {
      articleTags.innerHTML = '<strong class="d-block mb-1 small text-uppercase text-muted">Tags</strong>' +
        tags.map(function (t) { return '<span class="tag-badge">' + esc(t) + '</span>'; }).join('');
      articleTags.style.display = 'block';
    } else {
      articleTags.style.display = 'none';
    }

    var kp = article.key_points || [];
    if (kp.length) {
      articleKeyPoints.style.display = 'block';
      articleKeyPoints.innerHTML = '<h6 class="fw-semibold mb-2"><i class="bi bi-list-check me-1"></i>Key Points</h6>' +
        '<ul class="key-points-list">' + kp.map(function (p) { return '<li>' + esc(p) + '</li>'; }).join('') + '</ul>';
    } else {
      articleKeyPoints.style.display = 'none';
    }

    articleContent.innerHTML = article.content || '<p class="text-muted">No content generated.</p>';
    saveRecent(article);

    publishBtn.innerHTML = '<i class="bi bi-globe me-1"></i> Publish Now';
    publishBtn.disabled = false;
    publishBtn.className = 'btn btn-success';
    saveDraftBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save as Draft';
    saveDraftBtn.disabled = false;
    saveDraftBtn.className = 'btn btn-outline-primary';
    editBtn.style.display = 'none';
    delete editBtn.dataset.postId;
    delete editBtn.dataset.slug;

    if (window.innerWidth < 768) previewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    statusEl.className = 'status-message';
    publishBtn.innerHTML = '<i class="bi bi-globe me-1"></i> Publish Now';
    publishBtn.disabled = false;
    publishBtn.className = 'btn btn-success';
    saveDraftBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save as Draft';
    saveDraftBtn.disabled = false;
    saveDraftBtn.className = 'btn btn-outline-primary';
    editBtn.style.display = 'none';
  }

  function generateArticle(topic, opts) {
    if (isGenerating) return;
    setGenerating(true);
    showStatus('Generating article\u2026 This may take 30-60 seconds.', 'info', 60000);

    fetch('/api/admin/ai/article-writer/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ topic: topic, tone: opts.tone, length: opts.length, language: opts.language, keywords: opts.keywords, style: opts.style, _csrf_token: csrfToken })
    })
    .then(function (r) {
      if (!r.ok) return r.json().then(function (d) { throw new Error(d.error || 'HTTP ' + r.status); });
      return r.json();
    })
    .then(function (d) {
      setGenerating(false);
      if (!d.success) { showStatus(d.error || 'Generation failed.', 'error'); return; }
      if (!d.article || !d.article.title || !d.article.content) { showStatus('Generated article is incomplete.', 'error'); return; }
      showStatus('Article generated successfully!', 'success', 4000);
      renderArticle(d.article);
    })
    .catch(function (err) {
      setGenerating(false);
      var msg = err.message || 'Network error.';
      if (msg.indexOf('502') >= 0) msg = 'AI provider took too long. Try a shorter length.';
      else if (msg.indexOf('500') >= 0) msg = 'Server error. Check AI System settings.';
      showStatus(msg, 'error', 8000);
    });
  }

  function saveArticle(publish) {
    if (isPublishing || !currentArticle) return;
    setPublishing(true);
    showStatus((publish ? 'Publishing' : 'Saving draft') + ' article\u2026', 'info', 30000);

    var data = { title: currentArticle.title, seo_title: currentArticle.seo_title, seo_description: currentArticle.seo_description, content: currentArticle.content, slug: currentArticle.slug, tags: currentArticle.tags, reading_time_minutes: currentArticle.reading_time_minutes, key_points: currentArticle.key_points };

    fetch('/api/admin/ai/article-writer/publish', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ article: data, publish: publish, author_id: parseInt(userId, 10) || 0, _csrf_token: csrfToken })
    })
    .then(function (r) {
      if (!r.ok) return r.json().then(function (d) { throw new Error(d.error || 'HTTP ' + r.status); });
      return r.json();
    })
    .then(function (d) {
      setPublishing(false);
      if (!d.success) { showStatus(d.error || 'Failed to save.', 'error'); return; }
      var label = publish ? 'published' : 'saved as draft';
      showStatus('Article "' + (currentArticle.title || 'Untitled') + '" ' + label + '!', 'success', 6000);
      editBtn.style.display = 'inline-flex';
      editBtn.dataset.postId = d.post_id || '';
      editBtn.dataset.slug = d.slug || '';
      if (publish) { publishBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Published'; publishBtn.disabled = true; }
      else { saveDraftBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Draft Saved'; saveDraftBtn.disabled = true; }
    })
    .catch(function (err) {
      setPublishing(false);
      showStatus(err.message || 'Network error.', 'error', 8000);
    });
  }

  // Handle article generation via click on the button (type="button")
  if (generateBtn) {
    console.log('[AW DEBUG] Attaching click handler to generateBtn');
    generateBtn.addEventListener('click', function (e) {
      console.log('[AW DEBUG] Generate button clicked!');
      var topic = topicInput ? topicInput.value.trim() : '';
      if (!topic) { showStatus('Please enter a topic.', 'error'); if (topicInput) topicInput.focus(); return; }
      generateArticle(topic, {
        tone: toneSelect ? toneSelect.value : 'informative',
        length: lengthSelect ? lengthSelect.value : 'medium',
        language: langSelect ? langSelect.value : 'en',
        keywords: keywordsInput ? keywordsInput.value.trim() : '',
        style: styleInput ? styleInput.value.trim() : ''
      });
    });
    console.log('[AW DEBUG] Click handler attached to generateBtn');
  } else {
    console.log('[AW DEBUG] generateBtn is null, cannot attach click handler');
  }
  
  // Also intercept form submission as a safety net
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();
    });
  }

  if (regenerateBtn) regenerateBtn.addEventListener('click', function () {
    var topic = topicInput.value.trim();
    if (!topic) { showStatus('Enter a topic first.', 'error'); topicInput.focus(); return; }
    generateArticle(topic, { tone: toneSelect.value, length: lengthSelect.value, language: langSelect.value, keywords: keywordsInput.value.trim(), style: styleInput.value.trim() });
  });

  if (clearBtn) clearBtn.addEventListener('click', function () {
    if (form) form.reset();
    resetInterface();
    if (topicInput) topicInput.focus();
    showStatus('Cleared. Ready to write.', 'info', 2000);
  });

  if (publishBtn) publishBtn.addEventListener('click', function () {
    currentArticle ? saveArticle(true) : showStatus('Generate an article first.', 'error');
  });

  if (saveDraftBtn) saveDraftBtn.addEventListener('click', function () {
    currentArticle ? saveArticle(false) : showStatus('Generate an article first.', 'error');
  });

  if (editBtn) editBtn.addEventListener('click', function () {
    var postId = this.dataset.postId || this.dataset.slug;
    window.open(postId ? '/admin/posts/' + postId : '/admin/posts', '_blank');
  });

  if (topicInput) topicInput.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      if (generateBtn) generateBtn.click();
    }
  });

  var emptyIcon = emptyState && emptyState.querySelector('i');
  if (emptyIcon) emptyIcon.addEventListener('click', function () {
    var examples = ['The Impact of AI on Modern Healthcare', 'A Beginner\'s Guide to Sustainable Living', 'Top 10 Web Development Trends in 2025', 'Understanding Blockchain Technology', 'The Art of Digital Storytelling', 'Remote Work Best Practices', 'Bengali Literature in the Digital Age', 'How to Start a Successful Blog'];
    topicInput.value = examples[Math.floor(Math.random() * examples.length)];
    topicInput.focus();
  });

  console.log('[AW DEBUG] Article Writer IIFE completed successfully');
  loadRecent();
  if (topicInput) topicInput.focus();
})();
