/**
 * MedEx Brand Page — Section accordion toggle + refresh button
 * Extracted from brand.twig inline <script>
 *
 * @package BroxLab MedEx
 */

'use strict';

// ===== Section Accordion =====

window.toggleSection = function (key) {
  const content = document.getElementById(`content-${ key}`);
  const toggle = document.getElementById(`toggle-${ key}`);
  if (!content || !toggle) return;

  const isActive = content.classList.contains('active');

  // Close all sections first (single-open accordion)
  document.querySelectorAll('.medex-section-content').forEach((el) => {
    el.classList.remove('active');
  });
  document.querySelectorAll('.section-toggle').forEach((el) => {
    el.classList.remove('rotated');
  });

  // Then toggle this one
  if (!isActive) {
    content.classList.add('active');
    toggle.classList.add('rotated');
  }
};

window.expandAll = function () {
  document.querySelectorAll('.medex-section-content').forEach((el) => {
    el.classList.add('active');
  });
  document.querySelectorAll('.section-toggle').forEach((el) => {
    el.classList.add('rotated');
  });
};

window.collapseAll = function () {
  document.querySelectorAll('.medex-section-content').forEach((el) => {
    el.classList.remove('active');
  });
  document.querySelectorAll('.section-toggle').forEach((el) => {
    el.classList.remove('rotated');
  });
};

// ===== Refresh Button =====

window.sendMedexRefreshRequest = async function (button) {
  button.disabled = true;
  const originalText = button.innerHTML;
  button.innerHTML = '<i class="lucide lucide-refresh-cw animate-spin h-4 w-4"></i> Refreshing...';

  const feedback = document.getElementById('medex-refresh-feedback');
  if (feedback) {
    feedback.textContent = '';
  }

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const body = new URLSearchParams({ csrf_token: csrfToken, }).toString();

    const response = await fetch('/api/medex/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken,
      },
      body: body,
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || 'Refresh failed');
    }

    if (feedback) {
      feedback.textContent = 'Refresh completed successfully.';
      setTimeout(() => {
        feedback.textContent = '';
      }, 5000);
    }
  } catch (error) {
    if (feedback) {
      feedback.textContent = String(error.message || 'Refresh failed');
    }
    console.error('MedEx refresh failed:', error);
  } finally {
    button.disabled = false;
    button.innerHTML = originalText;
  }
};

// ===== Init on DOM Ready =====

document.addEventListener('DOMContentLoaded', () => {
  const refreshButton = document.getElementById('medex-refresh-button');
  if (refreshButton) {
    refreshButton.addEventListener('click', function () {
      window.sendMedexRefreshRequest(this);
    });
  }
});

export {};
