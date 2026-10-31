<x-dashboard-layout title="Dashboard">
    @php
        $tone = [
            'emerald' => ['box' => 'bg-emerald-50 text-emerald-600', 'text' => 'text-emerald-700'],
            'blue' => ['box' => 'bg-blue-50 text-blue-600', 'text' => 'text-blue-700'],
            'violet' => ['box' => 'bg-violet-50 text-violet-600', 'text' => 'text-violet-700'],
            'amber' => ['box' => 'bg-amber-50 text-amber-600', 'text' => 'text-amber-700'],
            'cyan' => ['box' => 'bg-cyan-50 text-cyan-600', 'text' => 'text-cyan-700'],
        ];
        $dateTitle = $date->locale('id')->translatedFormat('l, d F Y');
        $serviceTone = [
            'blue' => ['dot' => 'bg-blue-500', 'badge' => 'bg-blue-50 text-blue-700'],
            'amber' => ['dot' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-700'],
            'orange' => ['dot' => 'bg-orange-500', 'badge' => 'bg-orange-50 text-orange-700'],
            'sky' => ['dot' => 'bg-sky-500', 'badge' => 'bg-sky-50 text-sky-700'],
            'violet' => ['dot' => 'bg-violet-500', 'badge' => 'bg-violet-50 text-violet-700'],
            'emerald' => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700'],
            'slate' => ['dot' => 'bg-slate-400', 'badge' => 'bg-slate-100 text-slate-700'],
        ];
    @endphp
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-start gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-700 text-white"><x-nav-icon name="receipt" class="h-5 w-5"/></span><div><p class="text-xs font-medium uppercase tracking-wide text-slate-400">Dashboard</p><h2 class="mt-0.5 text-xl font-bold tracking-tight text-slate-950">Selamat datang, {{ $user->name }}.</h2><p class="mt-0.5 text-sm text-slate-500">Berikut ringkasan aktivitas {{ $date->isToday() ? 'hari ini' : 'tanggal '.$date->locale('id')->translatedFormat('d F Y') }}.</p></div></div>
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm"><label for="dashboard-date" class="sr-only">Pilih tanggal dashboard</label><x-nav-icon name="receipt" class="h-4 w-4 text-slate-500"/><input id="dashboard-date" type="date" name="date" value="{{ $date->toDateString() }}" class="border-0 bg-transparent p-0 text-xs font-medium text-slate-700 focus:ring-0"><button class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-700">Tampilkan</button></form>
    </div>

    @if($isCashier && $currentShift)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3"><p class="text-sm text-emerald-900">Shift aktif sejak {{ $currentShift->opened_at->format('H:i') }}.</p><a href="{{ route('cash.shifts.index') }}" class="text-sm font-semibold text-emerald-800 hover:underline">Kelola shift →</a></div>
    @elseif($isCashier)
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"><p class="text-sm text-amber-900">Shift belum dibuka. Buka shift sebelum menerima transaksi.</p><a href="{{ route('cash.shifts.index') }}" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Buka Shift</a></div>
    @endif

    @if(count($metrics))
        <section aria-label="Ringkasan hari ini" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($metrics as $metric)
                <article class="flex min-h-28 items-center gap-4 rounded-xl border border-slate-100 bg-white p-4 shadow-sm shadow-slate-900/[0.03] sm:p-5">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl {{ $tone[$metric['tone']]['box'] }}"><x-nav-icon :name="$metric['icon']" class="h-6 w-6"/></span>
                    <div class="min-w-0 flex-1"><h3 class="truncate text-xs font-medium text-slate-500">{{ $metric['label'] }}</h3><p class="mt-1 truncate text-xl font-bold tracking-tight text-slate-950">{{ $metric['value'] }}</p><div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1"><span class="text-[11px] text-slate-500">{{ $metric['detail'] }}</span><span class="text-[11px] font-semibold {{ $metric['comparison']['up'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $metric['comparison']['up'] ? '↑' : '↓' }} {{ $metric['comparison']['label'] }}</span></div></div>
                </article>
            @endforeach
        </section>
    @else
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">Tidak ada ringkasan yang dapat ditampilkan untuk permission akun ini.</div>
    @endif

    <div class="mt-4 grid gap-3 xl:grid-cols-12">
        @if($canSeeSales)
            <section class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm shadow-slate-900/[0.03] xl:col-span-5">
                <div class="flex items-center justify-between gap-2"><div class="flex items-center gap-2"><x-nav-icon name="chart" class="h-5 w-5 text-slate-800"/><div><h3 class="text-sm font-bold text-slate-900">Penjualan 7 Hari Terakhir</h3><p class="text-[11px] text-slate-500">{{ 'Rp '.number_format((float) $chartTotal, 0, ',', '.') }} total</p></div></div><a href="{{ route('module.sales') }}" class="text-xs font-semibold text-blue-700 hover:underline">Penjualan</a></div>
                @if($salesChart->sum('amount') <= 0)
                    <div class="mt-5 grid h-48 place-items-center rounded-lg bg-slate-50 text-center"><div><p class="text-sm font-medium text-slate-600">Belum ada transaksi pada periode ini.</p><p class="mt-1 text-xs text-slate-400">Grafik akan terisi dari transaksi yang tercatat.</p></div></div>
                @else
                    <div class="relative mt-5 h-48 border-b border-l border-slate-200 px-2"><div class="absolute inset-0 flex flex-col justify-between py-1 text-[10px] text-slate-400"><span>Rp {{ number_format($chartMax, 0, ',', '.') }}</span><span>Rp {{ number_format($chartMax / 2, 0, ',', '.') }}</span><span>Rp 0</span></div><div class="relative z-10 grid h-full grid-cols-7 items-end gap-2 pl-12 pr-1">@foreach($salesChart as $point)@php $barHeight = $chartMax > 0 ? ($point['amount'] / $chartMax * 100) : 0; @endphp<div class="group flex h-full flex-col items-center justify-end"><div class="mb-1 hidden whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white group-hover:block">{{ 'Rp '.number_format($point['amount'], 0, ',', '.') }}</div><div title="{{ $point['label'] }} · {{ 'Rp '.number_format($point['amount'], 0, ',', '.') }}" class="w-full max-w-9 rounded-t-sm bg-gradient-to-t from-blue-600 to-sky-400 transition hover:brightness-95" style="height: {{ max($point['amount'] > 0 ? 4 : 0, $barHeight) }}%"></div></div>@endforeach</div></div>
                    <div class="ml-12 mt-2 grid grid-cols-7 gap-2 text-center text-[10px] text-slate-500">@foreach($salesChart as $point)<span>{{ $point['label'] }}</span>@endforeach</div>
                @endif
            </section>
        @endif

        @if($canSeeSales)
            <section class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm shadow-slate-900/[0.03] xl:col-span-4">
                <div class="flex items-center gap-2"><x-nav-icon name="stock" class="h-5 w-5 text-slate-800"/><div><h3 class="text-sm font-bold text-slate-900">Metode Pembayaran Hari Ini</h3><p class="text-[11px] text-slate-500">{{ 'Rp '.number_format((float) $paymentTotal, 0, ',', '.') }} total penerimaan</p></div></div>
                @if($paymentTotal <= 0)
                    <div class="mt-5 flex h-40 items-center justify-center rounded-lg bg-slate-50 text-center text-sm text-slate-500">Belum ada pembayaran pada tanggal ini.</div>
                @else
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-4 sm:justify-between"><div class="relative h-40 w-40 shrink-0 rounded-full" style="background: {{ $paymentGradient }}"><div class="absolute inset-7 flex flex-col items-center justify-center rounded-full bg-white text-center"><span class="text-[10px] text-slate-500">Total</span><strong class="mt-1 text-xs text-slate-900">{{ 'Rp '.number_format((float) $paymentTotal, 0, ',', '.') }}</strong></div></div><div class="min-w-40 flex-1 space-y-2">@foreach($paymentMethods as $method)<div class="flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:{{ $method['color'] }}"></span><span class="min-w-0 flex-1 truncate text-xs text-slate-600">{{ $method['label'] }}</span><span class="whitespace-nowrap text-right text-[11px] font-medium text-slate-800">{{ 'Rp '.number_format((float) $method['amount'], 0, ',', '.') }}<span class="ml-1 text-slate-400">{{ number_format($method['percent'], 1, ',', '.') }}%</span></span></div>@endforeach</div></div>
                @endif
            </section>
        @endif

        @if($canSeeServices)
            <section class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm shadow-slate-900/[0.03] xl:col-span-3">
                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><x-nav-icon name="service" class="h-5 w-5 text-slate-800"/><h3 class="text-sm font-bold text-slate-900">Status Service</h3></div><a href="{{ route('module.service') }}" class="text-xs font-semibold text-blue-700 hover:underline">Lihat Semua</a></div>
                <div class="mt-3 space-y-1">@foreach($serviceStatuses as $item)<a href="{{ route('module.service', ['status' => $item['status']]) }}" class="flex items-center justify-between gap-3 rounded-lg px-1.5 py-1.5 hover:bg-slate-50"><span class="flex min-w-0 items-center gap-2 text-xs text-slate-700"><span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $serviceTone[$item['color']]['dot'] }}"></span><span class="truncate">{{ $item['label'] }}</span></span><span class="min-w-7 rounded-md px-2 py-1 text-center text-[11px] font-semibold {{ $serviceTone[$item['color']]['badge'] }}">{{ $item['count'] }}</span></a>@endforeach</div>
            </section>
        @endif
    </div>

    <div class="mt-3 grid gap-3 xl:grid-cols-12">
        @if($canSeeStock)
            <section class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm shadow-slate-900/[0.03] xl:col-span-4">
                <div class="flex items-center justify-between px-4 py-3"><div class="flex items-center gap-2"><x-nav-icon name="stock" class="h-5 w-5 text-slate-800"/><h3 class="text-sm font-bold text-slate-900">Stok Menipis</h3></div><a href="{{ route('stock.index', ['stock' => 'low']) }}" class="text-xs font-semibold text-blue-700 hover:underline">Lihat Semua</a></div>
                @if($lowStockProducts->isEmpty())<div class="border-t border-slate-100 px-4 py-8 text-center text-xs text-slate-500">Tidak ada produk yang mencapai batas stok minimum.</div>@else<div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Kode</th><th class="px-3 py-2">Nama Produk</th><th class="px-3 py-2 text-right">Stok</th><th class="px-3 py-2 text-right">Min.</th><th class="px-3 py-2">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($lowStockProducts as $product)<tr><td class="whitespace-nowrap px-3 py-2.5 font-medium text-slate-700">{{ $product->kode_produk }}</td><td class="max-w-36 px-3 py-2.5 text-slate-700"><div class="truncate">{{ $product->nama_produk }}</div>@if($product->spesifikasiRingkas())<div class="truncate text-[10px] text-slate-500">{{ $product->spesifikasiRingkas() }}</div>@endif</td><td class="px-3 py-2.5 text-right">{{ number_format((float) $product->stok, 0, ',', '.') }}</td><td class="px-3 py-2.5 text-right">{{ number_format((float) $product->stok_minimum, 0, ',', '.') }}</td><td class="px-3 py-2.5"><span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">Menipis</span></td></tr>@endforeach</tbody></table></div>@endif
            </section>
        @endif
        @if($canSeeSales)
            <section class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm shadow-slate-900/[0.03] xl:col-span-5">
                <div class="flex items-center justify-between px-4 py-3"><div class="flex items-center gap-2"><x-nav-icon name="receipt" class="h-5 w-5 text-slate-800"/><h3 class="text-sm font-bold text-slate-900">Penjualan Terbaru</h3></div><a href="{{ route('module.sales') }}" class="text-xs font-semibold text-blue-700 hover:underline">Lihat Semua</a></div>
                @if($recentSales->isEmpty())<div class="border-t border-slate-100 px-4 py-8 text-center text-xs text-slate-500">Belum ada transaksi penjualan.</div>@else<div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-2">Invoice</th><th class="px-3 py-2">Waktu</th><th class="px-3 py-2">Pelanggan</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($recentSales as $sale)<tr><td class="whitespace-nowrap px-3 py-2.5"><a href="{{ route('sales.show', $sale) }}" class="font-semibold text-blue-700 hover:underline">{{ $sale->no_invoice }}</a></td><td class="whitespace-nowrap px-3 py-2.5 text-slate-600">{{ $sale->created_at->format('H:i') }}</td><td class="max-w-28 truncate px-3 py-2.5">{{ $sale->pelanggan?->nama ?? 'Umum' }}</td><td class="whitespace-nowrap px-3 py-2.5 text-right">{{ 'Rp '.number_format((float) $sale->total, 0, ',', '.') }}</td><td class="px-3 py-2.5"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $sale->status === 'SELESAI' ? 'bg-emerald-50 text-emerald-700' : ($sale->status === 'BATAL' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800') }}">{{ $sale->status === 'SELESAI' ? 'Lunas' : str_replace('_', ' ', ucfirst(strtolower($sale->status))) }}</span></td></tr>@endforeach</tbody></table></div>@endif
            </section>
        @endif
        @if($canSeeServices)
            <section class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm shadow-slate-900/[0.03] xl:col-span-3">
                <div class="flex items-center justify-between px-4 py-3"><div class="flex items-center gap-2"><x-nav-icon name="service" class="h-5 w-5 text-slate-800"/><h3 class="text-sm font-bold text-slate-900">Service Terbaru</h3></div><a href="{{ route('module.service') }}" class="text-xs font-semibold text-blue-700 hover:underline">Lihat Semua</a></div>
                @if($recentServices->isEmpty())<div class="border-t border-slate-100 px-4 py-8 text-center text-xs text-slate-500">Belum ada service.</div>@else<div class="divide-y divide-slate-100">@foreach($recentServices as $service)<a href="{{ route('service.show', $service) }}" class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-slate-50"><span class="min-w-0"><span class="block truncate text-xs font-semibold text-blue-700">{{ $service->no_service }}</span><span class="mt-0.5 block truncate text-[11px] text-slate-500">{{ $service->pelanggan?->nama ?? '—' }}</span></span><span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-medium text-blue-700">{{ str_replace('_', ' ', ucfirst(strtolower($service->status))) }}</span></a>@endforeach</div>@endif
            </section>
        @endif
    </div>

    <section class="mt-3 overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm shadow-slate-900/[0.03]">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3"><x-nav-icon name="receipt" class="h-5 w-5 text-slate-800"/><div><h3 class="text-sm font-bold text-slate-900">Aktivitas Terakhir</h3><p class="text-[11px] text-slate-500">Peristiwa yang tercatat di transaksi dan progress.</p></div></div>
        @if($activities->isEmpty())<div class="px-4 py-8 text-center text-sm text-slate-500">Belum ada aktivitas pada tanggal ini.</div>@else<div class="overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-2.5">Waktu</th><th class="px-4 py-2.5">User</th><th class="px-4 py-2.5">Aktivitas</th><th class="px-4 py-2.5">Keterangan</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($activities as $activity)<tr class="hover:bg-slate-50"><td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $activity['at']?->format('H:i') ?? '—' }}</td><td class="whitespace-nowrap px-4 py-2.5">{{ $activity['user'] }}</td><td class="whitespace-nowrap px-4 py-2.5 font-medium text-slate-800">{{ $activity['activity'] }}</td><td class="px-4 py-2.5 text-slate-600">{{ $activity['detail'] }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>
</x-dashboard-layout>
