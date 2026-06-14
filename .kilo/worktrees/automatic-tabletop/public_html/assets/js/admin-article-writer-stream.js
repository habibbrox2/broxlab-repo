/**
 * Admin Article Writer Stream
 * Real-time streaming article writer with SSE
 */
(function () {
  'use strict';

  const writer = {
    state: 'idle',
    activeXHR: null,
    postId: null,
    articleSlug: null,
    articleTitle: '',
    articleContent: '',
    startTime: null,
    timerInterval: null,
    el: {},
    csrfToken: '',

    init() {
      this.cacheEls();
      this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      this.bindEvents();
    },

    cacheEls() {
      const ids = [
        'topicInput','toneSelect','lengthSelect','languageSelect',
        'keywordsInput','generateBtn','generateBtnText','generateBtnSpinner',
        'stopBtn','statusDot','statusText','statusElapsed',
        'liveTitle','progressFill','contentEditor',
        'wordCount','charCount','saveBtn','publishBtn','copyBtn','newTopicBtn',
        'saveToast','toastMessage'
      ];
      ids.forEach(id => { this.el[id] = document.getElementById(id); });
    },

    bindEvents() {
      this.el.generateBtn?.addEventListener('click', () => this.startGeneration());
      this.el.stopBtn?.addEventListener('click', () => this.stopGeneration());
      this.el.newTopicBtn?.addEventListener('click', () => this.reset());
      this.el.saveBtn?.addEventListener('click', () => this.saveArticle(false));
      this.el.publishBtn?.addEventListener('click', () => this.saveArticle(true));
      this.el.copyBtn?.addEventListener('click', () => this.copyContent());
      this.el.topicInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') this.startGeneration();
      });
    },

    startGeneration() {
      const topic = this.el.topicInput?.value.trim();
      if (!topic) {
        this.el.topicInput?.focus();
        this.setStatus('error', 'Please enter a topic');
        return;
      }

      if (this.state === 'generating') return;

      this.reset(false);
      this.state = 'generating';
      this.articleContent = '';
      this.articleTitle = '';
      this.startTime = Date.now();
      this.startTimer();

      this.el.generateBtn.disabled = true;
      this.el.generateBtnText.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Generating...';
      this.el.generateBtnSpinner?.classList.remove('d-none');
      this.el.stopBtn.disabled = false;
      this.el.progressFill.style.width = '10%';
      this.setStatus('generating', 'Generating article...');

      const options = {
        topic: topic,
        tone: this.el.toneSelect?.value || 'informative',
        length: this.el.lengthSelect?.value || 'medium',
        language: this.el.languageSelect?.value || 'en',
        keywords: this.el.keywordsInput?.value || ''
      };

      this.fetchStream(options);
    },

    async fetchStream(options) {
      try {
        const resp = await fetch('/api/admin/ai/article-writer-stream/generate', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': this.csrfToken
          },
          body: JSON.stringify(options)
        });

        const reader = resp.body?.getReader();
        if (!reader) {
          this.handleError('Stream not supported');
          return;
        }

        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n');
          buffer = lines.pop() || '';

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              const data = line.slice(6).trim();
              if (data === '[DONE]') {
                this.onComplete();
                return;
              }
              try {
                const parsed = JSON.parse(data);
                this.processEvent(parsed);
              } catch (e) {
                // skip invalid JSON
              }
            }
          }
        }

        if (buffer.trim()) {
          const data = buffer.slice(6).trim();
          if (data === '[DONE]') {
            this.onComplete();
            return;
          }
          try {
            const parsed = JSON.parse(data);
            this.processEvent(parsed);
          } catch (e) {}
        }

        this.onComplete();
      } catch (err) {
        this.handleError('Network error: ' + err.message);
      }
    },

    processEvent(data) {
      switch (data.type) {
        case 'start':
          this.setStatus('generating', data.message || 'Generating...');
          break;

        case 'title':
          this.articleTitle = data.title || '';
          this.el.liveTitle.textContent = this.articleTitle || 'Live Preview';
          this.el.progressFill.style.width = '20%';
          break;

        case 'meta':
          if (data.slug) this.articleSlug = data.slug;
          if (data.content) {
            this.articleContent = data.content;
            this.renderContent();
          }
          break;

        case 'content':
          this.articleContent += data.chunk || '';
          this.renderContent();
          this.updateProgress();
          break;

        case 'complete':
          this.articleContent = data.content || this.articleContent;
          this.articleTitle = data.title || this.articleTitle;
          this.postId = data.post_id || null;
          this.articleSlug = data.slug || null;
          this.renderContent();
          this.onComplete();
          break;

        case 'error':
          this.handleError(data.error || 'Generation failed');
          break;
      }
    },

    renderContent() {
      const editor = this.el.contentEditor;
      if (!editor) return;

      let html = '';
      if (this.articleTitle) {
        html += '<h1>' + this.escapeHtml(this.articleTitle) + '</h1>';
      }

      const paragraphs = this.articleContent.split(/\n{2,}/);
      paragraphs.forEach(p => {
        const trimmed = p.trim();
        if (!trimmed) return;

        if (trimmed.startsWith('## ')) {
          html += '<h2>' + this.escapeHtml(trimmed.slice(3)) + '</h2>';
        } else if (trimmed.startsWith('# ')) {
          html += '<h1>' + this.escapeHtml(trimmed.slice(2)) + '</h1>';
        } else {
          html += '<p>' + this.escapeHtml(trimmed) + '</p>';
        }
      });

      html += '<span class="typing-cursor"></span>';
      editor.innerHTML = html;
      editor.scrollTop = editor.scrollHeight;

      this.updateStats();
    },

    updateStats() {
      const text = this.articleTitle + ' ' + this.articleContent;
      const words = text.trim() ? text.trim().split(/\s+/).length : 0;
      const chars = text.length;

      if (this.el.wordCount) this.el.wordCount.textContent = words + ' words';
      if (this.el.charCount) this.el.charCount.textContent = chars + ' chars';
    },

    updateProgress() {
      const el = this.el.progressFill;
      const current = parseFloat(el.style.width) || 20;
      if (current < 90) {
        const increment = Math.random() * 8 + 2;
        el.style.width = Math.min(current + increment, 90) + '%';
      }
    },

    onComplete() {
      this.state = 'done';
      this.stopTimer();
      this.el.progressFill.style.width = '100%';
      this.el.generateBtn.disabled = false;
      this.el.generateBtnText.innerHTML = '<i class="bi bi-lightning-fill mr-1"></i>Generate Article';
      this.el.generateBtnSpinner?.classList.add('d-none');
      this.el.stopBtn.disabled = true;
      this.el.saveBtn.disabled = false;
      this.el.publishBtn.disabled = false;
      this.el.copyBtn.disabled = false;
      this.setStatus('done', 'Article generated successfully');

      // Remove cursor
      const cursor = this.el.contentEditor?.querySelector('.typing-cursor');
      if (cursor) cursor.remove();
    },

    stopGeneration() {
      this.state = 'idle';
      this.stopTimer();
      this.el.generateBtn.disabled = false;
      this.el.generateBtnText.innerHTML = '<i class="bi bi-lightning-fill mr-1"></i>Ge
