/**
 * BroxBhai AI SYSTEM - Knowledge Base Manager (2026 Admin)
 * Path: /public_html/ai/js/ai-knowledge-manager.js
 *
 * Features:
 *   - CRUD operations for knowledge slices
 *   - Search, filter by category/type/status
 *   - Show inactive toggle with localStorage persistence
 *   - Modal-based edit/add form
 */

// ── Auto-inject ai-style.css ──────────────────────────────────
(() => {
  const cssUrl = (document.currentScript?.src || '/ai/dist/ai-knowledge-manager.js')
    .replace(/\/(?:js|dist)\/[^/]+$/, '/dist/ai-style.css');
  if (!document.querySelector(`link[href="${cssUrl}"]`)) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = cssUrl;
    document.head.appendChild(link);
  }
})();

const KBApi = {
  baseUrl: '/api/admin/ai-knowledge',
  deleteUrl: '/api/admin/ai-knowledge/delete',

  async list(params = {}) {
    const query = new URLSearchParams({ limit: '100', ...params });
    const res = await fetch(`${this.baseUrl}?${query}`, { credentials: 'same-origin' });
    if (!res.ok) {
      if (res.status === 401 || res.status === 403) {
        throw new KBError('Authentication required', 'AUTH_ERROR');
      }
      throw new KBError(`HTTP ${res.status}`, 'HTTP_ERROR');
    }
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new KBError('Non-JSON response', 'INVALID_RESPONSE');
    }
    const data = await res.json();
    return (data.items || []).map(normalizeItem);
  },

  async get(id) {
    const res = await fetch(`${this.baseUrl}/${id}`, { credentials: 'same-origin' });
    if (!res.ok) throw new KBError(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.success) throw new KBError(data.error || 'Failed to load item');
    return normalizeItem(data.item);
  },

  async save(payload) {
    const res = await fetch(this.baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    });
    if (!res.ok) throw new KBError(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.success) throw new KBError(data.error || 'Save failed');
    return data;
  },

  async toggleStatus(id, isActive) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('is_active', isActive ? 1 : 0);
    formData.append('csrf_token', getCsrfTokenValue());

    const res = await fetch(this.baseUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (!data.success) throw new KBError(data.error || 'Update failed');
    return data;
  },

  async delete(id) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', getCsrfTokenValue());

    const res = await fetch(this.deleteUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (!data.success) throw new KBError(data.error || 'Delete failed');
    return data;
  },
};

class KBError extends Error {
  constructor(message, code = 'UNKNOWN') {
    super(message);
    this.name = 'KBError';
    this.code = code;
  }
}

function getCsrfTokenValue() {
  return document.querySelector('input[name=csrf_token]')?.value || '';
}

function toBool(v) {
  if (v === true || v === 1) return true;
  if (v === false || v === 0 || v === null || v === undefined) return false;
  const s = String(v).trim().toLowerCase();
  return s === '1' || s === 'true' || s === 'yes' || s === 'y' || s === 'on';
}

function normalizeItem(item) {
  if (!item || typeof item !== 'object') return item;
  return {
    ...item,
    id: typeof item.id === 'number' ? item.id : (parseInt(item.id, 10) || item.id),
    is_active: toBool(item.is_active),
    priority: typeof item.priority === 'number' ? item.priority : (parseInt(item.priority, 10) || 0),
  };
}

function loadShowInactive() {
  try {
    const v = localStorage.getItem('kb_show_inactive');
    return v === '1' || v === 'true';
  } catch {
    return false;
  }
}

function saveShowInactive(value) {
  try {
    localStorage.setItem('kb_show_inactive', value ? '1' : '0');
  } catch {
    // ignore
  }
}

function escapeHtml(s) {
  if (!s) return '';
  const div = document.createElement('div');
  div.textContent = s;
  return div.innerHTML;
}

function getCategoryBadge(category) {
  if (!category) return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-50 text-slate-700">-</span>';
  const badges = {
    general: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">General</span>',
    admin: '<span class="badge bg-purple" style="background-color: #6f42c1 !important;">Admin</span>',
    api: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">API</span>',
    features: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Features</span>',
    security: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Security</span>',
    deployment: '<span class="badge bg-warning text-dark">Deployment</span>',
    content: '<span class="badge bg-dark">Content</span>',
    notification: '<span class="badge bg-pink" style="background-color: #e83e8c !important;">Notification</span>',
  };
  return badges[category] || `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">${escapeHtml(category)}</span>`;
}

function getSourceBadge(type) {
  const badges = {
    text: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"><i class="lucide lucide-file-text"></i> Text</span>',
    pdf: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="lucide lucide-file-text"></i> PDF</span>',
    url: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="lucide lucide-link"></i> URL</span>',
    document: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800"><i class="lucide lucide-file"></i> Document</span>',
  };
  return badges[type] || badges.text;
}

class KnowledgeBaseManager {
  constructor() {
    this.allItems = [];
    this.showInactive = loadShowInactive();

    this.nodes = {};
    this.init();
  }

