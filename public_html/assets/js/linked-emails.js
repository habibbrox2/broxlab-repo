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
  document.querySelector(messageSelector);

  if (!container && !form) return null;

  // Helper: Get CSRF token
  const getCsrfToken = () => {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (metaToken) return metaToken;
    if (!csrfTokenSelector) return '';
    const csrfTokenEl = document.querySelector(csrfTokenSelector);
    return csrfTokenEl?.value || csrfTokenEl?.content || '';
  };

  const showError = (error) => {
    const message = error?.message || 'An unexpected error occurred.';
    if (window.showAlert) {
      window.showAlert(message, 'danger');
      return;
    }
    console.error(message, error);
  };


  // Fetch and display linked emails
  const loadLinkedEmails = async () => {
    if (!container) return;

    try {
      const response = await fetch('/api/user/linked-emails', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', },
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
                <divclass="p-4 rounded-lg bg-amber-50 border-amber-200 text-amber-700 border">
    <i class="lucide lucide-alert-triangle mr-2"></i>
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
                <div class="p-4 rounded-lg bg-neutral-100 text-neutral-700 border border-neutral-200">
                    <i class="lucide lucide-info mr-2"></i>
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
                <div class="rounded-xl border-0 shadow-sm mb-3 bg-white">
                    <div class="p-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <i class="lucide lucide-mail text-indigo-600"></i>
                                <strong>${escapeHtml(email)}</strong>
                                ${isPrimary ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"><i class="lucide lucide-star mr-1"></i>Primary</span>' : ''}
                                ${!isVerified ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><i class="lucide lucide-clock mr-1"></i>Pending</span>' : ''}
                            </div>
                            <small class="text-slate-500">${isVerified ? 'Verified' : 'Verification pending'}</small>
                        </div>
                        <div class="flex gap-2 items-center" role="group">
                            ${!isPrimary && isVerified ? `
                                <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-600 text-indigo-600 hover:bg-indigo-50 transition-colors js-set-primary" data-email="${escapeHtml(email)}">
                                    <i class="lucide lucide-star mr-1"></i> Set Primary
                                </button>
                            ` : ''}
                            <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium border border-red-400 text-red-600 hover:bg-red-50 transition-colors js-unlink-email" data-email="${escapeHtml(email)}">
                                <i class="lucide lucide-unlink mr-1"></i> Unlink
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
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ email: email.trim(), }),
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
          'X-Requested-With': 'XMLHttpRequest',
        },
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
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ set_primary: true, }),
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
    setPrimaryEmail,
  };
}

// Helper: Escape HTML
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

export default initLinkedEmails;
