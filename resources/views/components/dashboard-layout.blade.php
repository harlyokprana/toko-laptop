<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name', 'Laptop Prime.Com') }}</title>
    @include('partials.theme-init')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@include('partials.money-formatter')
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
@php
    $user = auth()->user();
    $notificationItems = $user->notifications()->latest()->limit(5)->get();
    $unreadNotificationCount = $user->unreadNotifications()->count();
    $isCashier = $user->role?->name === 'KASIR';
    $groupDefinitions = $isCashier ? [
        ['label' => 'Kasir (POS)', 'icon' => 'cart', 'children' => [
            ['label' => 'Transaksi Kasir', 'route' => 'sales.create', 'permission' => 'create_sales'],
            ['label' => 'Daftar Transaksi', 'route' => 'module.sales', 'permission' => 'view_sales'],
        ]],
        ['label' => 'Penjualan', 'icon' => 'receipt', 'children' => [
            ['label' => 'Daftar Penjualan', 'route' => 'module.sales', 'permission' => 'view_sales'],
            ['label' => 'Piutang Pelanggan', 'route' => 'module.sales', 'permission' => 'view_sales', 'params' => ['status' => 'PIUTANG']],
            ['label' => 'Pembayaran Piutang', 'route' => 'module.sales', 'permission' => 'view_sales', 'params' => ['status_group' => 'receivable']],
        ]],
        ['label' => 'Service', 'icon' => 'service', 'children' => [
            ['label' => 'Daftar Service', 'route' => 'module.service', 'permission' => 'view_service'],
            ['label' => 'Terima Service', 'route' => 'service.create', 'permission' => 'create_service'],
            ['label' => 'Progress Service', 'route' => 'module.service-progress', 'permission' => 'update_service_progress'],
            ['label' => 'Persetujuan', 'route' => 'module.service', 'permission' => 'view_service', 'params' => ['status' => 'MENUNGGU_PERSETUJUAN']],
            ['label' => 'Sparepart Service', 'route' => 'module.service', 'permission' => 'view_service', 'params' => ['status' => 'MENUNGGU_SPAREPART']],
            ['label' => 'Selesai & Pengambilan', 'route' => 'module.service', 'permission' => 'view_service', 'params' => ['status_group' => 'closed']],
        ]],
        ['label' => 'Shift & Kas', 'icon' => 'cash', 'children' => [
            ['label' => 'Buka / Tutup Shift', 'route' => 'cash.shifts.index', 'permission' => 'view_cash'],
            ['label' => 'Kas Harian', 'route' => 'module.cash', 'permission' => 'view_cash'],
        ]],
        ['label' => 'Setoran Kas', 'icon' => 'deposit', 'children' => [
            ['label' => 'Setoran Kas', 'route' => 'income.create', 'permission' => 'create_income'],
            ['label' => 'Riwayat Setoran', 'route' => 'module.income', 'permission' => 'view_income'],
        ]],
        ['label' => 'Purchase Order', 'icon' => 'purchase', 'children' => [
            ['label' => 'Daftar PO', 'route' => 'purchase-orders.index', 'permission' => 'view_purchases'],
            ['label' => 'Penerimaan PO', 'route' => 'purchase-orders.receiving.index', 'permission' => 'view_purchases'],
        ]],
        ['label' => 'Laporan', 'icon' => 'chart', 'children' => [
            ['label' => 'Penjualan', 'route' => 'module.reports', 'permission' => 'view_reports', 'params' => ['type' => 'sales']],
            ['label' => 'Service', 'route' => 'module.reports', 'permission' => 'view_reports', 'params' => ['type' => 'service']],
            ['label' => 'Kas', 'route' => 'module.reports', 'permission' => 'view_reports', 'params' => ['type' => 'cash']],
            ['label' => 'Stok', 'route' => 'module.reports', 'permission' => 'view_reports', 'params' => ['type' => 'stock']],
            ['label' => 'Lainnya', 'route' => 'module.reports', 'permission' => 'view_reports', 'params' => ['type' => 'other']],
        ]],
    ] : [
        ['label' => 'Inventaris', 'icon' => 'stock', 'children' => [
                ['label' => 'Produk', 'route' => 'products.index', 'permission' => 'view_products'],
            ['label' => 'Stok', 'route' => 'stock.index', 'permission' => 'view_stock'],
        ]],
        ['label' => 'Penjualan', 'icon' => 'cart', 'children' => [
            ['label' => 'Kasir (POS)', 'route' => 'sales.create', 'permission' => 'create_sales'],
            ['label' => 'Daftar Penjualan', 'route' => 'module.sales', 'permission' => 'view_sales'],
            ['label' => 'Pelanggan', 'route' => 'customers.index', 'permission' => 'view_sales'],
        ]],
        ['label' => 'Pembelian', 'icon' => 'purchase', 'children' => [
            ['label' => 'Purchase Order', 'route' => 'purchase-orders.index', 'permission' => 'view_purchases'],
            ['label' => 'Penerimaan PO', 'route' => 'purchase-orders.receiving.index', 'permission' => 'view_purchases'],
            ['label' => 'Supplier', 'route' => 'suppliers.index', 'permission' => 'view_purchases'],
        ]],
        ['label' => 'Service', 'icon' => 'service', 'children' => [
            ['label' => 'Daftar Service', 'route' => 'module.service', 'permission' => 'view_service'],
            ['label' => 'Terima Service', 'route' => 'service.create', 'permission' => 'create_service'],
            ['label' => 'Progress Service', 'route' => 'module.service-progress', 'permission' => 'update_service_progress'],
        ]],
        ['label' => 'Kas & Pendapatan', 'icon' => 'cash', 'children' => [
            ['label' => 'Shift Kasir', 'route' => 'cash.shifts.index', 'permission' => 'view_cash'],
            ['label' => 'Kas', 'route' => 'module.cash', 'permission' => 'view_cash'],
            ['label' => 'Pendapatan', 'route' => 'module.income', 'permission' => 'view_income'],
            ['label' => 'Catat Pendapatan', 'route' => 'income.create', 'permission' => 'create_income'],
            ['label' => 'Pengeluaran', 'route' => 'expenses.index', 'permission' => 'view_expense'],
        ]],
        ['label' => 'Laporan', 'icon' => 'chart', 'children' => [
            ['label' => 'Ringkasan Laporan', 'route' => 'module.reports', 'permission' => 'view_reports'],
            ['label' => 'Laba Rugi', 'route' => 'module.profit-loss', 'permission' => 'view_profit_loss'],
        ]],
        ['label' => 'Manajemen', 'icon' => 'people', 'children' => [
            ['label' => 'Payroll', 'route' => 'payroll.index', 'permission' => 'view_payroll'],
            ['label' => 'Pengguna', 'route' => 'module.users', 'permission' => 'manage_users'],
            ['label' => 'Role & Permission', 'route' => 'module.roles', 'permission' => 'manage_roles'],
            ['label' => 'AI Assistant', 'route' => 'module.ai', 'permission' => 'use_ai'],
        ]],
    ];
    if (app()->environment('local') && $user->role?->name === 'PIMPINAN') {
        $groupDefinitions[count($groupDefinitions) - 1]['children'][] = [
            'label' => 'Reset All Data', 'route' => 'reset-data.show', 'permission' => 'manage_roles',
        ];
    }
    $visibleGroups = collect($groupDefinitions)->map(function ($group) use ($user) {
        $group['children'] = collect($group['children'])->filter(fn ($item) => $user->hasPermission($item['permission']))->values()->all();
        return $group;
    })->filter(fn ($group) => count($group['children']) > 0)->values();
    $userInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1));