  init() {
    this.nodes = {
      container: document.getElementById('kbList'),
      count: document.getElementById('kbCount'),
      search: document.getElementById('kbSearch'),
      filterCategory: document.getElementById('kbFilterCategory'),
      filterType: document.getElementById('kbFilterType'),
      showInactiveToggle: document.getElementById('kbShowInactive'),
      refreshBtn: document.getElementById('btnRefresh'),
      addBtn: document.getElementById('btnAddSlice'),
      saveBtn: document.getElementById('kbSaveBtn'),
      form: document.getElementById('kbForm'),
      modal: document.getElementById('kbModal'),
    };

    this.bindEvents();
    this.fetchItems();
  }

  bindEvents() {
    if (this.nodes.showInactiveToggle) {
      this.nodes.showInactiveToggle.checked = !!this.showInactive;
      this.nodes.showInactiveToggle.addEventListener('change', () => {
        this.showInactive = this.nodes.showInactiveToggle.checked;
        saveShowInactive(this.showInactive);
        this.fetchItems();
      });
    }

    if (this.nodes.refreshBtn) {
      this.nodes.refreshBtn.addEventListener('click', () => this.fetchItems());
    }

    if (this.nodes.search) {
      this.nodes.search.addEventListener('input', () => this.filterItems());
    }
    if (this.nodes.filterCategory) {
      this.nodes.filterCategory.addEventListener('change', () => this.filterItems());
    }
    if (this.nodes.filterType) {
      this.nodes.filterType.addEventListener('change', () => this.filterItems());
    }

    if (this.nodes.addBtn) {
      this.nodes.addBtn.addEventListener('click', () => this.openAddModal());
    }

    if (this.nodes.saveBtn) {
      this.nodes.saveBtn.addEventListener('click', () => this.saveItem());
    }

    if (this.nodes.form) {
      this.nodes.form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.ctrlKey) {
          this.saveItem();
        }
      });
    }
  }

  showAlert(msg, type = 'success') {
    const el = document.getElementById('alertContainer');
    if (!el) return;
    el.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${msg}
        <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" data-brox-dismiss="alert" aria-label="Close"></button>
      </div>
    `;
    setTimeout(() => {
      const alert = el.querySelector('.alert');
      if (alert) alert.remove();
    }, 5000);
  }

  async fetchItems() {
    try {
      const params = { limit: '100' };
      if (this.showInactive) {
        params.include_inactive = '1';
      }
      this.allItems = await KBApi.list(params);
      this.filterItems();
    } catch (err) {
      console.error('[KB] Failed to fetch:', err);
      this.showAlert(`Failed to load knowledge base: ${err.message}`, 'danger');
    }
  }

  filterItems() {
    const searchTerm = (this.nodes.search?.value || '').toLowerCase();
    const categoryFilter = this.nodes.filterCategory?.value || '';
    const typeFilter = this.nodes.filterType?.value || '';

    let filtered = this.allItems;

    if (!this.showInactive) {
      filtered = filtered.filter((item) => !!item.is_active);
    }

    if (searchTerm) {
      filtered = filtered.filter((item) =>
        (item.title || '').toLowerCase().includes(searchTerm) ||
        (item.excerpt || '').toLowerCase().includes(searchTerm) ||
        (item.content || '').toLowerCase().includes(searchTerm) ||
        (item.category || '').toLowerCase().includes(searchTerm)
      );
    }

    if (categoryFilter) {
      filtered = filtered.filter((item) => item.category === categoryFilter);
    }

    if (typeFilter) {
      filtered = filtered.filter((item) => item.source_type === typeFilter);
    }

    this.renderList(filtered);
  }

  renderList(items) {
    if (!this.nodes.container) return;

    if (this.nodes.count) {
      this.nodes.count.textContent = `${items.length} items`;
    }

    if (!items.length) {
      this.renderEmptyState();
      return;
    }

    const rowsHtml = items.map((item) => `
      <tr>
        <td>${item.id}</td>
        <td>
          <strong>${escapeHtml(item.title)}</strong>
          <br><small class="text-muted">${escapeHtml(item.excerpt || '')}</small>
        </td>
        <td>${getCategoryBadge(item.category)}</td>
        <td>${getSourceBadge(item.source_type)}</td>
        <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">${item.priority || 0}</span></td>
        <td>${item.is_active
          ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>'
          : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Inactive</span>'
        }</td>
        <td>
          <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-600 text-indigo-600 hover:bg-indigo-50 transition-colors mr-1" data-action="edit" data-id="${item.id}" title="Edit">
            <i class="lucide lucide-pencil"></i>
          </button>
          <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-${item.is_active ? 'warning' : 'success'} mr-1" data-action="toggle" data-id="${item.id}" title="${item.is_active ? 'Deactivate' : 'Activate'}">
            <i class="lucide lucide-${item.is_active ? 'eye-off' : 'eye'}"></i>
          </button>
          <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-red-600 text-red-600 hover:bg-red-50 transition-colors" data-action="delete" data-id="${item.id}" title="Delete">
            <i class="lucide lucide-trash-2"></i>
          </button>
        </td>
      </tr>
    `).join('');

    this.nodes.container.innerHTML = `
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th style="width: 50px;">ID</th>
              <th>Title</th>
              <th>Category</th>
              <th>Type</th>
              <th>Priority</th>
              <th>Status</th>
              <th style="width: 150px;">Actions</th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>
    `;

    this.bindTableActions();
  }

  renderEmptyState() {
    this.nodes.container.innerHTML = `
      <div class="text-center py-5 text-muted">
        <i class="lucide lucide-book fs-1"></i>
        <p class="mt-2">No knowledge slices found.</p>
        <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors" id="btnAddFirst">
          <i class="lucide lucide-plus"></i> Add Your First Slice
        </button>
      </div>
    `;
    document.getElementById('btnAddFirst')?.addEventListener('click', () => this.openAddModal());
  }

  bindTableActions() {
    this.nodes.container.querySelectorAll('[data-action="edit"]').forEach((btn) => {
      btn.addEventListener('click', () => this.editItem(parseInt(btn.dataset.id)));
    });
    this.nodes.container.querySelectorAll('[data-action="toggle"]').forEach((btn) => {
      btn.addEventListener('click', () => this.toggleItemStatus(parseInt(btn.dataset.id)));
    });
    this.nodes.container.querySelectorAll('[data-action="delete"]').forEach((btn) => {
      btn.addEventListener('click', () => this.deleteItem(parseInt(btn.dataset.id)));
    });
  }

  openAddModal() {
    this.resetForm();
    this.showModal();
  }

  async editItem(id) {
    try {
      const item = await KBApi.get(id);
      document.getElementById('kb_id').value = item.id;
      document.getElementById('kb_title').value = item.title || '';
      document.getElementById('kb_content').value = item.content || '';
      document.getElementById('kb_source_type').value = item.source_type || 'text';
      document.getElementById('kb_category').value = item.category || '';
      document.getElementById('kb_priority').value = item.priority || 0;
      document.getElementById('kb_is_active').checked = !!item.is_active;
      this.showModal();
    } catch (err) {
      console.error('[KB] Failed to load item:', err);
      this.showAlert(`Failed to load item: ${err.message}`, 'danger');
    }
  }

  resetForm() {
    if (!this.nodes.form) return;
    this.nodes.form.reset();
    document.getElementById('kb_id').value = 0;
    document.getElementById('kb_source_type').value = 'text';
    document.getElementById('kb_category').value = '';
    document.getElementById('kb_priority').value = 0;
    document.getElementById('kb_is_active').checked = true;
  }

  showModal() {
    if (typeof broxUI !== 'undefined' && broxUI.Modal) {
      const modal = new broxUI.Modal(this.nodes.modal);
      modal.show();
    } else {
      this.nodes.modal?.classList.add('show');
      this.nodes.modal.style.display = 'block';
    }
  }

  async saveItem() {
    const title = document.getElementById('kb_title')?.value.trim();
    const content = document.getElementById('kb_content')?.value.trim();

    if (!title || !content) {
      this.showAlert('Please fill in both title and content', 'warning');
      return;
    }

    const payload = {
      id: parseInt(document.getElementById('kb_id')?.value) || 0,
      title,
      content,
      source_type: document.getElementById('kb_source_type')?.value || 'text',
      category: document.getElementById('kb_category')?.value || '',
      priority: parseInt(document.getElementById('kb_priority')?.value) || 0,
      is_active: document.getElementById('kb_is_active')?.checked || false,
      csrf_token: getCsrfTokenValue(),
    };

    try {
      await KBApi.save(payload);
      this.showAlert('Knowledge slice saved successfully');
      if (typeof broxUI !== 'undefined' && broxUI.Modal) {
        const modal = broxUI.Modal.getInstance(this.nodes.modal);
        if (modal) modal.hide();
      }
      await this.fetchItems();
    } catch (err) {
      console.error('[KB] Save failed:', err);
      this.showAlert(`Save failed: ${err.message}`, 'danger');
    }
  }

  async toggleItemStatus(id) {
    const item = this.allItems.find((i) => i.id === id);
    if (!item) return;

    try {
      await KBApi.toggleStatus(id, !item.is_active);
      this.showAlert('Status updated successfully');
      await this.fetchItems();
    } catch (err) {
      this.showAlert(`Update failed: ${err.message}`, 'danger');
    }
  }

  async deleteItem(id) {
    if (!confirm('Are you sure you want to delete this knowledge slice?')) return;

    try {
      await KBApi.delete(id);
      this.showAlert('Knowledge slice deleted successfully');
      await this.fetchItems();
    } catch (err) {
      this.showAlert(`Delete failed: ${err.message}`, 'danger');
    }
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  window.kbManager = new KnowledgeBaseManager();
});
