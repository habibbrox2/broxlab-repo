import { notifications } from './components/notifications.js';
import { userMenu } from './components/user-menu.js';
import { mobileMenu } from './components/mobile-menu.js';
import { topPicksTabs } from './components/top-picks-tabs.js';
import { feedToolbar } from './components/feed-toolbar.js';
import { shareModal } from './components/share-modal.js';
import { scrollProgress } from './components/scroll-progress.js';

export function initAlpineComponents() {
    window.Alpine.data('notifications', notifications);
    window.Alpine.data('userMenu', userMenu);
    window.Alpine.data('mobileMenu', mobileMenu);
    window.Alpine.data('topPicksTabs', topPicksTabs);
    window.Alpine.data('feedToolbar', feedToolbar);
    window.Alpine.data('shareModal', shareModal);
    window.Alpine.data('scrollProgress', scrollProgress);
}
