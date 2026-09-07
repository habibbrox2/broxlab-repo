@php
    $author = $comment['username'] ?: ($comment['guest_name'] ?: 'Guest');
    $initial = mb_strtoupper(mb_substr($author, 0, 1));
    $isAdmin = in_array($comment['user_role'] ?? null, ['admin', 'super_admin'], true);
    $reactions = $comment['reactions'] ?? [];
    $currentUserId = auth()->id() ? (int) auth()->id() : null;
    $commentUserId = (int) ($comment['user_id'] ?? 0);
    $canModerate = $currentUserId !== null && ($currentUserId === $commentUserId || $isAdmin);
@endphp
<div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-slate-200 hover:shadow-md"
     x-data="commentItem({
        id: {{ $comment['id'] }},
        canModerate: {{ $canModerate ? 'true' : 'false' }},
        editing: false,
        editContent: {{ json_encode($comment['content']) }}
     })"
     :class="{ 'opacity-0 scale-95': removing }"
     :style="removing ? 'transition: all .3s' : ''">
    <div class="flex gap-3">
        <div class="shrink-0">
            @if (!empty($comment['user_avatar']))
            <img src="{{ $comment['user_avatar'] }}" alt="Avatar" class="h-10 w-10 rounded-full object-cover shadow-sm ring-2 ring-white">
            @else
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-sm font-bold text-white shadow-sm shadow-indigo-500/20">
                {{ $initial }}
            </div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <strong class="text-sm font-semibold text-slate-800">{{ $author }}</strong>
                @if ($isAdmin)
                <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-red-500 to-rose-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm shadow-red-500/20">
                    <i class="lucide lucide-shield"></i> Admin
                </span>
                @endif
                <span class="flex items-center gap-1 text-xs text-slate-400">
                    <i class="lucide lucide-clock"></i>
                    {{ \Illuminate\Support\Carbon::parse($comment['created_at'])->format('M d, Y \a\t H:i') }}
                    @if (!empty($comment['edited_at']))
                    <span class="text-slate-300">(edited {{ \Illuminate\Support\Carbon::parse($comment['edited_at'])->format('M d, Y') }})</span>
                    @endif
                </span>
            </div>

            {{-- View mode --}}
            <div x-show="!editing" class="markdown-content text-sm leading-relaxed text-slate-600">
                {!! $comment['content_html'] ?? e($comment['content']) !!}
            </div>

            {{-- Edit mode --}}
            <div x-show="editing" x-cloak>
                <textarea x-model="editContent" rows="3" maxlength="2000"
                          class="mb-2 w-full resize-y rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10"></textarea>
                <div class="flex items-center gap-2">
                    <button type="button" @click="saveEdit()" :disabled="saving"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60">
                        <i class="lucide lucide-check-circle"></i> Save
                    </button>
                    <button type="button" @click="editing = false; editContent = original"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-100">
                        <i class="lucide lucide-x"></i> Cancel
                    </button>
                </div>
            </div>

            {{-- Reactions summary --}}
            <div x-ref="reactions" class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($reactions as $emoji => $count)
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-100 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ $emoji }} {{ $count }}</span>
                @endforeach
            </div>

            {{-- Actions --}}
            <div class="mt-3 flex flex-wrap items-center gap-1">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-indigo-50 hover:text-indigo-600"
                        @click="window.dispatchEvent(new CustomEvent('comment-reply', { detail: { id: {{ $comment['id'] }}, author: '{{ $author }}' } }))">
                    <i class="lucide lucide-reply"></i> Reply
                </button>
                @if ($canModerate)
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-amber-50 hover:text-amber-600" @click="startEdit()">
                    <i class="lucide lucide-pencil"></i> Edit
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-red-50 hover:text-red-600" @click="remove()" :disabled="removing">
                    <i class="lucide lucide-trash-2"></i> Delete
                </button>
                @endif
                {{-- React (emoji panel) --}}
                <div class="relative inline-block" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-600">
                        <span>👍</span> React
                    </button>
                    <div x-show="open" x-cloak class="absolute bottom-full left-0 z-10 mb-2 flex gap-0.5 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                        @foreach (['👍','❤️','🔥','😂','😮','😢','🎉','💯'] as $emoji)
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-lg text-lg transition hover:scale-125 hover:bg-slate-100" @click="react('{{ $emoji }}')">{{ $emoji }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (!empty($comment['replies']))
    <div class="ml-8 mt-4 space-y-3 border-l-2 border-slate-100 pl-4">
        @foreach ($comment['replies'] as $reply)
        @include('partials.comment-item', ['comment' => $reply])
        @endforeach
    </div>
    @endif
</div>