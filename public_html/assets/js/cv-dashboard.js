/**
 * CV Dashboard Frontend Functionality
 * Handles CV management, deletion, sharing, and filtering
 *
 * ES Module — exports initCvDashboard() for manual init.
 * Keeps shareOnPlatform, copyToClipboard, closeShareModal on window
 * for backward compatibility with onclick attributes in dynamically
 * created share modal HTML.
 */

/**
 * Initialize the CV dashboard.
 * Called automatically on DOMContentLoaded when loaded as an entry point.
 */
export function initCvDashboard() {
  setupDeleteButtons();
  setupShareButtons();
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
// Share CV
// ═══════════════════════════════════════════════

function setupShareButtons() {
  const shareButtons = document.querySelectorAll('[data-action="share-cv"]');

  shareButtons.forEach((btn) => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const cvId = this.getAttribute('data-cv-id');
      const cvTitle = this.getAttribute('data-cv-title') || 'CV';
      showShareModal(cvId, cvTitle);
    });
  });
}

function showShareModal(cvId, cvTitle) {
  const shareUrl = `${window.location.origin}/cv-builder/view/${cvId}`;
  const shareTitle = `Check out my CV: ${cvTitle}`;

  let modal = document.getElementById('share-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'share-modal';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
    modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-8">
                <h3 class="text-2xl font-bold mb-4">Share CV</h3>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Share Link</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            id="share-url"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                            readonly
                        >
                        <button
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition"
                            onclick="window.copyToClipboard('#share-url')"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Share On</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            class="p-3 border border-gray-300 rounded-lg hover:bg-blue-50 transition text-center"
                            onclick="window.shareOnPlatform('facebook', '${shareUrl}', '${shareTitle}')"
                            title="Share on Facebook"
                        >
                            <i class="lucide-facebook text-2xl text-blue-600"></i>
                        </button>
                        <button
                            class="p-3 border border-gray-300 rounded-lg hover:bg-blue-50 transition text-center"
                            onclick="window.shareOnPlatform('twitter', '${shareUrl}', '${shareTitle}')"
                            title="Share on Twitter"
                        >
                            <i class="lucide-twitter text-2xl text-blue-400"></i>
                        </button>
                        <button
                            class="p-3 border border-gray-300 rounded-lg hover:bg-green-50 transition text-center"
                            onclick="window.shareOnPlatform('whatsapp', '${shareUrl}', '${shareTitle}')"
                            title="Share on WhatsApp"
                        >
                            <i class="lucide-message-circle text-2xl text-green-600"></i>
                        </button>
                    </div>
                </div>

                <button
                    class="w-full px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition"
                    onclick="window.closeShareModal()"
                >
                    Close
                </button>
            </div>
        `;
    document.body.appendChild(modal);
  }

  document.getElementById('share-url').value = shareUrl;
  modal.classList.remove('hidden');
}

/** @global */
window.closeShareModal = function () {
  const modal = document.getElementById('share-modal');
  if (modal) {
    modal.classList.add('hidden');
  }
};

/** @global */
window.shareOnPlatform = function (platform, url, title) {
  let shareUrl = '';

  switch (platform) {
    case 'facebook':
      shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
      break;
    case 'twitter':
      shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
      break;
    case 'whatsapp':
      shareUrl = `https://wa.me/?text=${encodeURIComponent(`${title} ${url}`)}`;
      break;
  }

  if (shareUrl) {
    window.open(shareUrl, '_blank', 'width=600,height=400');
  }
};

/** @global */
window.copyToClipboard = function (selector) {
  const element = document.querySelector(selector);
  if (element) {
    const value = element.value || element.textContent || '';
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(() => {
        showNotification('Link copied to clipboard!', 'success');
      }).catch(() => {
        element.select();
        document.execCommand('copy');
        showNotification('Link copied to clipboard!', 'success');
      });
      return;
    }
    element.select();
    document.execCommand('copy');
    showNotification('Link copied to clipboard!', 'success');
  }
};

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
// Outside-click handler for share modal
// ═══════════════════════════════════════════════

document.addEventListener('click', (e) => {
  const modal = document.getElementById('share-modal');
  if (modal && !modal.classList.contains('hidden') && e.target === modal) {
    window.closeShareModal();
  }
});

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
