export function scrollProgress() {
    return {
        progress: 0,
        init() {
            const update = () => {
                const scrolled = window.pageYOffset || document.documentElement.scrollTop || 0;
                const docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
                const winH = window.innerHeight;
                const maxScroll = docH - winH;
                this.progress = maxScroll > 0 ? Math.min((scrolled / maxScroll) * 100, 100) : 0;
            };

            window.addEventListener('scroll', () => {
                requestAnimationFrame(update);
            }, { passive: true });

            window.addEventListener('resize', update, { passive: true });
            update();
        }
    };
}
