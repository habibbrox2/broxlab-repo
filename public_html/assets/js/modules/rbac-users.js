import { escapeHtml, setText, toSafeId } from './core.js';
import { debounce } from '../shared/utils.js';

const byIdDefault = (id) => document.getElementById(id);

function toggleCheckedClass(selector) {
  document.querySelectorAll(selector).forEach((checkbox) => {
    checkbox.closest('.checkbox-wrapper')?.classList.toggle('checked', checkbox.checked);
    checkbox.addEventListener('change', function () {
      this.closest('.checkbox-wrapper')?.classList.toggle('checked', this.checked);
    });
  });
}

function getStatusClass(status) {
  return String(status || '').toLowerCase() === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
}

function formatDate(dateValue) {
  const date = new Date(dateValue);
  if (Number.isNaN(date.getTime())) return String(dateValue || '-');
  return date.toLocaleDateString();
}

export function initRbacRolesEdit() {
  if (!document.querySelector('.permission-checkbox')) return;

  window.selectAll = function () {
    document.querySelectorAll('.permission-checkbox').forEach((checkbox) => {
      checkbox.checked = true;
      checkbox.closest('.checkbox-wrapper')?.classList.add('checked');
    });
  };

  window.deselectAll = function () {
    document.querySelectorAll('.permission-checkbox').forEach((checkbox) => {
      checkbox.checked = false;
      checkbox.closest('.checkbox-wrapper')?.classList.remove('checked');
    });
  };

  toggleCheckedClass('.permission-checkbox');
}

