@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="rounded-2xl border border-[rgb(var(--border))] bg-[rgb(var(--surface))] shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-[rgb(var(--border))] bg-[rgb(var(--surface-soft))]">
            <h1 class="text-xl font-bold text-[rgb(var(--text))]">ব্যাকআপ কোডসমূহ</h1>
            <p class="text-sm text-[rgb(var(--muted))] mt-0.5">ফোন হারালে এই কোডগুলোই আপনার শেষ ভরসা — এখনই সংরক্ষণ করুন</p>
        </div>

        <div class="px-6 py-6">
            <div class="rounded-xl bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-700 dark:text-amber-300 mb-6">
                এই কোডগুলো শুধু একবারই দেখানো হবে। প্রতিটি কোড একবারই ব্যবহার করা যাবে। নিরাপদ জায়গায় সংরক্ষণ করুন।
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                @foreach ($backupCodes as $code)
                    <code class="rounded-lg bg-[rgb(var(--surface-soft))] border border-[rgb(var(--border))] px-3 py-2.5 font-mono text-sm text-center tracking-wider text-[rgb(var(--text))]">{{ $code }}</code>
                @endforeach
            </div>

            <a href="{{ route('user.security.2fa') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 transition-colors">
                আমি কোডগুলো সংরক্ষণ করেছি
            </a>
        </div>
    </div>
</div>
@endsection
