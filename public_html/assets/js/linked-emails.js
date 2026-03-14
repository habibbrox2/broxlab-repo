/**
 * Linked Emails Management
 * Handles linking, unlinking, and managing additional email addresses
 */

export function initLinkedEmails(options = {}) {
    const containerSelector = options.containerSelector || '#linked-emails-container';
    const formSelector = options.formSelector || '#link-email-form';
    const emailInputSelector = options.emailInputSelector || '#new-email';
    const messageSelector = options.messageSelector || '#link-email-message';
    const csrfTokenSelector = options.csrfTokenSelector || null;

    const container = document.querySelector(containerSelector);
    const form = document.querySelector(formSelector);
    const emailInput = document.querySelector(emailInputSelector);
    const messageDiv = document.querySelector(messageSelector);

    if (!container && !form) return null;

    // Helper: Get CSRF token
    const getCsrfToken = () => {
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (metaToken) return metaToken;
        if (!csrfTokenSelector) return '';
        const csrfTokenEl = document.querySelector(csrfTokenSelector);
        return csrfTokenEl?.value || csrfTokenEl?.content || '';
    };



    // Fetch and display linked emails
    const loadLinkedEmails = async () => {
        if (!container) return;

        try {
            const response = await fetch('/api/user/linked-emails', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error('Failed to load linked emails');
            }

            const data = await response.json();
            const emails = data?.data || [];

            renderLinkedEmails(emails);
        } catch (error) {
            console.error('Error loading linked emails:', error);
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Could not load linked emails. Please refresh the page.
                </div>
            `;
        }
    };

    // Render linked emails
    const renderLinkedEmails = (emails) => {
        if (!container) return;

        if (!emails || emails.length === 0) {
            container.innerHTML = `
                <div class="alert alert-secondary">
                    <i class="bi bi-info-circle me-2"></i>
                    No additional emails linked yet. Add one below to strengthen your account security.
                </div>
            `;
            return;
        }

        let html = '<div class="linked-emails-list">';

        emails.forEach((emailData) => {
            const email = emailData.email || emailData;
            const isPrimary = emailData.is_primary || emailData.primary;
            const isVerified = emailData.verified !== false;

            html += `
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-envelope-fill text-primary"></i>
                                <strong>${escapeHtml(email)}</strong>
                                ${isPrimary ? '<span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Primary</span>' : ''}
                                ${!isVerified ? '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>' : ''}
                            </div>
                            <small class="text-muted">${isVerified ? 'Verified' : 'Verification pending'}</small>
                        </div>
                        <div class="btn-group btn-group-sm gap-2" role="group">
                            ${!isPrimary && isVerified ? `
                                <button type="button" class="btn btn-outline-primary js-set-primary" data-email="${escapeHtml(email)}">
                                    <i class="bi bi-star me-1"></i> Set Primary
                                </button>
                            ` : ''}
                            <button type="button" class="btn btn-outline-danger js-unlink-email" data-email="${escapeHtml(email)}">
                                <i class="bi bi-unlink me-1"></i> Unlink
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Add event listeners
        container.querySelectorAll('.js-set-primary').forEach((btn) => {
            btn.addEventListener('click', () => {
                const email = btn.dataset.email;
                setPrimaryEmail(email);
            });
        });

        container.querySelectorAll('.js-unlink-email').forEach((btn) => {
            btn.addEventListener('click', () => {
                const email = btn.dataset.email;
                if (confirm(`Are you sure you want to unlink ${email}?`)) {
                    unlinkEmail(email);
                }
            });
        });
    };

    // Link new email
    const linkEmail = async (email) => {
        if (!email || !email.trim()) {
            window.showAlert('Please enter an email address', 'warning');
            return;
        }

        try {
            const response = await fetch('/api/user/linked-emails', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email: email.trim() })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data?.message || 'Failed to link email');
            }

            window.showAlert('Email link request sent! Check your inbox for verification link.', 'success');
            if (emailInput) emailInput.value = '';

            // Reload emails after a brief delay
            setTimeout(() => loadLinkedEmails(), 2000);
        } catch (error) {
            window.showAlert(error?.message || 'An error occurred. Please try again.', 'danger');
        }
    };

    // Unlink email
    const unlinkEmail = async (email) => {
        try {
            const response = await fetch(`/api/user/linked-emails/${encodeURIComponent(email)}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data?.message || 'Failed to unlink email');
            }

            window.showAlert('Email unlinked successfully', 'success');
            loadLinkedEmails();
        } catch (error) {
            window.showAlert(error?.message || 'An error occurred. Please try again.', 'danger');
        }
    };

    // Set primary email
    const setPrimaryEmail = async (email) => {
        try {
            const response = await fetch(`/api/user/linked-emails/${encodeURIComponent(email)}/primary`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ set_primary: true })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data?.message || 'Failed to set primary email');
            }

            window.showAlert('Primary email updated successfully', 'success');
            loadLinkedEmails();
        } catch (error) {
            showError(error);
        }
    };

    // Form submission
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = emailInput?.value || '';
            linkEmail(email);
        });
    }

    // Initial load
    loadLinkedEmails();

    return {
        loadLinkedEmails,
        linkEmail,
        unlinkEmail,
        setPrimaryEmail
    };
}

// Helper: Escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
}

export default initLinkedEmails;