export function initRbacUserRoles(options = {}) {
  const byId = options.byId || byIdDefault;
  const userSearch = byId('userSearch');
  if (!userSearch) return;
  if (userSearch.dataset.rbacUserRolesBound === '1') return;
  userSearch.dataset.rbacUserRolesBound = '1';

  let selectedUserId = null;
  const userResults = byId('userResults');
  const userPanel = byId('userPanel');
  const userLookup = new Map();

  function renderUserRows(users) {
    if (!userResults) return;
    if (!Array.isArray(users) || users.length === 0) {
      userLookup.clear();
      userResults.innerHTML = '<div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200">No users found.</div>';
      userResults.style.display = 'block';
      return;
    }

    userLookup.clear();
    userResults.innerHTML = users.map((user) => {
      const userId = String(user?.id ?? '').trim();
      if (!userId) return '';

      const firstName = String(user?.first_name || '').trim();
      const lastName = String(user?.last_name || '').trim();
      const fullName = `${firstName} ${lastName}`.trim() || String(user?.username || 'Unknown');
      const username = String(user?.username || '');
      const email = String(user?.email || '');
      const created = String(user?.created_at || '');
      const status = String(user?.status || 'unknown');

      userLookup.set(userId, {
        id: Number(user?.id) || userId,
        username,
        email,
        name: fullName,
        created,
        status,
      });

      return `
                <button type="button"
                    class="flex items-start gap-3 w-full px-4 py-3 text-left hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-b-0 cursor-pointer"
                    data-user-id="${escapeHtml(userId)}">
                    <div class="flex justify-between items-start w-full">
                        <div>
                            <h6 class="mb-1">${escapeHtml(fullName)}</h6>
                            <small class="text-slate-500">${escapeHtml(username)} (${escapeHtml(email)})</small>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusClass(status)}">${escapeHtml(status)}</span>
                    </div>
                </button>
            `;
    }).join('');
    userResults.style.display = 'block';
  }

  function searchUsers() {
    const query = userSearch.value.trim();
    if (query.length < 2) {
      if (userResults) userResults.style.display = 'none';
      return;
    }

    fetch(`/api/users/search?q=${encodeURIComponent(query)}`)
      .then((response) => response.json())
      .then((data) => {
        renderUserRows(Array.isArray(data?.data) ? data.data : []);
      })
      .catch(() => {
        if (!userResults) return;
        userResults.innerHTML = '<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200">Failed to search users.</div>';
        userResults.style.display = 'block';
      });
  }

  function loadUserRoles(userId) {
    fetch(`/api/user-roles/${userId}`)
      .then((response) => response.json())
      .then((data) => {
        const list = byId('rolesList');
        if (!list) return;

        const roles = Array.isArray(data?.data) ? data.data : [];
        if (roles.length === 0) {
          list.innerHTML = '<div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 mb-0">No roles assigned.</div>';
          return;
        }

        list.innerHTML = `${roles.map((role) => `
                    <div class="role-badge">
                        <span>${escapeHtml(role?.name || 'Unnamed role')}</span>
                        <span
                            class="remove-btn"
                            data-role-id="${escapeHtml(role?.id)}"
                            data-user-id="${escapeHtml(userId)}"
                            aria-label="Remove role">
                            &times;
                        </span>
                    </div>
                `).join('') }<div class="clear-float"></div>`;
      });
  }

  function loadUserPermissions(userId) {
    fetch(`/api/user-roles/${userId}`)
      .then((response) => response.json())
      .then(() => fetch('/api/rbac/permissions/grouped'))
      .then((response) => response.json())
      .then((permsData) => {
        const list = byId('permissionsList');
        if (!list) return;

        let html = '';
        for (const [moduleName, perms,] of Object.entries(permsData?.data || {})) {
          html += `<h6 class="font-bold text-indigo-600 mb-2 mt-3 module-header">${escapeHtml(moduleName).toUpperCase()}</h6>`;
          html += '<div class="permissions-grid">';
          (perms || []).forEach((perm) => {
            html += `
                            <div class="permission-card">
                                <div class="permission-module">${escapeHtml(perm?.module || '')}</div>
                                <div class="permission-name">${escapeHtml(perm?.name || '')}</div>
                                <div class="permission-desc">${escapeHtml(perm?.description || 'N/A')}</div>
                            </div>
                        `;
          });
          html += '</div>';
        }
        list.innerHTML = html || '<div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200">No permissions found.</div>';
      });
  }

  window.selectUser = function (id, username, email, name, created, status) {
    selectedUserId = id;

    setText(byId('selectedUserName'), name);
    setText(byId('selectedUsername'), username);
    setText(byId('selectedUserEmail'), email);
    setText(byId('selectedUserCreated'), formatDate(created));

    const statusEl = byId('selectedUserStatus');
    if (statusEl) {
      statusEl.innerHTML = '';
      const badge = document.createElement('span');
      badge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusClass(status)}`;
      badge.textContent = String(status || 'unknown');
      statusEl.appendChild(badge);
    }

    if (userResults) userResults.style.display = 'none';
    if (userPanel) userPanel.style.display = 'block';
    loadUserRoles(id);
    loadUserPermissions(id);
  };

  window.removeUserRole = async function (userId, roleId) {
    if (!(await window.showConfirm('Remove this role from the user?'))) return;
    fetch(`/api/user-roles/${userId}/remove/${roleId}`, { method: 'POST', })
      .then((response) => response.json())
      .then((data) => {
        if (data?.success) {
          loadUserRoles(userId);
          loadUserPermissions(userId);
          return;
        }
        window.showMessage(`Error: ${ data?.error || 'Unknown error'}`, 'danger');
      });
  };

  const rolesList = byId('rolesList');
  if (rolesList && rolesList.dataset.roleRemoveBound !== '1') {
    rolesList.dataset.roleRemoveBound = '1';
    rolesList.addEventListener('click', (event) => {
      const removeBtn = event.target.closest('.remove-btn[data-role-id][data-user-id]');
      if (!removeBtn) return;
      window.removeUserRole(removeBtn.dataset.userId, removeBtn.dataset.roleId);
    });
  }

  function showAssignRoleModal() {
    if (!selectedUserId) {
      window.showMessage('Please select a user first', 'warning');
      return;
    }
    fetch('/api/rbac/roles')
      .then((response) => response.json())
      .then((data) => {
        const list = byId('availableRolesCheckboxes');
        if (!list) return;
        list.innerHTML = (data?.data || []).map((role) => {
          const rawId = String(role?.id || '');
          const safeId = toSafeId(`role_${rawId}`) || `role_${rawId}`;
          return `
                        <div class="checkbox-wrapper flex items-start gap-2 mb-2">
                            <input class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mt-0.5" type="checkbox" value="${escapeHtml(rawId)}" id="${escapeHtml(safeId)}">
                            <label class="text-sm cursor-pointer" for="${escapeHtml(safeId)}">
                                ${escapeHtml(role?.name || 'Unnamed role')}
                                <small class="text-slate-500 block">${escapeHtml(role?.description || '')}</small>
                            </label>
                        </div>
                    `;
        }).join('');

        const modal = new broxUI.Modal(byId('assignRoleModal'));
        modal.show();
      });
  }

  function confirmAssignRoles() {
    const checkedRoles = Array.from(
      document.querySelectorAll('#availableRolesCheckboxes input:checked')
    ).map((el) => el.value);
    if (checkedRoles.length === 0) {
      window.showMessage('Please select at least one role', 'warning');
      return;
    }

    const formData = new FormData();
    checkedRoles.forEach((roleId) => formData.append('roles[]', roleId));

    fetch(`/api/user-roles/${selectedUserId}/assign-roles`, {
      method: 'POST',
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data?.success) {
          broxUI.Modal.getInstance(byId('assignRoleModal'))?.hide();
          loadUserRoles(selectedUserId);
          loadUserPermissions(selectedUserId);
          window.showMessage('Roles assigned successfully!', 'success');
          return;
        }
        window.showMessage(`Error: ${ data?.error || 'Unknown error'}`, 'danger');
      });
  }

  if (userResults && userResults.dataset.userSelectBound !== '1') {
    userResults.dataset.userSelectBound = '1';
    userResults.addEventListener('click', (event) => {
      const row = event.target.closest('[data-user-id]');
      if (!row) return;
      const record = userLookup.get(String(row.dataset.userId || ''));
      if (!record) return;
      window.selectUser(
        record.id,
        record.username,
        record.email,
        record.name,
        record.created,
        record.status
      );
    });
  }

  byId('assignRoleBtn')?.addEventListener('click', showAssignRoleModal);
  byId('confirmAssignBtn')?.addEventListener('click', confirmAssignRoles);
  byId('clearUserBtn')?.addEventListener('click', () => {
    selectedUserId = null;
    if (userPanel) userPanel.style.display = 'none';
    if (userResults) userResults.style.display = 'none';
  });
  userSearch.addEventListener('keyup', debounce(searchUsers, 300));
}

export function initUsersAddUser() {
  if (!document.querySelector('.role-checkboxes')) return;
  toggleCheckedClass('.role-checkboxes .checkbox-wrapper input');
}

export function initRbacPermissionsList() {
  const searchBox = document.getElementById('searchBox');
  if (!searchBox) return;
  searchBox.addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('.permission-row').forEach((row) => {
      row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
  });
}

export function initUsersEditUser(options = {}) {
  const byId = options.byId || byIdDefault;
  const userEditData = byId('user-edit-data');
  if (!userEditData) return;

  const userId = parseInt(userEditData.dataset.userId || '0', 10);
  toggleCheckedClass('.role-checkboxes .checkbox-wrapper input');

  const permissionsEl = byId('userPermissions');
  if (!permissionsEl || !userId) return;

  function loadUserPermissions() {
    fetch(`/api/user-roles/${userId}`)
      .then((response) => response.json())
      .then((data) => {
        if (!data?.data || data.data.length === 0) {
          permissionsEl.innerHTML = '<div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 mb-0">No roles assigned, no permissions available.</div>';
          return;
        }
        return fetch('/api/rbac/permissions/grouped')
          .then((response) => response.json())
          .then((permsData) => {
            let html = '';
            for (const [moduleName, perms,] of Object.entries(permsData?.data || {})) {
              html += `<div class="mb-3"><strong class="text-indigo-600 uppercase">${escapeHtml(moduleName)}</strong></div>`;
              (perms || []).forEach((perm) => {
                html += `<div class="permission-badge">
                                    <div class="module">${escapeHtml(perm?.module || '')}</div>
                                    <div>${escapeHtml(perm?.name || '')}</div>
                                    <small class="text-slate-500">${escapeHtml(perm?.description || '')}</small>
                                </div>`;
              });
            }
            permissionsEl.innerHTML = html || '<div class="p-4 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 mb-0">No permissions found.</div>';
          });
      });
  }

  loadUserPermissions();
}
