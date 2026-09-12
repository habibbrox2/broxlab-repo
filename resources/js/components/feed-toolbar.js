export function feedToolbar() {
    return {
        viewMode: 'grid',
        searchQuery: '',
        autoRefresh: false,
        refreshTimer: null,

        init() {
            this.$watch('autoRefresh', (enabled) => {
                if (enabled) {
                    this.refreshTimer = setInterval(() => {
                        window.location.reload();
                    }, 30000);
                } else if (this.refreshTimer) {
                    clearInterval(this.refreshTimer);
                    this.refreshTimer = null;
                }
            });
        },
        toggleView() {
            this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid';
            this.$dispatch('view-changed', this.viewMode);
        }
    };
}
