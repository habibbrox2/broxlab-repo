/**
 * Services Admin Module
 * Lazy-loaded via loadAdminModule("services").
 * Handles services forms, index/deletion, and applications dashboard.
 * Only loaded when visiting /admin/services/* pages.
 */

export function initServicesForms({ byId, parseJson, }) {
  const dataEl = byId('service-form-data');
  if (!dataEl) return;
  const serviceCategoryIds = parseJson(dataEl.dataset.serviceCategoryIds, []);
  const serviceTagIds = parseJson(dataEl.dataset.serviceTagIds, []);
  const serviceAllTags = parseJson(dataEl.dataset.serviceAllTags, []);
  const serviceAllCategories = parseJson(dataEl.dataset.serviceAllCategories, []);
  const excludeId = dataEl.dataset.excludeId ? parseInt(dataEl.dataset.excludeId, 10) : null;

  window.serviceCategoryIds = serviceCategoryIds;
  window.serviceTagIds = serviceTagIds;
  window.serviceAllTags = serviceAllTags;
  window.serviceAllCategories = serviceAllCategories;
  window.serviceExcludeId = excludeId;

  if (typeof window.initializeServiceSlugGenerator === 'function') {
    window.initializeServiceSlugGenerator(excludeId);
  }

  if (window.adminContent?.fetchCategories) {
    window.adminContent.fetchCategories(serviceCategoryIds, '#service_categories');
  }
  if (window.adminContent?.initializeCategoriesSelect) {
    window.adminContent.initializeCategoriesSelect('#service_categories');
  }
  if (window.adminContent?.fetchTags) {
    window.adminContent.fetchTags(serviceTagIds, '#tags');
  }
  if (window.adminContent?.initializeTagsSelect) {
    window.adminContent.initializeTagsSelect('#tags');
  }

  const iconUploadBtn = byId('iconUploadBtn');
  const iconUploadInput = byId('iconUploadInput');
  const iconPreview = byId('iconPreview');
  const iconPreviewContainer = byId('iconPreviewContainer');
  const iconInput = byId('iconInput');
  const removeIconBtn = byId('removeIconBtn');

  iconUploadBtn?.addEventListener('click', () => iconUploadInput?.click());
  iconUploadInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = function (event) {
        if (iconPreview) iconPreview.src = event.target.result;
        iconPreviewContainer?.classList.remove('hidden');
        if (iconInput) iconInput.value = file.name;
      };
      reader.readAsDataURL(file);
    }
  });
  removeIconBtn?.addEventListener('click', () => {
    if (iconUploadInput) iconUploadInput.value = '';
    if (iconInput) iconInput.value = '';
    iconPreviewContainer?.classList.add('hidden');
  });

  const dropZone = byId('dropZone');
  const imageUploadInput = byId('imageUploadInput');
  const imagePreviewContainer = byId('imagePreviewContainer');

  function handleImageFiles(files) {
    Array.from(files).forEach((file, index) => {
      if (file.type.startsWith('image/')) {
        if (file.size > 10 * 1024 * 1024) {
          alert(`Image "${file.name}" is too large (max 10MB)`);
          return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
          const previewId = `preview-${Date.now()}-${index}`;
          const fileKey = `${file.name}::${file.size}::${file.lastModified}`;
          const previewHTML = `
                            <div class="w-full md:w-1/2 lg:w-full xl:w-1/2 mb-3" id="${previewId}-container" data-file-key="${fileKey}">
                                <div class="admin-panel-card border shadow-sm h-100">
                                    <img src="${event.target.result}" class="" style="height: 150px; object-fit: cover;" alt="Preview">
                                    <divclass="p-3">
                            <h6 class="text-sm font-semibold text-slate-900 truncate mb-2" title="${file.name}">
                                            <i class="lucide lucide-image text-indigo-500 mr-1" style="width:1rem;height:1rem;"></i>${file.name}
                                        </h6>
                                        <p class="text-sm text-slate-500 mb-2">
                                            <i class="lucide lucide-file mr-1" style="width:1rem;height:1rem;"></i>
                                            ${(file.size / 1024).toFixed(2)} KB
                                        </p>
                                        <button type="button" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg w-full text-xs font-medium bg-red-600 text-white hover:bg-red-700 transition-colors" onclick="removePreview('${previewId}-container')">
                                            <i class="lucide lucide-trash-2 mr-1" style="width:1rem;height:1rem;"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
          imagePreviewContainer?.insertAdjacentHTML('beforeend', previewHTML);
        };
        reader.readAsDataURL(file);
      }
    });
  }

  window.removePreview = function (containerId) {
    const element = byId(containerId);
    if (!element) return;
    const fileKey = element.getAttribute('data-file-key');
    if (fileKey && imageUploadInput?.files?.length) {
      const dt = new DataTransfer();
      Array.from(imageUploadInput.files).forEach((f) => {
        const k = `${f.name}::${f.size}::${f.lastModified}`;
        if (k !== fileKey) dt.items.add(f);
      });
      imageUploadInput.files = dt.files;
    }
    element.remove();
  };

  dropZone?.addEventListener('click', () => imageUploadInput?.click());
  dropZone?.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
  });
  dropZone?.addEventListener('dragleave', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
  });
  dropZone?.addEventListener('drop', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
    const files = e.dataTransfer.files;
    handleImageFiles(files);
  });
  imageUploadInput?.addEventListener('change', (e) => {
    handleImageFiles(e.target.files);
  });

  window.removeMetadata = function (btn) {
    btn.closest('.row')?.remove();
  };

  byId('addMetadataBtn')?.addEventListener('click', () => {
    const html = `
                <div class="flex flex-wrap -mx-2 mb-2 items-center">
                    <div class="w-full md:w-5/12 px-3">
                        <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Key" data-metadata-key>
                    </div>
                    <div class="w-full md:w-1/2 px-3">
                        <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Value" data-metadata-value>
                    </div>
                    <div class="w-full md:w-1/12 px-3">
                        <button type="button" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-red-200 w-100" onclick="removeMetadata(this)">                                            <i class="lucide lucide-x" style="width:1rem;height:1rem;"></i>
                        </button>
                    </div>
                </div>
            `;
    byId('metadataFields')?.insertAdjacentHTML('beforeend', html);
  });

  window.removeFormField = function (btn) {
    btn.closest('.form-field-item')?.remove();
  };

  byId('addFormFieldBtn')?.addEventListener('click', () => {
    const html = `
                <div class="admin-panel-card mb-2 border form-field-item">
                    <divclass="p-3">
                        <div class="flex flex-wrap -mx-2 items-center">
                            <div class="w-full md:w-1/4 px-3 mb-3">
                                <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Field Name" data-field-label>
                            </div>
                            <div class="w-full md:w-1/6 px-3">
                                <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" data-field-type>
                                    <option value="text">Text</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Select</option>
                                    <option value="date">Date</option>
                                </select>
                            </div>
                            <div class="w-full md:w-1/6 px-3">
                                <div class="flex items-center gap-2">
                                    <input class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" type="checkbox" data-field-required>
                                    <label class="text-sm text-slate-600">Required</label>
                                </div>
                            </div>
                            <div class="w-full md:w-1/3 px-3">
                                <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" placeholder="Placeholder text" data-field-placeholder>
                            </div>
                            <div class="w-full md:w-1/12 px-3">
                                <button type="button" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-red-200 w-100" onclick="removeFormField(this)">
                                    <i class="lucide lucide-trash-2" style="width:1rem;height:1rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
    byId('formFieldsContainer')?.insertAdjacentHTML('beforeend', html);
  });

  window.removeImage = function (btn) {
    const item = btn.closest('.image-item');
    if (!item) return;
    const imageId = item.getAttribute('data-image-id');
    if (imageId) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'deleted_image_ids[]';
      input.value = imageId;
      byId('serviceForm')?.appendChild(input);
    }
    item.remove();
  };

  const serviceForm = byId('serviceForm');
  serviceForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const editor = window.editor_content;
    const hiddenDescriptionInput = byId('content-input');
    const contentEl = byId('content');
    const fallbackContent = contentEl
      ? typeof contentEl.value === 'string'
        ? contentEl.value
        : contentEl.innerHTML || ''
      : '';
    const descriptionHtml =
        editor && typeof editor.getContent === 'function'
          ? editor.getContent() || ''
          : hiddenDescriptionInput?.value || fallbackContent || '';
    const descriptionText = String(descriptionHtml || '')
      .replace(/<[^>]+>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .trim();

    if (!descriptionText) {
      window.showMessage?.('Service description is required', 'danger');
      return;
    }

    if (hiddenDescriptionInput) {
      hiddenDescriptionInput.value = descriptionHtml;
    }

    const metadata = {};
    document.querySelectorAll('#metadataFields [data-metadata-key]').forEach((keyInput) => {
      const key = keyInput.value;
      const value =
          keyInput.parentElement.parentElement.querySelector('[data-metadata-value]')?.value;
      if (key) metadata[key] = value;
    });

    const formFields = [];
    document.querySelectorAll('#formFieldsContainer .form-field-item').forEach((item) => {
      const labelInput = item.querySelector('[data-field-label]');
      const typeSelect = item.querySelector('[data-field-type]');
      const requiredCheckbox = item.querySelector('[data-field-required]');
      const placeholderInput = item.querySelector('[data-field-placeholder]');
      if (labelInput && labelInput.value.trim()) {
        formFields.push({
          label: labelInput.value.trim(),
          field_type: typeSelect ? typeSelect.value : 'text',
          required: requiredCheckbox ? (requiredCheckbox.checked ? 1 : 0) : 0,
          placeholder: placeholderInput ? placeholderInput.value.trim() : '',
        });
      }
    });

    const imageUpdates = [];
    document.querySelectorAll('.image-item').forEach((item) => {
      const id = item.getAttribute('data-image-id');
      if (!id) return;
      const altInput = item.querySelector('[data-field="alt_text"]');
      const captionInput = item.querySelector('[data-field="caption"]');
      const orderInput = item.querySelector('[data-field="display_order"]');
      const alt = altInput ? altInput.value.trim() : '';
      const caption = captionInput ? captionInput.value.trim() : '';
      const displayOrder = orderInput ? parseInt(orderInput.value || 0, 10) : 0;
      const featuredRadio = document.querySelector('input[name="featured_image"]:checked');
      const isFeatured = featuredRadio && featuredRadio.value === id ? 1 : 0;

      imageUpdates.push({
        id: parseInt(id, 10),
        alt_text: alt,
        caption: caption,
        display_order: displayOrder,
        is_featured: isFeatured,
      });
    });

    const formData = new FormData(serviceForm);
    formData.append('metadata', JSON.stringify(metadata));
    formData.append('form_fields', JSON.stringify(formFields));
    formData.append('image_updates', JSON.stringify(imageUpdates));

    try {
      const endpoint = formData.get('service_id')
        ? '/admin/services/update'
        : '/admin/services/create';
      const response = await fetch(endpoint, { method: 'POST', body: formData, });
      const data = await response.json();
      if (data.success) {
        window.showMessage?.(data.message || 'Service saved successfully!', 'success');
        setTimeout(() => {
          window.location.href = `/admin/services/details/${data.service_id || formData.get('service_id')}`;
        }, 2000);
      } else {
        window.showMessage?.(data.message || 'Failed to save service', 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      window.showMessage?.('An error occurred. Please try again.', 'danger');
    }
  });
}

export function initServicesIndex({ byId, getCsrfToken, }) {
  const deleteModal = byId('deleteModal');
  if (!deleteModal) return;
  const csrfToken = getCsrfToken();
  let deleteServiceId = null;
  const modal = new broxUI.Modal(deleteModal);

  document.querySelectorAll('.delete-service').forEach((btn) => {
    btn.addEventListener('click', function () {
      deleteServiceId = this.dataset.id;
      modal.show();
    });
  });

  byId('confirmDelete')?.addEventListener('click', () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/services/details/${deleteServiceId}/delete`;
    form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}">`;
    document.body.appendChild(form);
    form.submit();
  });
}

export function initServicesApplications({ byId, escapeHtml, }) {
  const listView = byId('listView');
  if (!listView) return;

  let currentPage = 1;
  const pageSize = 20;

  const viewToggle = document.querySelectorAll('input[name="view"]');
  const dashboardView = byId('dashboardView');

  viewToggle.forEach((radio) => {
    radio.addEventListener('change', function () {
      if (this.value === 'list') {
        listView.style.display = 'block';
        if (dashboardView) dashboardView.style.display = 'none';
        loadApplications();
      } else {
        listView.style.display = 'none';
        if (dashboardView) dashboardView.style.display = 'block';
        loadDashboard();
      }
    });
  });

  async function loadApplications() {
    const status = byId('filterStatus')?.value || '';
    const priority = byId('filterPriority')?.value || '';
    const dateFrom = byId('filterDateFrom')?.value || '';
    const dateTo = byId('filterDateTo')?.value || '';

    const params = new URLSearchParams({
      limit: pageSize,
      offset: (currentPage - 1) * pageSize,
    });
    if (status) params.set('status', status);
    if (priority) params.set('priority', priority);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    try {
      const response = await fetch(`/api/admin/applications?${params}`);
      const data = await response.json();
      if (data.success) {
        renderApplicationsTable(data.data);
        updateStats();
        updatePagination(data.total);
      }
    } catch (error) {
      console.error('Error loading applications:', error);
      byId('applicationsTable').innerHTML =
          '<tr><td colspan="8" class="text-center text-red-600 py-4">Failed to load applications</td></tr>';
    }
  }

  function renderApplicationsTable(apps) {
    const html = apps
      .map(
        (app) => `
                <tr>
                    <td class="pl-4"><strong>#${escapeHtml(String(app.id))}</strong></td>
                    <td>${escapeHtml(app.user_name)} <br><small class="text-slate-500">${escapeHtml(app.user_email)}</small></td>
                    <td>${escapeHtml(app.service_name)}</td>
                    <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(app.status)}">${escapeHtml(app.status)}</span></td>
                    <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getPriorityBadgeClass(app.priority)}">${escapeHtml(app.priority)}</span></td>
                    <td class="text-sm text-slate-500">${new Date(app.created_at).toLocaleDateString()}</td>
                    <td>${escapeHtml(app.approved_by_name || '--')}</td>
                    <td>
                        <button class="modern-btn text-xs px-2.5 py-1 border border-indigo-600 text-indigo-600 hover:bg-indigo-50 rounded-lg" onclick="viewApplication(${escapeHtml(String(app.id))})">
                            <i class="lucide lucide-eye" style="width:1rem;height:1rem;"></i> View
                        </button>
                    </td>
                </tr>
            `
      )
      .join('');

    byId('applicationsTable').innerHTML = html;
  }

  function getStatusBadgeClass(status) {
    const classes = {
      pending: 'bg-amber-100 text-amber-800',
      processing: 'bg-sky-100 text-sky-800',
      approved: 'bg-emerald-100 text-emerald-800',
      rejected: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-slate-100 text-slate-800';
  }

  function getPriorityBadgeClass(priority) {
    const classes = {
      low: 'bg-slate-100 text-slate-800',
      normal: 'bg-sky-100 text-sky-800',
      high: 'bg-red-100 text-red-800',
    };
    return classes[priority] || 'bg-slate-100 text-slate-800';
  }

  async function updateStats() {
    try {
      const response = await fetch('/api/admin/applications/stats');
      const data = await response.json();
      if (data.success) {
        byId('stat-total').textContent = data.data.total;
        byId('stat-pending').textContent = data.data.pending;
        byId('stat-approved').textContent = data.data.approved;
        byId('stat-rejected').textContent = data.data.rejected;
      }
    } catch (error) {
      console.error('Error loading stats:', error);
    }
  }

  window.viewApplication = async function (appId) {
    try {
      const response = await fetch(`/api/admin/applications/${appId}`);
      const data = await response.json();
      if (data.success) {
        renderApplicationDetail(data.data);
        const modal = new broxUI.Modal(byId('detailModal'));
        modal.show();
      }
    } catch (error) {
      console.error('Error loading application:', error);
      window.showMessage?.('Failed to load application details', 'danger');
    }
  };

  function renderApplicationDetail(app) {
    const content = `
                <div class="flex flex-wrap -mx-3 mb-3">
                    <div class="w-full md:w-1/2 px-3 mb-3">
                        <div class="text-sm text-slate-500 mb-1">User</div>
                        <div class="font-bold text-slate-900">${escapeHtml(app.user.username)}</div>
                        <div class="text-sm text-slate-500">${escapeHtml(app.user.email)}</div>
                    </div>
                    <div class="w-full md:w-1/2 px-3 mb-3">
                        <div class="text-sm text-slate-500 mb-1">Service</div>
                        <div class="font-bold text-slate-900">${escapeHtml(app.service.name)}</div>
                        <div class="text-sm text-slate-500">${app.service.categories ? app.service.categories.map((c) => escapeHtml(c.name)).join(', ') : '-'}</div>
                    </div>
                </div>

                <hr>

                <div class="flex flex-wrap -mx-3 mb-3">
                    <div class="w-full md:w-1/4 px-3 mb-3">
                        <div class="text-sm text-slate-500">Status</div>
                        <select class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 bg-white shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20" id="appStatus">
                            <option value="pending" ${app.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="processing" ${app.status === 'processing' ? 'selected' : ''}>Processing</option>
                            <option value="approved" ${app.status === 'approved' ? 'selected' : ''}>Approved</option>
                            <option value="rejected" ${app.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4 px-3 mb-3">
                        <div class="text-sm text-slate-500">Priority</div>
                        <select class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 bg-white shadow-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20" id="appPriority">
                            <option value="low" ${app.priority === 'low' ? 'selected' : ''}>Low</option>
                            <option value="normal" ${app.priority === 'normal' ? 'selected' : ''}>Normal</option>
                            <option value="high" ${app.priority === 'high' ? 'selected' : ''}>High</option>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4 px-3 mb-3">
                        <div class="text-sm text-slate-500">Activated</div>
                        <div class="flex items-center gap-2">
                            <input class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" type="checkbox" id="appActivated" ${app.service_activated ? 'checked' : ''}>
                        </div>
                    </div>
                    <div class="w-full md:w-1/4 px-3 mb-3">
                        <div class="text-sm text-slate-500">Submitted</div>
                        <div>${new Date(app.created_at).toLocaleDateString()}</div>
                    </div>
                </div>

                <hr>

<div class="mb-3">
                    <label class="text-sm text-slate-500 uppercase font-medium">Application Data</label>
                    <pre class="bg-slate-100 p-3 rounded-lg small"><code>${JSON.stringify(app.application_data, null, 2)}</code></pre>
                </div>

                ${app.status === 'rejected' && app.rejection_reason
    ? `
                <div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 rounded-lg">
                    <strong>Rejection Reason:</strong>
                    <p class="mb-0">${app.rejection_reason}</p>
                </div>
            `
    : ''
}

                <div class="mb-3">
                    <label class="text-sm text-slate-500 uppercase font-medium">Admin Notes</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" id="appAdminNotes" rows="4">${app.admin_notes || ''}</textarea>
                </div>

                ${app.status === 'rejected'
    ? `
                <div class="mb-3">
                    <label class="text-sm text-slate-500 uppercase font-medium">Rejection Reason</label>
                    <input type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" id="appRejectionReason" value="${app.rejection_reason || ''}">
                </div>
            `
    : ''
}

                <div class="mt-4">
                    <h6 class="font-bold text-slate-900 mb-2">Audit Log</h6>
                    <div class="timeline small">
                        ${app.audit_log
    .map((log) => `
                            <div class="mb-2">
                                <div class="text-slate-500"><small>${new Date(log.created_at).toLocaleString()}</small></div>
                                <div><strong>${log.action_type}</strong>: ${log.description}</div>
                            </div>
                        `
    )
    .join('')}
                    </div>
                </div>
            `;

    byId('detailContent').innerHTML = content;
    window.currentAppId = app.id;
  }

  byId('applyFilters')?.addEventListener('click', () => {
    currentPage = 1;
    loadApplications();
  });

  byId('clearFilters')?.addEventListener('click', () => {
    byId('filterStatus').value = '';
    byId('filterPriority').value = '';
    byId('filterDateFrom').value = '';
    byId('filterDateTo').value = '';
    currentPage = 1;
    loadApplications();
  });

  function updatePagination(total) {
    const maxPages = Math.ceil(total / pageSize);
    const container = byId('paginationContainer');
    if (!container) return;
    if (maxPages > 1) {
      container.style.display = 'block';
      byId('pageInfo').textContent = `Page ${currentPage} of ${maxPages}`;
      byId('prevPage').onclick = (e) => {
        e.preventDefault();
        if (currentPage > 1) {
          currentPage--;
          loadApplications();
        }
      };
      byId('nextPage').onclick = (e) => {
        e.preventDefault();
        if (currentPage < maxPages) {
          currentPage++;
          loadApplications();
        }
      };
    } else {
      container.style.display = 'none';
    }
  }

  async function loadDashboard() {
    await updateStats();
  }

  loadApplications();
}
