import { escapeHtml, setText, toSafeId } from './core.js';

const byIdDefault = (id) => document.getElementById(id);

function debounce(fn, waitMs) {
    let timeout = null;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), waitMs);
    };
}

function toggleCheckedClass(selector) {
    document.querySelectorAll(selector).forEach((checkbox) => {
        checkbox.closest('.form-check')?.classList.toggle('checked', checkbox.checked);
        checkbox.addEventListener('change', function () {
            this.closest('.form-check')?.classList.toggle('checked', this.checked);
        });
    });
}

function getStatusClass(status) {
    return String(status || '').toLowerCase() === 'active' ? 'bg-success' : 'bg-warning';
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
            checkbox.closest('.form-check')?.classList.add('checked');
        });
    };

    window.deselectAll = function () {
        document.querySelectorAll('.permission-checkbox').forEach((checkbox) => {
            checkbox.checked = false;
            checkbox.closest('.form-check')?.classList.remove('checked');
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
            userResults.innerHTML = '<div class="alert alert-info">No users found.</div>';
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
                status
            });

            return `
                <button type="button"
                    class="list-group-item list-group-item-action cursor-pointer text-start"
                    data-user-id="${escapeHtml(userId)}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${escapeHtml(fullName)}</h6>
                            <small class="text-muted">${escapeHtml(username)} (${escapeHtml(email)})</small>
                        </div>
                        <span class="badge ${getStatusClass(status)}">${escapeHtml(status)}</span>
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
                userResults.innerHTML = '<div class="alert alert-danger">Failed to search users.</div>';
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
                    list.innerHTML = '<div class="alert alert-info mb-0">No roles assigned.</div>';
                    return;
                }

                list.innerHTML = roles.map((role) => `
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
                `).join('') + '<div class="clear-float"></div>';
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
                for (const [moduleName, perms] of Object.entries(permsData?.data || {})) {
                    html += `<h6 class="fw-bold text-primary mb-2 mt-3 module-header">${escapeHtml(moduleName).toUpperCase()}</h6>`;
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
                list.innerHTML = html || '<div class="alert alert-info">No permissions found.</div>';
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
            badge.className = `badge ${getStatusClass(status)}`;
            badge.textContent = String(status || 'unknown');
            statusEl.appendChild(badge);
        }

        if (userResults) userResults.style.display = 'none';
        if (userPanel) userPanel.style.display = 'block';
        loadUserRoles(id);
        loadUserPermissions(id);
    };

    window.removeUserRole = function (userId, roleId) {
        if (!confirm('Remove this role from the user?')) return;
        fetch(`/api/user-roles/${userId}/remove/${roleId}`, { method: 'POST' })
            .then((response) => response.json())
            .then((data) => {
                if (data?.success) {
                    loadUserRoles(userId);
                    loadUserPermissions(userId);
                    return;
                }
                alert('Error: ' + (data?.error || 'Unknown error'));
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
            alert('Please select a user first');
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
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="${escapeHtml(rawId)}" id="${escapeHtml(safeId)}">
                            <label class="form-check-label" for="${escapeHtml(safeId)}">
                                ${escapeHtml(role?.name || 'Unnamed role')}
                                <small class="text-muted d-block">${escapeHtml(role?.description || '')}</small>
                            </label>
                        </div>
                    `;
                }).join('');

                const modal = new bootstrap.Modal(byId('assignRoleModal'));
                modal.show();
            });
    }

    function confirmAssignRoles() {
        const checkedRoles = Array.from(
            document.querySelectorAll('#availableRolesCheckboxes input:checked')
        ).map((el) => el.value);
        if (checkedRoles.length === 0) {
            alert('Please select at least one role');
            return;
        }

        const formData = new FormData();
        checkedRoles.forEach((roleId) => formData.append('roles[]', roleId));

        fetch(`/api/user-roles/${selectedUserId}/assign-roles`, {
            method: 'POST',
            body: formData
        })
            .then((response) => response.json())
            .then((data) => {
                if (data?.success) {
                    bootstrap.Modal.getInstance(byId('assignRoleModal'))?.hide();
                    loadUserRoles(selectedUserId);
                    loadUserPermissions(selectedUserId);
                    alert('Roles assigned successfully!');
                    return;
                }
                alert('Error: ' + (data?.error || 'Unknown error'));
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
    toggleCheckedClass('.role-checkboxes .form-check input');
}

export function initUsersEditUser(options = {}) {
    const byId = options.byId || byIdDefault;
    const userEditData = byId('user-edit-data');
    if (!userEditData) return;

    const userId = parseInt(userEditData.dataset.userId || '0', 10);
    toggleCheckedClass('.role-checkboxes .form-check input');

    const permissionsEl = byId('userPermissions');
    if (!permissionsEl || !userId) return;

    function loadUserPermissions() {
        fetch(`/api/user-roles/${userId}`)
            .then((response) => response.json())
            .then((data) => {
                if (!data?.data || data.data.length === 0) {
                    permissionsEl.innerHTML = '<div class="alert alert-info mb-0">No roles assigned, no permissions available.</div>';
                    return;
                }
                return fetch('/api/rbac/permissions/grouped')
                    .then((response) => response.json())
                    .then((permsData) => {
                        let html = '';
                        for (const [moduleName, perms] of Object.entries(permsData?.data || {})) {
                            html += `<div class="mb-3"><strong class="text-primary text-uppercase-9">${escapeHtml(moduleName)}</strong></div>`;
                            (perms || []).forEach((perm) => {
                                html += `<div class="permission-badge">
                                    <div class="module">${escapeHtml(perm?.module || '')}</div>
                                    <div>${escapeHtml(perm?.name || '')}</div>
                                    <small class="text-muted">${escapeHtml(perm?.description || '')}</small>
                                </div>`;
                            });
                        }
                        permissionsEl.innerHTML = html || '<div class="alert alert-info mb-0">No permissions found.</div>';
                    });
            });
    }

    loadUserPermissions();
}
