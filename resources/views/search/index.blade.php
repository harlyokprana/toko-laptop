<x-dashboard-layout title="Pencarian">
    <div class="mb-6"><p class="text-sm text-slate-500">Hasil pencarian untuk</p><h2 class="mt-1 text-2xl font-bold text-slate-950">“{{ $term }}”</h2></div>
    @if($results->isEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center"><span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-500"><x-nav-icon name="search" class="h-5 w-5"/></span><h3 class="mt-4 font-semibold text-slate-900">Tidak ada hasil yang cocok.</h3><p class="mt-1 text-sm text-slate-500">Coba cari dengan kode produk, invoice, nama pelanggan, nomor service, atau serial.</p></section>
    @else
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><ul class="divide-y divide-slate-100">@foreach($results as $result)<li><a href="{{ $result['url'] }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-blue-50/60"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ $result['title'] }}</span><span class="mt-1 block truncate text-xs text-slate-500">{{ $result['detail'] }}</span></span><span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">{{ $result['type'] }}</span></a></li>@endforeach</ul></section>
    @endif
</x-dashboard-layout>
