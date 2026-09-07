/**
 * Modern Public Header - v2.0
 * Enhanced Responsive Navigation with smooth animations
 * Includes: Mobile menu, dropdowns, theme toggle, and accessibility support
 */

export function initPublicHeaderDropdowns() {
  // ═════════════════════════════════════════════════════════════
  // 1. MOBILE MENU TOGGLE
  // ═════════════════════════════════════════════════════════════
  const mobileMenuToggle = document.getElementById('mobileMenuToggle');
  const mobileMenu = document.getElementById('broxMainNav');
  const hamburgerIcon = mobileMenuToggle?.querySelector('[data-icon-hamburger]');
  const closeIcon = mobileMenuToggle?.querySelector('[data-icon-close]');

  if (mobileMenuToggle && mobileMenu) {
    const toggleMobileMenu = (open) => {
      const isOpen = open !== undefined ? open : mobileMenuToggle.getAttribute('aria-expanded') !== 'true';

      mobileMenuToggle.setAttribute('aria-expanded', isOpen);
      mobileMenu.setAttribute('data-expanded', isOpen ? 'true' : 'false');

      if (isOpen) {
        // Opening menu
        hamburgerIcon?.classList.add('hidden');
        closeIcon?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      } else {
        // Closing menu
        hamburgerIcon?.classList.remove('hidden');
        closeIcon?.classList.add('hidden');
        document.body.style.overflow = 'auto';
      }
    };

    // Open/close on hamburger click
    mobileMenuToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleMobileMenu();
    });

    // Close menu when clicking nav links
    mobileMenu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        if (window.innerWidth < 1024) {
          toggleMobileMenu(false);
        }
      });
    });

    // Close menu on outside click
    document.addEventListener('click', (e) => {
      if (window.innerWidth < 1024 && mobileMenuToggle.getAttribute('aria-expanded') === 'true') {
        if (!mobileMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
          toggleMobileMenu(false);
        }
      }
    });

    // Close menu on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && mobileMenuToggle.getAttribute('aria-expanded') === 'true') {
        toggleMobileMenu(false);
        mobileMenuToggle.focus();
      }
    });

    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        if (window.innerWidth >= 1024) {
          toggleMobileMenu(false);
        }
      }, 150);
    });
  }

  // ═════════════════════════════════════════════════════════════
  // 2. NOTIFICATION DROPDOWN
  // ═════════════════════════════════════════════════════════════
  const notificationBell = document.getElementById('broxNotificationBell');
  const notificationDropdown = document.getElementById('notificationDropdown');

  if (notificationBell && notificationDropdown) {
    const toggleNotification = () => {
      const isOpen = notificationBell.getAttribute('aria-expanded') === 'true';
      const newState = !isOpen;

      notificationBell.setAttribute('aria-expanded', newState);

      if (newState) {
        notificationDropdown.classList.remove('hidden', 'opacity-0', 'invisible', 'scale-95');
        notificationDropdown.classList.add('opacity-100', 'visible', 'scale-100');
      } else {
        notificationDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
        notificationDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
      }
    };

    notificationBell.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleNotification();
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (notificationBell.getAttribute('aria-expanded') === 'true') {
        if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
          notificationBell.setAttribute('aria-expanded', 'false');
          notificationDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
          notificationDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
        }
      }
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && notificationBell.getAttribute('aria-expanded') === 'true') {
        notificationBell.setAttribute('aria-expanded', 'false');
        notificationDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
        notificationDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
        notificationBell.focus();
      }
    });
  }

  // ═════════════════════════════════════════════════════════════
  // 3. USER DROPDOWN MENU
  // ═════════════════════════════════════════════════════════════
  const userMenuBtn = document.getElementById('broxNavbarUser');
  const userDropdown = document.getElementById('userDropdown');

  if (userMenuBtn && userDropdown) {
    const toggleUserMenu = () => {
      const isOpen = userMenuBtn.getAttribute('aria-expanded') === 'true';
      const newState = !isOpen;

      userMenuBtn.setAttribute('aria-expanded', newState);

      if (newState) {
        userDropdown.classList.remove('hidden', 'opacity-0', 'invisible', 'scale-95');
        userDropdown.classList.add('opacity-100', 'visible', 'scale-100');

        // Close notification dropdown if open
        if (notificationBell && notificationDropdown) {
          notificationBell.setAttribute('aria-expanded', 'false');
          notificationDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
          notificationDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
        }
      } else {
        userDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
        userDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
      }
    };

    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleUserMenu();
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (userMenuBtn.getAttribute('aria-expanded') === 'true') {
        if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
          userMenuBtn.setAttribute('aria-expanded', 'false');
          userDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
          userDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
        }
      }
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && userMenuBtn.getAttribute('aria-expanded') === 'true') {
        userMenuBtn.setAttribute('aria-expanded', 'false');
        userDropdown.classList.add('hidden', 'opacity-0', 'invisible', 'scale-95');
        userDropdown.classList.remove('opacity-100', 'visible', 'scale-100');
        userMenuBtn.focus();
      }
    });
  }

  // ═════════════════════════════════════════════════════════════
  // 4. LANGUAGE TOGGLE (Handled by brox-i18n.js via data-lang-btn)
  // ═════════════════════════════════════════════════════════════
  // Language switching is now handled by brox-i18n.js which provides
  // smooth language switching without page reload. The header button
  // with data-lang-btn is wired automatically by brox-i18n.wireLangButtons()
  // No page reload needed - just click the globe icon to switch.

  // ═════════════════════════════════════════════════════════════
  // 5. NOTIFICATION CLICK HANDLERS
  // ═════════════════════════════════════════════════════════════
  const notificationItems = document.querySelectorAll('[data-notification-id]');

  notificationItems.forEach((item) => {
    item.addEventListener('click', () => {
      const actionUrl = item.getAttribute('data-action-url');
      if (actionUrl) {
        window.location.href = actionUrl;
      }
    });
  });

  // Mark notification as read
  document.querySelectorAll('[data-action="mark-read"]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const notifId = btn.getAttribute('data-notification-id');
      // You can add API call here to mark as read
      console.log('Mark notification as read:', notifId);
    });
  });
}
