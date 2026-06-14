/**
 * feed-discovery.js — Modern Content Discovery Dashboard
 * Features: live search, category/tag/date filtering, sort, infinite scroll,
 * intersection observer lazy loading, bookmark/share, view toggle, skeleton loading
 */
'use strict';

const FeedDiscovery = {
  __VERSION__: '1.0.0',
  config: {
    page: 1,
    totalPages: 1,
    limit: 12,
    sort: 'latest',
    searchQuery: '',
    activeCategory: '',
    activeTag: '',
    dateFrom: '',
    dateTo: '',
    viewMode: 'grid',
    feedType: 'recent',
    isLoading: false,
    hasMore: true,
    intersectionMargin: '200px',
    bookmarkKey: 'feed_discovery_bookmarks',
  },

  elements: {},
  state: {},

  init() {
    this.cacheElements();
    if (!this.elements.feedContainer) return;
    this.loadPreferences();
    this.bindEvents();
    this.setupIntersectionObserver();
    this.setupSkeletonLoader();
    this.restoreBookmarks();
    this.updateUI();
  },

  cacheElements() {
    this.elements = {
      feedContainer: document.getElementById('discovery-feed'),
      feedGrid: document.getElementById('discovery-feed-grid'),
      toolbar: document.querySelector('.discovery-toolbar'),
      searchInput: document.getElementById('feed-search'),
      searchClear: document.getElementById('feed-search-clear'),
      categoryFilter: document.getElementById('feed-category'),
      tagFilter: document.getElementById('feed-tag'),
      dateFrom: document.getElementById('feed-date-from'),
      dateTo: document.getElementById('feed-date-to'),
      sortSelect: document.getElementById('feed-sort'),
      viewToggle: document.getElementById('feed-view-toggle'),
      autoRefresh: document.getElementById('feed-auto-refresh'),
      autoRefreshLabel: document.getElementById('feed-auto-refresh-label'),
      loadMoreBtn: document.getElementById('feed-load-more'),
      loadMoreText: document.getElementById('feed-load-more-text'),
      feedStatus: document.getElementById('feed-status'),
      feedEnd: document.getElementById('feed-end'),
      feedError: document.getElementById('feed-error'),
      feedEmpty: document.getElementById('feed-empty'),
      skeletonContainer: document.getElementById('feed-skeleton'),
      filterBadges: document.getElementById('feed-filter-badges'),
      filterDrawerToggle: document.getElementById('feed-filter-drawer-toggle'),
      scrollSentinel: document.getElementById('feed-scroll-sentinel'),
      activeFilters: document.getElementById('feed-active-filters'),
      totalCount: document.getElementById('feed-total-count'),
    };
  },

  loadPreferences() {
    try {
      const saved = localStorage.getItem('feed_discovery_prefs');
      if (saved) {
        const prefs = JSON.parse(saved);
        this.config.viewMode = prefs.viewMode || 'grid';
        this.config.sort = prefs.sort || 'latest';
      }
      if (this.elements.sortSelect) {
        this.elements.sortSelect.value = this.config.sort;
      }
    } catch (e) { /* ignore */ }
  },

  savePreferences() {
    try {
      localStorage.setItem('feed_discovery_prefs', JSON.stringify({
        viewMode: this.config.viewMode,
        sort: this.config.sort,
      }));
    } catch (e) { /* ignore */ }
  },

  getBookmarks() {
    try {
      const data = localStorage.getItem(this.config.bookmarkKey);
      return data ? JSON.parse(data) : [];
    } catch (e) { return []; }
  },

  saveBookmarks(bookmarks) {
    try {
      localStorage.setItem(this.config.bookmarkKey, JSON.stringify(bookmarks));
    } catch (e) { /* ignore */ }
  },

  isBookmarked(id) {
    return this.getBookmarks().some((b) => { return b.id === id; });
  },

  toggleBookmark(id, title, url) {
    const bookmarks = this.getBookmarks();
    const idx = bookmarks.findIndex((b) => { return b.id === id; });
    if (idx >= 0) {
      bookmarks.splice(idx, 1);
    } else {
      bookmarks.push({ id: id, title: title, url: url, savedAt: new Date().toISOString(), });
    }
    this.saveBookmarks(bookmarks);
    this.updateBookmarkUI(id);
    return idx < 0;
  },

  updateBookmarkUI(id) {
    const btns = document.querySelectorAll(`[data-bookmark-id="${ id.replace(/"/g, '&quot;') }"]`);
    const isBm = this.isBookmarked(id);
    btns.forEach((btn) => {
      btn.classList.toggle('is-bookmarked', isBm);
      btn.setAttribute('aria-label', isBm ? 'Remove bookmark' : 'Bookmark this item');
    });
  },

  restoreBookmarks() {
    const bookmarks = this.getBookmarks();
    const self = this;
    bookmarks.forEach((bm) => { self.updateBookmarkUI(bm.id); });
  },

  bindEvents() {
    const self = this;

    // Delegated clicks for bookmark, share, copy buttons on cards
    document.addEventListener('click', (e) => {
      const target = e.target;
      // Walk up DOM to find action button
      let btn = target.closest('[data-bookmark-id]');
      if (btn && self.elements.feedGrid && self.elements.feedGrid.contains(btn)) {
        e.preventDefault();
        const id = btn.getAttribute('data-bookmark-id');
        const bookmarkTitle = btn.getAttribute('data-bookmark-title') || '';
        const bookmarkUrl = btn.getAttribute('data-bookmark-url') || '';
        self.toggleBookmark(id, bookmarkTitle, bookmarkUrl);
        return;
      }
      btn = target.closest('[data-share-url]');
      if (btn && self.elements.feedGrid && self.elements.feedGrid.contains(btn)) {
        e.preventDefault();
        const shareUrl = btn.getAttribute('data-share-url') || '';
        const shareTitle = btn.getAttribute('data-share-title') || '';
        self.openShareModal(shareTitle, shareUrl);
        return;
      }
      btn = target.closest('[data-copy-url]');
      if (btn && self.elements.feedGrid && self.elements.feedGrid.contains(btn)) {
        e.preventDefault();
        const copyUrl = btn.getAttribute('data-copy-url') || '';
        self.copyToClipboard(copyUrl, btn);
        return;
      }
    });

    if (this.elements.searchInput) {
      let searchTimer;
      this.elements.searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          self.config.searchQuery = self.elements.searchInput.value.trim();
          self.config.page = 1;
          self.resetFeed();
        }, 350);
      });
      if (this.elements.searchClear) {
        this.elements.searchClear.addEventListener('click', () => {
          if (self.elements.searchInput) {
            self.elements.searchInput.value = '';
            self.config.searchQuery = '';
            self.config.page = 1;
            self.resetFeed();
          }
        });
      }
    }

    if (this.elements.categoryFilter) {
      this.elements.categoryFilter.addEventListener('change', () => {
        self.config.activeCategory = self.elements.categoryFilter.value;
        self.config.page = 1;
        self.resetFeed();
      });
    }

    if (this.elements.tagFilter) {
      this.elements.tagFilter.addEventListener('change', () => {
        self.config.activeTag = self.elements.tagFilter.value;
        self.config.page = 1;
        self.resetFeed();
      });
    }

    if (this.elements.dateFrom) {
      this.elements.dateFrom.addEventListener('change', () => {
        self.config.dateFrom = self.elements.dateFrom.value;
        self.config.page = 1;
        self.resetFeed();
      });
    }
    if (this.elements.dateTo) {
      this.elements.dateTo.addEventListener('change', () => {
        self.config.dateTo = self.elements.dateTo.value;
        self.config.page = 1;
        self.resetFeed();
      });
    }

    if (this.elements.sortSelect) {
      this.elements.sortSelect.addEventListener('change', () => {
        self.config.sort = self.elements.sortSelect.value;
        self.config.page = 1;
        self.savePreferences();
        self.resetFeed();
      });
    }

    if (this.elements.viewToggle) {
      this.elements.viewToggle.addEventListener('click', () => {
        self.config.viewMode = self.config.viewMode === 'grid' ? 'list' : 'grid';
        self.savePreferences();
        self.updateViewMode();
      });
    }

    if (this.elements.autoRefresh) {
      this.elements.autoRefresh.addEventListener('change', () => {
        const enabled = self.elements.autoRefresh.checked;
        if (self.elements.autoRefreshLabel) {
          self.elements.autoRefreshLabel.textContent = enabled ? 'Auto-refresh on' : 'Auto-refresh off';
        }
        if (enabled) self.startAutoRefresh();
        else self.stopAutoRefresh();
      });
    }

    if (this.elements.loadMoreBtn) {
      this.elements.loadMoreBtn.addEventListener('click', () => { self.loadMore(); });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        self.closeModal();
      }
    });
  },

  setupIntersectionObserver() {
    const self = this;
    if (!this.elements.scrollSentinel || !('IntersectionObserver' in window)) return;

    this._observer = new IntersectionObserver(((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !self.config.isLoading && self.config.hasMore) {
          self.loadMore();
        }
      });
    }), { rootMargin: this.config.intersectionMargin, });

    this._observer.observe(this.elements.scrollSentinel);
  },

  setupSkeletonLoader() {
    if (!this.elements.skeletonContainer) return;
    const count = this.config.limit || 6;
    let html = '';
    for (let i = 0; i < count; i++) {
      html += '<div class="discovery-skeleton" aria-hidden="true">' +
          '<div class="skeleton-image skeleton-pulse"></div>' +
          '<div class="skeleton-body">' +
          '<div class="skeleton-line skeleton-pulse" style="width: 30%"></div>' +
          '<div class="skeleton-line skeleton-pulse" style="width: 85%"></div>' +
          '<div class="skeleton-line skeleton-pulse" style="width: 65%"></div>' +
          '<div class="skeleton-line skeleton-pulse" style="width: 40%"></div>' +
          '<div class="skeleton-btn skeleton-pulse"></div>' +
          '</div></div>';
    }
    this.elements.skeletonContainer.innerHTML = html;
  },

  showSkeleton() {
    if (this.elements.skeletonContainer) {
      this.elements.skeletonContainer.classList.remove('hidden');
    }
    if (this.elements.feedGrid) {
      this.elements.feedGrid.classList.add('hidden');
    }
    if (this.elements.feedError) {
      this.elements.feedError.classList.add('hidden');
    }
    if (this.elements.feedEmpty) {
      this.elements.feedEmpty.classList.add('hidden');
    }
  },

  hideSkeleton() {
    if (this.elements.skeletonContainer) {
      this.elements.skeletonContainer.classList.add('hidden');
    }
    if (this.elements.feedGrid) {
      this.elements.feedGrid.classList.remove('hidden');
    }
  },

  showError(message) {
    if (this.elements.skeletonContainer) {
      this.elements.skeletonContainer.classList.add('hidden');
    }
    if (this.elements.feedEmpty) {
      this.elements.feedEmpty.classList.add('hidden');
    }
    if (this.elements.feedError) {
      this.elements.feedError.classList.remove('hidden');
      const msgEl = this.elements.feedError.querySelector('[data-error-message]');
      if (msgEl) msgEl.textContent = message || 'Something went wrong. Please try again.';
    }
  },

  showEmpty(message) {
    if (this.elements.skeletonContainer) {
      this.elements.skeletonContainer.classList.add('hidden');
    }
    if (this.elements.feedError) {
      this.elements.feedError.classList.add('hidden');
    }
    if (this.elements.feedEmpty) {
      this.elements.feedEmpty.classList.remove('hidden');
      const msgEl = this.elements.feedEmpty.querySelector('[data-empty-message]');
      if (msgEl) msgEl.textContent = message || 'No items found.';
    }
  },

  updateViewMode() {
    if (!this.elements.feedGrid) return;
    this.elements.feedGrid.classList.toggle('is-grid-view', this.config.viewMode === 'grid');
    this.elements.feedGrid.classList.toggle('is-list-view', this.config.viewMode === 'list');
    if (this.elements.viewToggle) {
      const label = this.elements.viewToggle.querySelector('.btn-label');
      if (label) label.textContent = this.config.viewMode === 'grid' ? 'Grid' : 'List';
    }
  },

  updateUI() {
    this.updateViewMode();
    if (this.elements.totalCount) {
      const cards = this.elements.feedGrid ? this.elements.feedGrid.querySelectorAll('.discovery-card').length : 0;
      this.elements.totalCount.textContent = cards;
    }
  },

  resetFeed() {
    if (!this.elements.feedGrid) return;
    this.config.page = 1;
    this.config.hasMore = true;
    this.elements.feedGrid.innerHTML = '';
    this.showSkeleton();
    this.loadMore();
  },

  loadMore() {
    if (this.config.isLoading || !this.config.hasMore) return;
    this.config.isLoading = true;
    const event = new CustomEvent('feed:load-more', {
      detail: {
        page: this.config.page,
        sort: this.config.sort,
        search: this.config.searchQuery,
        category: this.config.activeCategory,
        tag: this.config.activeTag,
        dateFrom: this.config.dateFrom,
        dateTo: this.config.dateTo,
      },
      bubbles: true,
    });
    if (this.elements.feedContainer) {
      this.elements.feedContainer.dispatchEvent(event);
    }
  },

  appendItems(html, hasMore) {
    if (!this.elements.feedGrid) return;
    this.hideSkeleton();
    this.elements.feedGrid.insertAdjacentHTML('beforeend', html);
    this.config.page++;
    this.config.hasMore = hasMore !== false;
    this.config.isLoading = false;
    this.updateUI();
    this.restoreBookmarks();
  },

  closeModal() {
    const modal = document.querySelector('.discovery-modal.open');
    if (modal) {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }
  },

  openShareModal(title, url) {
    const modal = document.getElementById('discovery-share-modal');
    if (!modal) return;
    modal.classList.add('open');
    modal.removeAttribute('aria-hidden');
    const titleEl = modal.querySelector('[data-share-title]');
    if (titleEl) titleEl.textContent = title;
    const urlEl = modal.querySelector('[data-share-url]');
    if (urlEl) urlEl.value = url || window.location.href;
  },

  copyToClipboard(text, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        FeedDiscovery._showCopied(btn);
      }).catch(() => {
        FeedDiscovery._fallbackCopy(text, btn);
      });
    } else {
      FeedDiscovery._fallbackCopy(text, btn);
    }
  },

  _showCopied(btn) {
    if (!btn) return;
    const orig = btn.innerHTML;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
    setTimeout(() => { btn.innerHTML = orig; }, 2000);
  },

  _fallbackCopy(text, btn) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      FeedDiscovery._showCopied(btn);
    } catch (e) {
      prompt('Copy this link:', text);
    }
    document.body.removeChild(ta);
  },

  startAutoRefresh() {
    this.stopAutoRefresh();
    const self = this;
    this._refreshTimer = setInterval(() => {
      self.resetFeed();
    }, 30000);
  },

  stopAutoRefresh() {
    if (this._refreshTimer) {
      clearInterval(this._refreshTimer);
      this._refreshTimer = null;
    }
  },

  destroy() {
    this.stopAutoRefresh();
    if (this._observer) {
      this._observer.disconnect();
      this._observer = null;
    }
  },
};


// Auto-init on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => { FeedDiscovery.init(); });
} else {
  FeedDiscovery.init();
}

export { FeedDiscovery };
export default FeedDiscovery;
window.FeedDiscovery = FeedDiscovery;
