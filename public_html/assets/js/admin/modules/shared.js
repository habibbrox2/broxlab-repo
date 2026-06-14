/**
 * Shared Admin Utilities Module
 * Lazy-loaded via loadAdminModule('shared').
 * Contains small utility functions for dashboard, content form, media, and flash messages.
 */

export function initFlashMessageAutoDismiss({ byId, }) {
  const flashMsg = byId('flash-message');
  if (!flashMsg || typeof broxUI === 'undefined') return;
  setTimeout(() => {
    try {
      new broxUI.Alert(flashMsg).close();
    } catch { /* ignore auto-dismiss errors */ }
  }, 5000);
}

export function initDashboardData({ byId, parseJson, }) {
  const dataEl = byId('admin-dashboard-data');
  if (!dataEl) return;
  window.BLOG_DASHBOARD = {
    trendLabels: parseJson(dataEl.dataset.trendLabels, []),
    trendSeries: parseJson(dataEl.dataset.trendSeries, []),
  };
}

export function initMediaDetail() {
  if (!document.querySelector('[data-media-detail]')) return;
  window.copyToClipboard = function (text) {
    navigator.clipboard
      .writeText(text)
      .then(() => {
        alert('URL copied to clipboard!');
      })
      .catch((err) => {
        console.error('Failed to copy:', err);
      });
  };
}

export function initContentFormData({ byId, parseJson, }) {
  const dataEl = byId('admin-content-data');
  if (!dataEl) return;
  const categoryIds = parseJson(dataEl.dataset.categoryIds, []);
  const tagIds = parseJson(dataEl.dataset.tagIds, []);
  const contentType = dataEl.dataset.contentType || '';

  window.itemCategoryIds = categoryIds;
  window.itemTagIds = tagIds;
  if (contentType === 'posts') {
    window.postCategoryIds = categoryIds;
    window.postTagIds = tagIds;
  } else if (contentType === 'pages') {
    window.pageCategoryIds = categoryIds;
    window.pageTagIds = tagIds;
  }

  if (window.adminContent?.fetchCategories) {
    window.adminContent.fetchCategories(categoryIds, '#category_ids_select');
  }
  if (window.adminContent?.initializeCategoriesSelect) {
    window.adminContent.initializeCategoriesSelect('#category_ids_select');
  }
  if (window.adminContent?.fetchTags) {
    window.adminContent.fetchTags(tagIds, '#tags');
  }
  if (window.adminContent?.initializeTagsSelect) {
    window.adminContent.initializeTagsSelect('#tags');
  }
}
