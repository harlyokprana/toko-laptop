<x-dashboard-layout title="Daftar Service">
    @php
        $statusStyles = [
            'DITERIMA' => 'bg-blue-50 text-blue-700', 'DIAGNOSA' => 'bg-amber-50 text-amber-800',
            'MENUNGGU_PERSETUJUAN' => 'bg-orange-50 text-orange-800', 'MENUNGGU_SPAREPART' => 'bg-violet-50 text-violet-800',
            'DALAM_PERBAIKAN' => 'bg-sky-50 text-sky-800', 'TESTING' => 'bg-indigo-50 text-indigo-800',
            'SELESAI' => 'bg-emerald-50 text-emerald-800', 'SUDAH_DIAMBIL' => 'bg-slate-100 text-slate-700',
            'BATAL' => 'bg-rose-50 text-rose-800',
        ];
        $totalServices = $statusCounts->sum('count');
        $activeServices = $statusCounts->whereNotIn('status', ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL'])->sum('count');
        $pendingApprovals = $statusCounts->firstWhere('status', 'MENUNGGU_PERSETUJUAN')['count'] ?? 0;
        $completedServices = $statusCounts->whereIn('status', ['SELESAI', 'SUDAH_DIAMBIL'])->sum('count');
    @endphp
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-900 text-white"><x-nav-icon name="service" class="h-6 w-6"/></span><div><p class="text-xs text-slate-500">Service</p><h1 class="text-xl font-bold text-slate-950">{{ auth()->user()->role?->name === 'PELANGGAN' ? 'Service Saya' : 'Daftar Service' }}</h1><p class="text-xs text-slate-500">Kelola penerimaan, diagnosis, persetujuan, dan pengerjaan service.</p></div></div>
        @if(auth()->user()->hasPermission('create_service'))<a href="{{ route('service.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">＋ Terima Service</a>@endif
    </div>

    @if(session('status'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    <section class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach([['Semua Service', $totalServices, 'bg-blue-50 text-blue-700'], ['Service Aktif', $activeServices, 'bg-amber-50 text-amber-800'], ['Menunggu Approval', $pendingApprovals, 'bg-orange-50 text-orange-800'], ['Selesai', $completedServices, 'bg-emerald-50 text-emerald-800']] as [$label, $count, $style])<article class="rounded-xl border border-slate-100 bg-white p-3 shadow-sm"><p class="text-[11px] text-slate-500">{{ $label }}</p><strong class="mt-1 block text-xl {{ $style }} inline-flex rounded-md px-2 py-0.5">{{ number_format($count, 0, ',', '.') }}</strong></article>@endforeach
    </section>

    <section class="mb-4 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" action="{{ route('module.service') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
            <label class="text-[11px] font-medium text-slate-500">Dari tanggal<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block h-9 w-full rounded-lg border-slate-200 text-xs"></label>
            <label class="text-[11px] font-medium text-slate-500">Sampai tanggal<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block h-9 w-full rounded-lg border-slate-200 text-xs"></label>
            <label class="text-[11px] font-medium text-slate-500">Status<select name="status" class="mt-1 block h-9 w-full rounded-lg border-slate-200 text-xs"><option value="">Semua Status</option>@foreach($statusCounts as $item)<option value="{{ $item['status'] }}" @selected(($filters['status'] ?? '') === $item['status'])>{{ $item['label'] }}</option>@endforeach</select></label>
            @if(auth()->user()->role?->name !== 'TEKNISI')<label class="text-[11px] font-medium text-slate-500">Teknisi<select name="teknisi_id" class="mt-1 block h-9 w-full rounded-lg border-slate-200 text-xs"><option value="">Semua Teknisi</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(($filters['teknisi_id'] ?? '') == $technician->id)>{{ $technician->name }}</option>@endforeach</select></label>@endif
            @if(auth()->user()->role?->name !== 'PELANGGAN')<label class="text-[11px] font-medium text-slate-500">Pelanggan<select name="pelanggan_id" class="mt-1 block h-9 w-full rounded-lg border-slate-200 text-xs"><option value="">Semua Pelanggan</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(($filters['pelanggan_id'] ?? '') == $customer->id)>{{ $customer->nama }}</option>@endforeach</select></label>@endif
            <label class="text-[11px] font-medium text-slate-500">Cari service / serial / pelanggan<div class="mt-1 flex gap-1"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="No. service atau serial…" class="h-9 min-w-0 flex-1 rounded-lg border-slate-200 text-xs"><button class="rounded-lg bg-blue-700 px-3 text-xs font-semibold text-white">Filter</button></div></label>
            <div class="flex items-end lg:col-span-6"><a href="{{ route('module.service') }}" class="text-xs font-semibold text-slate-500 hover:text-blue-700">Reset filter</a></div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @if($services->isEmpty())<div class="px-6 py-14 text-center"><h2 class="font-semibold text-slate-900">Belum ada service pada filter ini</h2><p class="mt-1 text-sm text-slate-500">Catatan service akan muncul setelah perangkat diterima.</p>@if(auth()->user()->hasPermission('create_service'))<a href="{{ route('service.create') }}" class="mt-4 inline-flex rounded-lg bg-blue-700 px-4 py-2 text-xs font-semibold text-white">Terima Service</a>@endif</div>@else
            <div class="overflow-x-auto"><table class="min-w-[980px] w-full text-left text-xs"><thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">No. Service</th><th class="px-3 py-3">Tanggal Masuk</th><th class="px-3 py-3">Pelanggan</th><th class="px-3 py-3">Perangkat</th><th class="px-3 py-3">Keluhan</th><th class="px-3 py-3">Teknisi</th><th class="px-3 py-3">Status</th>@if($canViewBilling)<th class="px-3 py-3 text-right">Estimasi / Total</th>@endif<th class="px-3 py-3">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($services as $service)<tr class="hover:bg-slate-50"><td class="whitespace-nowrap px-3 py-3"><a href="{{ route('service.show', $service) }}" class="font-semibold text-blue-700 hover:underline">{{ $service->no_service }}</a></td><td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $service->tanggal_masuk->format('d M Y H:i') }}</td><td class="max-w-36 truncate px-3 py-3">{{ auth()->user()->role?->name === 'PELANGGAN' ? 'Service Saya' : ($service->pelanggan?->nama ?? '—') }}</td><td class="max-w-40 px-3 py-3"><span class="block truncate font-medium text-slate-800">{{ trim(($service->merek ?? '').' '.($service->model ?? '')) ?: $service->jenis_perangkat }}</span><span class="text-[10px] text-slate-500">{{ $service->serial_number ?: $service->jenis_perangkat }}</span></td><td class="max-w-44 truncate px-3 py-3 text-slate-600">{{ $service->keluhan }}</td><td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $service->teknisi?->name ?? 'Belum ditugaskan' }}</td><td class="px-3 py-3"><span class="whitespace-nowrap rounded-full px-2 py-1 text-[10px] font-semibold {{ $statusStyles[$service->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusCounts->firstWhere('status', $service->status)['label'] ?? str_replace('_', ' ', $service->status) }}</span></td>@if($canViewBilling)<td class="whitespace-nowrap px-3 py-3 text-right font-medium">Rp {{ number_format((float) $service->total, 0, ',', '.') }}</td>@endif<td class="px-3 py-3"><a href="{{ route('service.show', $service) }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 font-semibold text-blue-700 hover:bg-blue-50">Detail</a></td></tr>@endforeach</tbody></table></div>
            @if($services->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $services->links() }}</div>@endif
        @endif
    </section>
</x-dashboard-layout>
