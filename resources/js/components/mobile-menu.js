export function mobileMenu() {
    return {
        open: false,
        init() {
            this.$watch('open', (value) => {
                document.body.style.overflow = value ? 'hidden' : '';
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