@endphp
<div x-data="{ mobileOpen: false, sidebarCollapsed: false }" class="min-h-screen">
    <div x-show="mobileOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/55 lg:hidden" @click="mobileOpen = false" style="display:none"></div>
    <aside :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', sidebarCollapsed ? 'lg:w-20' : 'lg:w-60']" class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-slate-200 bg-white text-slate-800 shadow-xl shadow-slate-900/[0.04] transition-all duration-200 lg:translate-x-0">
        <a href="{{ route('dashboard') }}" class="flex h-[72px] shrink-0 items-center gap-3 border-b border-slate-100 px-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-700 text-white"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M2 20h20M8 16l-1 4m9-4 1 4"/></svg></span>
            <span x-show="!sidebarCollapsed" x-transition.opacity class="min-w-0"><span class="block truncate text-sm font-bold tracking-wide text-slate-950">Laptop Prime.Com</span><span class="mt-0.5 block text-[9px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Sales · Service · Accessories</span></span>
        </a>
        <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-3">
            @if($user->hasPermission('view_dashboard'))
                <a href="{{ route('dashboard') }}" @click="mobileOpen = false" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition', 'bg-amber-300 text-slate-950 shadow-sm' => request()->routeIs('dashboard'), 'text-slate-600 hover:bg-amber-50 hover:text-slate-950' => ! request()->routeIs('dashboard')])>
                    <x-nav-icon name="home"/><span x-show="!sidebarCollapsed" class="truncate">Dashboard</span>
                </a>
            @endif
            @foreach($visibleGroups as $index => $group)
                @php
                    $groupActive = collect($group['children'])->contains(fn ($child) => request()->routeIs($child['route']) && collect($child['params'] ?? [])->every(fn ($value, $key) => request()->query($key) == $value));
                    $navKey = ($isCashier ? 'cashier-' : 'general-').\Illuminate\Support\Str::slug($group['label']);
                @endphp
                <div x-data="{ open: localStorage.getItem('sidebar:{{ $navKey }}') !== null ? localStorage.getItem('sidebar:{{ $navKey }}') === '1' : {{ $groupActive ? 'true' : 'false' }} }" x-init="$watch('open', value => localStorage.setItem('sidebar:{{ $navKey }}', value ? '1' : '0')); if ({{ $groupActive ? 'true' : 'false' }}) open = true" class="pt-1">
                    <button type="button" @click="if (sidebarCollapsed && window.innerWidth >= 1024) sidebarCollapsed = false; open = !open" :aria-expanded="open.toString()" class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition hover:bg-slate-50" :class="{{ $groupActive ? "'text-emerald-800'" : "'text-slate-600'" }}">
                        <x-nav-icon name="{{ $group['icon'] }}"/><span x-show="!sidebarCollapsed" class="flex-1 truncate">{{ $group['label'] }}</span><svg x-show="!sidebarCollapsed" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="open && !sidebarCollapsed" class="ml-5 mt-1 space-y-0.5 border-l border-slate-200 pl-3">
                        @foreach($group['children'] as $child)
                            @php $childActive = request()->routeIs($child['route']) && collect($child['params'] ?? [])->every(fn ($value, $key) => request()->query($key) == $value); @endphp
                            <a href="{{ route($child['route'], $child['params'] ?? []) }}" @click="mobileOpen = false" @class(['block rounded-lg px-3 py-2 text-[13px] transition', 'bg-amber-300 font-semibold text-slate-950' => $childActive, 'text-slate-600 hover:bg-amber-50 hover:text-slate-950' => ! $childActive])>{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
        <div x-show="!sidebarCollapsed" class="shrink-0 border-t border-slate-100 p-3">
            <a href="{{ route('profile.edit') }}" @click="mobileOpen = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50"><span class="grid h-8 w-8 place-items-center rounded-full bg-emerald-700 text-sm font-bold text-white">{{ $userInitial }}</span><span class="min-w-0"><span class="block truncate text-xs font-semibold">{{ $user->name }}</span><span class="block truncate text-[11px] text-slate-500">{{ $user->role?->name }}</span></span></a>
        </div>
    </aside>

    <div :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-60'" class="min-h-screen transition-[padding] duration-200">
        <header class="app-header sticky top-0 z-30 flex min-h-[68px] items-center gap-3 border-b border-slate-200 bg-white/95 px-3 backdrop-blur sm:px-5 lg:px-6">
            <button type="button" @click="if (window.innerWidth < 1024) mobileOpen = true; else sidebarCollapsed = !sidebarCollapsed" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Buka atau ciutkan menu"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg></button>
            <form action="{{ route('global-search') }}" method="GET" class="relative min-w-0 max-w-3xl flex-1">
                <x-nav-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"/>
                <input id="global-search" name="q" type="search" value="{{ request()->routeIs('global-search') ? request('q') : '' }}" placeholder="Cari produk, supplier, PO, invoice, service, atau serial number… (F2)" class="h-10 w-full rounded-lg border-slate-200 bg-slate-50 pl-10 pr-14 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-blue-500">
                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">F2</span>
            </form>
            <div class="hidden items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 md:flex"><svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 10h18M5 10V6l7-3 7 3v4M5 10v10h14V10M9 14h6"/></svg><span>Toko Utama</span><svg class="h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path d="m5.5 7.5 4.5 4 4.5-4"/></svg></div>
            <button type="button" id="theme-toggle" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" aria-label="Aktifkan tema gelap" title="Aktifkan tema gelap">
                <svg class="theme-icon-moon h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.5 8.5 0 1 0 20.2 15.1Z"/></svg>
                <svg class="theme-icon-sun hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
            </button>
            <div x-data="{ open: false }" class="relative shrink-0">
                <button type="button" @click="open = !open" @click.outside="open = false" class="relative grid h-10 w-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Notifikasi" :aria-expanded="open.toString()">
                    <x-nav-icon name="bell"/>
                    @if($unreadNotificationCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    @endif
                </button>
                <div x-show="open" x-transition.origin.top.right @click.outside="open = false" style="display:none" class="absolute right-0 top-12 z-50 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <span class="text-sm font-bold text-slate-900">Notifikasi</span>
                        @if($unreadNotificationCount > 0)<span class="text-xs font-semibold text-emerald-700">{{ $unreadNotificationCount }} belum dibaca</span>@endif
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        @forelse($notificationItems as $notification)
                            <a href="{{ route('notifications.open', $notification->id) }}" @class([
                                'block border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50',
                                'bg-emerald-50/60' => $notification->unread(),
                            ])>
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Pembaruan' }}</span>
                                <span class="mt-1 block line-clamp-2 text-xs text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="mt-1.5 block text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </a>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-slate-500">Belum ada notifikasi.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Lihat semua notifikasi</a>
                </div>
            </div>
            <div x-data="{ open: false }" class="relative shrink-0">
                <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-lg px-1.5 py-1.5 text-left hover:bg-slate-50 sm:gap-3 sm:px-2"><span class="grid h-8 w-8 place-items-center rounded-full bg-slate-200 text-sm font-bold text-slate-700">{{ $userInitial }}</span><span class="hidden min-w-0 sm:block"><span class="block max-w-28 truncate text-xs font-semibold text-slate-900">{{ $user->name }}</span><span class="block text-[11px] text-slate-500">{{ $user->role?->name }}</span></span><svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor"><path d="m5.5 7.5 4.5 4 4.5-4"/></svg></button>
                <div x-show="open" x-transition.origin.top.right @click.outside="open = false" style="display:none" class="absolute right-0 top-12 z-50 w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"><a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Profil</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-700 hover:bg-rose-50">Keluar</button></form></div>
            </div>
        </header>
        <main class="app-main mx-auto max-w-[1600px] px-3 py-5 sm:px-5 lg:px-6 lg:py-6">{{ $slot }}</main>
    </div>
</div>
<script>
    document.addEventListener('keydown', event => {
        if (event.key === 'F2') { event.preventDefault(); (document.getElementById('catalog-search') || document.getElementById('global-search'))?.focus(); }
    });
</script>
</body>
</html>
