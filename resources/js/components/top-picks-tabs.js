export function topPicksTabs() {
    return {
        activeTab: 'posts',
        setTab(tab) {
            this.activeTab = tab;
        }
    };
}
