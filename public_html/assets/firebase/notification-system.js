// Notification System JS

document.addEventListener('DOMContentLoaded', function () {

    const notificationBell = document.querySelector('[data-notification-bell]');
    const notificationDropdown = document.querySelector('[data-notification-dropdown]');
    const notificationList = document.querySelector('[data-notification-list]');
    const userId = notificationList ? notificationList.dataset.userId : null;

    if (!notificationBell || !notificationDropdown) return;

    // Toggle dropdown on bell click
    notificationBell.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const isOpen = notificationDropdown.classList.contains('show');

        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            if (menu !== notificationDropdown) {
                menu.classList.remove('show');
            }
        });

        // Toggle this dropdown
        if (isOpen) {
            notificationDropdown.classList.remove('show');
            notificationBell.setAttribute('aria-expanded', 'false');
        } else {
            notificationDropdown.classList.add('show');
            notificationBell.setAttribute('aria-expanded', 'true');
            loadNotifications();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('show');
            notificationBell.setAttribute('aria-expanded', 'false');
        }
    });

    // Load notifications
    function loadNotifications() {
        if (!userId || !notificationList) return;

        fetch('/api/user/notifications', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications) {
                    renderNotifications(data.notifications);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    // Render notifications
    function renderNotifications(notifications) {
        if (!notificationList) return;

        notificationList.innerHTML = '';

        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="text-center py-4 text-muted"><i class="bi icon-inbox fs-4"></i><p class="mb-0 mt-2 small">No new notifications</p></div>';
            return;
        }

        notifications.forEach(notif => {
            const entry = document.createElement('div');
            entry.className = `notification-entry p-2 mb-2 rounded ${notif.is_read == 0 ? 'bg-light border-start border-primary border-2' : ''}`;
            entry.innerHTML = `
                <div class="d-flex align-items-start gap-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold small mb-1">${notif.title}</div>
                        <div class="small text-muted mb-1">${notif.message}</div>
                        <div class="small text-secondary">${new Date(notif.created_at).toLocaleString()}</div>
                    </div>
                    ${notif.is_read == 0 ? '<button type="button" class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-600 text-indigo-600 hover:bg-indigo-50 transition-colors" data-action="mark-read" data-notification-id="' + notif.id + '">Read</button>' : ''}
                </div>
            `;
            notificationList.appendChild(entry);
        });

        // Add event listeners for mark as read
        notificationList.querySelectorAll('[data-action="mark-read"]').forEach(btn => {
            btn.addEventListener('click', function () {
                const notifId = this.dataset.notificationId;
                markAsRead(notifId);
            });
        });
    }

    // Mark as read
    function markAsRead(notificationId) {
        fetch('/api/notification/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(); // Reload
                    updateBadge();
                }
            })
            .catch(error => console.error('Error marking as read:', error));
    }

    // Update badge
    function updateBadge() {
        fetch('/api/user/notifications/count', {
            method: 'GET',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('[data-notification-badge]');
                const count = document.querySelector('[data-notification-count]');
                if (badge && count) {
                    if (data.count > 0) {
                        count.textContent = data.count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    }

});
