export function shareModal() {
    return {
        open: false,
        title: '',
        url: '',
        openModal(title, url) {
            this.title = title;
            this.url = url || window.location.href;
            this.open = true;
        },
        close() {
            this.open = false;
        },
        copyToClipboard() {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this.url).then(() => {
                    this.showCopied();
                }).catch(() => {
                    this.fallbackCopy();
                });
            } else {
                this.fallbackCopy();
            }
        },
        showCopied() {
            const btn = this.$refs.copyBtn;
            if (!btn) return;
            const original = btn.innerHTML;
            btn.innerHTML = '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"20 6 9 17 4 12\"/></svg> Copied!';
            setTimeout(() => { btn.innerHTML = original; }, 2000);
        },
        fallbackCopy() {
            const ta = document.createElement('textarea');
            ta.value = this.url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                this.showCopied();
            } catch (e) {
                window.showPrompt && window.showPrompt('Copy this link:', this.url);
            }
            document.body.removeChild(ta);
        }
    };
}
