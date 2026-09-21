{{-- Modern comments section — ported from legacy comments/comments.twig, rebuilt on Alpine.
     Read tree renders server-side (CommentService::tree); submit posts to the migrated
     /comment/add endpoint with the Laravel CSRF token. React/edit/delete endpoints are
     still legacy-owned (tracked in migration/REMAINING_STEPS.md). --}}

<div class="modern-comments-section mt-8" x-data="commentSection({{ json_encode([
        'content_type' => $contentType ?? 'post',
        'content_id' => $contentId ?? 0,
        'total' => count($comments ?? []),
    ]) }})" x-on:comment-reply.window="replyTo($event.detail.id, $event.detail.author)">
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
            <i class="lucide lucide-message-square-more bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent"></i>
            <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">{{ t('Comments') }}</span>
        </h2>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-indigo-600 to-purple-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-indigo-500/20">
            <i class="lucide lucide-message-square-text" aria-hidden="true"></i>
            <span x-text="total"></span>
        </span>
    </div>

    {{-- Feedback --}}
    <div x-show="feedback.show" x-cloak x-transition
         class="mb-4 flex items-center gap-3 rounded-xl border p-4 text-sm font-medium"
         :class="feedback.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : (feedback.type === 'danger' ? 'border-red-200 bg-red-50 text-red-800' : 'border-sky-200 bg-sky-50 text-sky-800')">
        <i class="lucide lucide-info shrink-0" aria-hidden="true"></i>
        <span class="flex-1" x-text="feedback.message"></span>
        <button type="button" @click="feedback.show = false" class="shrink-0 opacity-60 transition hover:opacity-100" aria-label="Dismiss">
            <i class="lucide lucide-x h-4 w-4" aria-hidden="true"></i>
        </button>
    </div>

    {{-- Form --}}
    <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 to-purple-50/60 px-5 py-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-sm shadow-indigo-500/20">
                    <i class="lucide lucide-pencil" aria-hidden="true"></i>
                </span>
                <span x-text="replyingTo ? 'Replying to '+replyingTo : 'Share Your Thoughts'"></span>
            </h3>
        </div>
        <form @submit.prevent="submit" class="space-y-4 p-5">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="content_type" value="{{ $contentType ?? 'post' }}">
            <input type="hidden" name="content_id" value="{{ $contentId ?? 0 }}">
            <input type="hidden" name="parent_id" :value="parentId">

            @guest
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-slate-500">{{ t('Name') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="guest_name" x-model="guestName" placeholder="{{ t('Your Name') }}" required
                           class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10">
                </div>
            </div>
            @endguest

            <div>
                <textarea name="content" x-model="content" rows="4" maxlength="2000" placeholder="Write your comment... (Markdown supported 📝)" required
                          class="w-full resize-y rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10"></textarea>
                <div class="mt-1.5 flex justify-between text-xs">
                    <span class="font-semibold text-indigo-600" x-text="content.length + ' / 2000'"></span>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit" :disabled="loading"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/20 transition hover:from-indigo-700 hover:to-purple-700 disabled:opacity-60">
                    <span x-show="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    <i class="lucide lucide-send" aria-hidden="true" x-show="!loading"></i>
                    <span x-text="replyingTo ? 'Post Reply' : 'Post Comment'"></span>
                </button>
                <button type="button" @click="clearForm" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                    <i class="lucide lucide-x" aria-hidden="true"></i> <span x-text="replyingTo ? 'Cancel reply' : 'Clear'"></span>
                </button>
            </div>
        </form>
    </div>

    {{-- List --}}
    <div>
        @forelse ($comments as $comment)
        @include('partials.comment-item', ['comment' => $comment])
        @empty
        <div class="py-12 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-100 to-slate-50">
                <i class="lucide lucide-message-square-more text-2xl text-slate-300"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-600">{{ t('No comments yet') }}</h3>
            <p class="mt-1 text-sm text-slate-400">{{ t('Be the first to share your thoughts!') }}</p>
        </div>
        @endforelse
    </div>
</div>

<script>
function commentPost(url, payload) {
    const body = new FormData();
    body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    Object.entries(payload).forEach(([k, v]) => body.append(k, v));
    return fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json());
}

function commentItem(config) {
    return {
        id: config.id,
        editing: false,
        editContent: config.editContent,
        original: config.editContent,
        saving: false,
        removing: false,
        startEdit() {
            this.original = this.editContent;
            this.editing = true;
        },
        async saveEdit() {
            const content = (this.editContent || '').trim();
            if (content.length < 2) { return; }
            this.saving = true;
            try {
                const res = await commentPost('/comment/edit', { comment_id: this.id, content });
                if (res.success) { this.editing = false; window.location.reload(); }
                else { this.notify(res.message || 'Failed to edit comment', 'danger'); }
            } finally { this.saving = false; }
        },
        async remove() {
            if (!confirm('Are you sure you want to delete this comment?')) { return; }
            this.removing = true;
            try {
                const res = await commentPost('/comment/delete', { comment_id: this.id });
                if (res.success) { setTimeout(() => window.location.reload(), 400); }
                else { this.removing = false; this.notify(res.message || 'Failed to delete comment', 'danger'); }
            } catch (e) { this.removing = false; }
        },
        async react(emoji) {
            try {
                const res = await commentPost('/comment/react', { comment_id: this.id, reaction: emoji });
                if (res.success && this.$refs.reactions) {
                    this.$refs.reactions.innerHTML = Object.entries(res.reactions || {})
                        .map(([e, c]) => '<span class="inline-flex items-center gap-1 rounded-full border border-slate-100 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600">' + e + ' ' + c + '</span>')
                        .join('');
                } else { this.notify(res.message || 'Failed to add reaction', 'danger'); }
            } catch (e) { /* network */ }
        },
        notify(message, type) {
            window.dispatchEvent(new CustomEvent('comment-feedback', { detail: { message, type } }));
        }
    };
}

function commentSection(config) {
    return {
        content_type: config.content_type,
        content_id: config.content_id,
        total: config.total,
        content: '',
        guestName: localStorage.getItem('guest_name') || '',
        parentId: '',
        replyingTo: null,
        loading: false,
        feedback: { show: false, message: '', type: 'info' },
        submit() {
            if (this.content.trim().length < 2) {
                this.notify('Comment must be at least 2 characters', 'danger');
                return;
            }
            this.loading = true;
            const body = new FormData();
            body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            body.append('content_type', this.content_type);
            body.append('content_id', this.content_id);
            body.append('parent_id', this.parentId);
            body.append('content', this.content);
            if (this.guestName) body.append('guest_name', this.guestName);
            if (this.guestName) localStorage.setItem('guest_name', this.guestName);

            fetch('/comment/add', { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.notify('Comment posted successfully!', 'success');
                        this.clearForm();
                        setTimeout(() => window.location.reload(), 900);
                    } else {
                        this.notify(data.message || 'Failed to post comment', 'danger');
                    }
                })
                .catch(() => {
                    this.loading = false;
                    this.notify('An error occurred. Please try again.', 'danger');
                });
        },
        replyTo(id, author) {
            this.parentId = id;
            this.replyingTo = author;
            document.querySelector('.modern-comments-section form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        clearForm() {
            this.content = '';
            this.parentId = '';
            this.replyingTo = null;
        },
        init() {
            window.addEventListener('comment-feedback', (e) => this.notify(e.detail.message, e.detail.type));
        },
        notify(message, type) {
            this.feedback = { show: true, message, type };
            clearTimeout(this._timer);
            this._timer = setTimeout(() => { this.feedback.show = false; }, 5000);
        }
    };
}
</script>