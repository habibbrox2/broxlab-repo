export function notifications() {
    return {
        open: false,
        init() {
            this.$watch('open', (value) => {
                if (value) {
                    this.$nextTick(() => {
                        const list = this.$refs.list;
                        if (list) list.scrollTop = 0;
                    });
                }
            });
        },
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        }
    };
}
