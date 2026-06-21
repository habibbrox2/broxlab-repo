/**
 * CV Dashboard Frontend Functionality
 * Handles CV management, deletion, and filtering
 *
 * ES Module — exports initCvDashboard() for manual init.
 */

/**
 * Initialize the CV dashboard.
 * Called automatically on DOMContentLoaded when loaded as an entry point.
 */
export function initCvDashboard() {
  setupDeleteButtons();
  setupCvCardInteractions();
}

// ═══════════════════════════════════════════════
// Delete CV
// ═══════════════════════════════════════════════

function setupDeleteButtons() {
  const deleteButtons = document.querySelectorAll('[data-action="delete-cv"]');

  deleteButtons.forEach((btn) => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const cvId = this.getAttribute('data-cv-id');
      const cvTitle = this.getAttribute('data-cv-title') || 'CV';

      window.showConfirm(`Are you sure you want to delete "${cvTitle}"? This action cannot be undone.`).then((confirmed) => {
        if (confirmed) deleteCv(cvId);
      });
    });
  });
}

function deleteCv(cvId) {
  const deleteUrl = `/cv-builder/${encodeURIComponent(cvId)}`;
  const deleteBtn = document.querySelector(`[data-action="delete-cv"][data-cv-id="${cvId}"]`);

  if (deleteBtn) {
    deleteBtn.disabled = true;
    deleteBtn.dataset.originalLabel = deleteBtn.innerHTML;
    deleteBtn.innerHTML = 'Deleting...';
  }

  fetch(deleteUrl, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    },
  })
    .then((response) => {
      if (response.ok) {
        const cvCard = document.querySelector(`[data-cv-id="${cvId}"]`)?.closest('.cv-card');
        if (cvCard) {
          cvCard.style.animation = 'fadeOut 0.3s ease-out';
          setTimeout(() => cvCard.remove(), 300);
          showNotification('CV deleted successfully', 'success');
          setTimeout(() => location.reload(), 1000);
          return;
        }
        showNotification('CV deleted successfully', 'success');
      } else {
        showNotification('Failed to delete CV', 'error');
      }
    })
    .catch((error) => {
      console.error('Error deleting CV:', error);
      showNotification('Error deleting CV', 'error');
    })
    .finally(() => {
      if (deleteBtn) {
        deleteBtn.disabled = false;
        if (deleteBtn.dataset.originalLabel) {
          deleteBtn.innerHTML = deleteBtn.dataset.originalLabel;
          deleteBtn.removeAttribute('data-original-label');
        }
      }
    });
}

// ═══════════════════════════════════════════════
// CV Card Interactions
// ═══════════════════════════════════════════════

function setupCvCardInteractions() {
  const cvCards = document.querySelectorAll('.cv-card');

  cvCards.forEach((card) => {
    card.addEventListener('mouseenter', function () {
      this.classList.add('hover:shadow-lg', 'transform', 'hover:scale-105');
    });

    card.addEventListener('mouseleave', function () {
      this.classList.remove('hover:shadow-lg', 'transform', 'hover:scale-105');
    });
  });
}

// ═══════════════════════════════════════════════
// Notification Toast
// ═══════════════════════════════════════════════

function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${
    type === 'success' ? 'bg-green-500'
      : type === 'error' ? 'bg-red-500'
        : 'bg-blue-500'
  }`;
  notification.textContent = message;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = 'fadeOut 0.3s ease-out';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// ═══════════════════════════════════════════════
// Fade-out animation keyframes
// ═══════════════════════════════════════════════

const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
`;
document.head.appendChild(style);

// ═══════════════════════════════════════════════
// Auto-init on DOMContentLoaded
// ═══════════════════════════════════════════════

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initCvDashboard(), { once: true, });
} else {
  initCvDashboard();
}
