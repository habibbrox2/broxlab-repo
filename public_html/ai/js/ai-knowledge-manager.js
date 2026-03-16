(function () {
    var apiList = '/api/admin/ai-knowledge';
    var allItems = [];
    var showInactive = false;

    function showAlert(msg, type) {
        type = type || 'success';
        var el = document.getElementById('alertContainer');
        el.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        // Auto dismiss after 5 seconds
        setTimeout(function () {
            var alert = el.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }

    function escapeHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderList(items) {
        var container = document.getElementById('kbList');
        var countEl = document.getElementById('kbCount');

        if (countEl) {
            countEl.textContent = items.length + ' items';
        }

        if (!items.length) {
            container.innerHTML = '<div class="text-center py-5 text-muted">' +
                '<i class="bi bi-book fs-1"></i>' +
                '<p class="mt-2">No knowledge slices found.</p>' +
                '<button class="btn btn-primary btn-sm" id="btnAddFirst">' +
                '<i class="bi bi-plus-lg"></i> Add Your First Slice' +
                '</button>' +
                '</div>';
            document.getElementById('btnAddFirst').onclick = function () {
                document.getElementById('btnAddSlice').click();
            };
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-hover mb-0">' +
            '<thead><tr>' +
            '<th style="width: 50px;">ID</th>' +
            '<th>Title</th>' +
            '<th>Category</th>' +
            '<th>Type</th>' +
            '<th>Priority</th>' +
            '<th>Status</th>' +
            '<th style="width: 150px;">Actions</th>' +
            '</tr></thead><tbody>';

        items.forEach(function (item) {
            var categoryBadge = getCategoryBadge(item.category);
            var sourceBadge = getSourceBadge(item.source_type);
            var statusBadge = item.is_active ?
                '<span class="badge bg-success">Active</span>' :
                '<span class="badge bg-secondary">Inactive</span>';

            html += '<tr>' +
                '<td>' + item.id + '</td>' +
                '<td>' +
                '<strong>' + escapeHtml(item.title) + '</strong>' +
                '<br><small class="text-muted">' + escapeHtml(item.excerpt || '') + '</small>' +
                '</td>' +
                '<td>' + categoryBadge + '</td>' +
                '<td>' + sourceBadge + '</td>' +
                '<td><span class="badge bg-info">' + (item.priority || 0) + '</span></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                '<button class="btn btn-sm btn-outline-primary me-1" data-id="' + item.id + '" onclick="kbEdit(' + item.id + ')" title="Edit">' +
                '<i class="bi bi-pencil"></i>' +
                '</button>' +
                '<button class="btn btn-sm btn-outline-' + (item.is_active ? 'warning' : 'success') + ' me-1" data-id="' + item.id + '" onclick="kbToggle(' + item.id + ')" title="' + (item.is_active ? 'Deactivate' : 'Activate') + '">' +
                '<i class="bi bi-' + (item.is_active ? 'eye-slash' : 'eye') + '"></i>' +
                '</button>' +
                '<button class="btn btn-sm btn-outline-danger" data-id="' + item.id + '" onclick="kbDelete(' + item.id + ')" title="Delete">' +
                '<i class="bi bi-trash"></i>' +
                '</button>' +
                '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    function getCategoryBadge(category) {
        if (!category) return '<span class="badge bg-light text-dark">-</span>';
        var badges = {
            'general': '<span class="badge bg-primary">General</span>',
            'admin': '<span class="badge bg-purple" style="background-color: #6f42c1 !important;">Admin</span>',
            'api': '<span class="badge bg-info">API</span>',
            'features': '<span class="badge bg-success">Features</span>',
            'security': '<span class="badge bg-danger">Security</span>',
            'deployment': '<span class="badge bg-warning text-dark">Deployment</span>',
            'content': '<span class="badge bg-dark">Content</span>',
            'notification': '<span class="badge bg-pink" style="background-color: #e83e8c !important;">Notification</span>'
        };
        return badges[category] || '<span class="badge bg-secondary">' + escapeHtml(category) + '</span>';
    }

    function getSourceBadge(type) {
        var badges = {
            'text': '<span class="badge bg-primary"><i class="bi bi-file-text"></i> Text</span>',
            'pdf': '<span class="badge bg-danger"><i class="bi bi-file-pdf"></i> PDF</span>',
            'url': '<span class="badge bg-success"><i class="bi bi-link"></i> URL</span>',
            'document': '<span class="badge bg-secondary"><i class="bi bi-file-earmark"></i> Document</span>'
        };
        return badges[type] || badges['text'];
    }

    function filterItems() {
        var searchInput = document.getElementById('kbSearch');
        var filterCategory = document.getElementById('kbFilterCategory');
        var filterSelect = document.getElementById('kbFilterType');

        var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        var categoryFilter = filterCategory ? filterCategory.value : '';
        var typeFilter = filterSelect ? filterSelect.value : '';

        var filtered = allItems;

        // Filter by active status
        if (!showInactive) {
            filtered = filtered.filter(function (item) {
                return item.is_active != 0 && item.is_active != false;
            });
        }

        if (searchTerm) {
            filtered = filtered.filter(function (item) {
                return (item.title && item.title.toLowerCase().includes(searchTerm)) ||
                    (item.excerpt && item.excerpt.toLowerCase().includes(searchTerm)) ||
                    (item.content && item.content.toLowerCase().includes(searchTerm)) ||
                    (item.category && item.category.toLowerCase().includes(searchTerm));
            });
        }

        if (categoryFilter) {
            filtered = filtered.filter(function (item) {
                return item.category === categoryFilter;
            });
        }

        if (typeFilter) {
            filtered = filtered.filter(function (item) {
                return item.source_type === typeFilter;
            });
        }

        renderList(filtered);
    }

    async function fetchList() {
        try {
            var res = await fetch(apiList + '?limit=100', { credentials: 'same-origin' });
            if (!res.ok) {
                // If not OK, check if it's a redirect (login required)
                if (res.status === 401 || res.status === 403 || res.redirected) {
                    console.warn('Knowledge base API requires authentication');
                    showAlert('Please log in to access the knowledge base', 'warning');
                    return;
                }
                throw new Error('HTTP ' + res.status);
            }
            var contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Not JSON response');
            }
            var data = await res.json();
            allItems = data.items || [];
            filterItems();
        } catch (e) {
            console.error('Failed to fetch knowledge base:', e);
            showAlert('Failed to load knowledge base: ' + e.message, 'danger');
        }
    }

    window.kbEdit = async function (id) {
        try {
            var res = await fetch(apiList + '/' + id, { credentials: 'same-origin' });
            if (!res.ok) {
                showAlert('Failed to load item', 'danger');
                return;
            }
            var data = await res.json();
            if (!data.success) { showAlert('Failed to load item', 'danger'); return; }
            var it = data.item;
            document.getElementById('kb_id').value = it.id;
            document.getElementById('kb_title').value = it.title || '';
            document.getElementById('kb_content').value = it.content || '';
            document.getElementById('kb_source_type').value = it.source_type || 'text';
            document.getElementById('kb_category').value = it.category || '';
            document.getElementById('kb_priority').value = it.priority || 0;
            document.getElementById('kb_is_active').checked = it.is_active != 0 && it.is_active != false;
            var modal = new bootstrap.Modal(document.getElementById('kbModal'));
            modal.show();
        } catch (e) {
            console.error('Failed to load item:', e);
            showAlert('Failed to load item', 'danger');
        }
    };

    window.kbToggle = async function (id) {
        var item = allItems.find(function (i) { return i.id === id; });
        if (!item) return;

        var newStatus = !(item.is_active != 0 && item.is_active != false);
        var form = new FormData();
        form.append('id', id);
        form.append('is_active', newStatus ? 1 : 0);
        form.append('csrf_token', document.querySelector('input[name=csrf_token]').value);

        try {
            var res = await fetch(apiList, { method: 'POST', body: form, credentials: 'same-origin' });
            var data = await res.json();
            if (data.success) {
                showAlert('Status updated successfully');
                fetchList();
            } else {
                showAlert('Update failed: ' + (data.error || 'Unknown error'), 'danger');
            }
        } catch (e) {
            showAlert('Update failed: ' + e.message, 'danger');
        }
    };

    window.kbDelete = async function (id) {
        if (!confirm('Are you sure you want to delete this knowledge slice?')) return;
        var form = new FormData();
        form.append('id', id);
        form.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
        var res = await fetch('/api/admin/ai-knowledge/delete', { method: 'POST', body: form, credentials: 'same-origin' });
        var data = await res.json();
        if (data.success) {
            showAlert('Knowledge slice deleted successfully');
            fetchList();
        } else {
            showAlert('Delete failed: ' + (data.error || 'Unknown error'), 'danger');
        }
    };

    async function save() {
        var title = document.getElementById('kb_title').value.trim();
        var content = document.getElementById('kb_content').value.trim();

        if (!title || !content) {
            showAlert('Please fill in both title and content', 'warning');
            return;
        }

        var id = document.getElementById('kb_id').value || 0;
        var payload = {
            id: id,
            title: title,
            content: content,
            source_type: document.getElementById('kb_source_type').value,
            category: document.getElementById('kb_category').value,
            priority: parseInt(document.getElementById('kb_priority').value) || 0,
            is_active: document.getElementById('kb_is_active').checked,
            csrf_token: document.querySelector('input[name=csrf_token]').value
        };
        try {
            var res = await fetch(apiList, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), credentials: 'same-origin' });
            if (!res.ok) {
                showAlert('Save failed: HTTP error ' + res.status, 'danger');
                return;
            }
            var data = await res.json();
            if (data.success) {
                showAlert('Knowledge slice saved successfully');
                var modalEl = document.getElementById('kbModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                fetchList();
            } else {
                showAlert('Save failed: ' + (data.error || 'Unknown error'), 'danger');
            }
        } catch (e) {
            console.error('Save failed:', e);
            showAlert('Save failed: ' + (e.message || 'Network error'), 'danger');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        fetchList();

        // Search and filter handlers
        var searchInput = document.getElementById('kbSearch');
        var filterCategory = document.getElementById('kbFilterCategory');
        var filterSelect = document.getElementById('kbFilterType');
        var showInactiveToggle = document.getElementById('kbShowInactive');
        var refreshBtn = document.getElementById('btnRefresh');

        if (searchInput) {
            searchInput.addEventListener('input', filterItems);
        }
        if (filterCategory) {
            filterCategory.addEventListener('change', filterItems);
        }
        if (filterSelect) {
            filterSelect.addEventListener('change', filterItems);
        }
        if (showInactiveToggle) {
            showInactiveToggle.addEventListener('change', function () {
                showInactive = this.checked;
                filterItems();
            });
        }
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                fetchList();
            });
        }

        // Add button handler
        document.getElementById('btnAddSlice').addEventListener('click', function () {
            document.getElementById('kbForm').reset();
            document.getElementById('kb_id').value = 0;
            document.getElementById('kb_source_type').value = 'text';
            document.getElementById('kb_category').value = '';
            document.getElementById('kb_priority').value = 0;
            document.getElementById('kb_is_active').checked = true;
            var modal = new bootstrap.Modal(document.getElementById('kbModal'));
            modal.show();
        });

        // Save button handler
        document.getElementById('kbSaveBtn').addEventListener('click', save);

        // Enter key in form
        document.getElementById('kbForm').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                save();
            }
        });
    });
})();
