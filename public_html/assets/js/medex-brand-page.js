/**
 * MedEx Brand Page — Section accordion toggle + refresh button
 * Extracted from brand.twig inline <script>
 *
 * @package BroxLab MedEx
 */

(function () {
    'use strict';

    // ===== Section Accordion =====

    window.toggleSection = function (key) {
        var content = document.getElementById('content-' + key);
        var toggle = document.getElementById('toggle-' + key);
        if (!content || !toggle) return;

        var isActive = content.classList.contains('active');

        // Close all sections first (single-open accordion)
        document.querySelectorAll('.medex-section-content').forEach(function (el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.section-toggle').forEach(function (el) {
            el.classList.remove('rotated');
        });

        // Then toggle this one
        if (!isActive) {
            content.classList.add('active');
            toggle.classList.add('rotated');
        }
    };

    window.expandAll = function () {
        document.querySelectorAll('.medex-section-content').forEach(function (el) {
            el.classList.add('active');
        });
        document.querySelectorAll('.section-toggle').forEach(function (el) {
            el.classList.add('rotated');
        });
    };

    window.collapseAll = function () {
        document.querySelectorAll('.medex-section-content').forEach(function (el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.section-toggle').forEach(function (el) {
            el.classList.remove('rotated');
        });
    };

    // ===== Refresh Button =====

    window.sendMedexRefreshRequest = async function (button) {
        button.disabled = true;
        var originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Refreshing...';

        var feedback = document.getElementById('medex-refresh-feedback');
        if (feedback) {
            feedback.textContent = '';
        }

        try {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var body = new URLSearchParams({ csrf_token: csrfToken }).toString();

            var response = await fetch('/api/medex/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: body,
            });

            var data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Refresh failed');
            }

            if (feedback) {
                feedback.textContent = 'Refresh completed successfully.';
                setTimeout(function () {
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

    document.addEventListener('DOMContentLoaded', function () {
        var refreshButton = document.getElementById('medex-refresh-button');
        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                window.sendMedexRefreshRequest(this);
            });
        }
    });
})();
