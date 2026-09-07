@extends('admin.layout')

@section('title', 'Donations — '.($appSettings['site_name'] ?? 'BroxLab'))

@section('content')

<div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-rose-900 to-pink-900 text-white shadow-xl shadow-slate-900/20">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(244,63,94,0.25),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative px-6 py-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/10 flex items-center justify-center flex-shrink-0">
                <i class="lucide lucide-heart w-5 h-5 text-white"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Revenue</p>
                <h1 class="text-xl font-bold text-white">Donations</h1>
                <p class="text-sm text-white/60 mt-0.5">View and manage user donations</p>
            </div>
        </div>
        <a href="/admin/revenue" class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-150">
            <i class="lucide lucide-arrow-left w-4 h-4"></i> Back to Revenue
        </a>
    </div>
</div>

<div class="max-w-6xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="lucide lucide-heart w-4 h-4 text-rose-600 dark:text-rose-400"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent Donations</h3>
            </div>
            <span class="text-xs text-slate-400 dark:text-slate-600">Total: ৳0.00</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-800/20">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Donor</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Amount</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Payment Method</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Date</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-600">
                            <i class="lucide lucide-heart-off w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm font-medium">No donations yet</p>
                            <p class="text-xs mt-1">Donations will appear here</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
        <a href="/admin/revenue/donations/bkash" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-credit-card w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">bKash</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-4">Manage bKash donation settings</p>
                <span class="text-xs font-medium text-slate-400 dark:text-slate-600 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Configure →</span>
            </div>
        </a>
        <a href="/admin/revenue/donations/nagad" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-credit-card w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Nagad</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-4">Manage Nagad donation settings</p>
                <span class="text-xs font-medium text-slate-400 dark:text-slate-600 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Configure →</span>
            </div>
        </a>
        <a href="/admin/revenue/donations/rocket" class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-150 group">
            <div class="p-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="lucide lucide-credit-card w-6 h-6 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Rocket</h3>
                <p class="text-sm text-slate-400 dark:text-slate-600 mb-4">Manage Rocket donation settings</p>
                <span class="text-xs font-medium text-slate-400 dark:text-slate-600 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Configure →</span>
            </div>
        </a>
    </div>
</div>

@endsection
