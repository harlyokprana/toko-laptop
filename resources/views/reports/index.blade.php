<x-dashboard-layout title="Laporan">
    <div class="mb-6"><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Analisis bisnis</p><h1 class="mt-1 text-2xl font-bold text-slate-950">Laporan</h1><p class="mt-1 max-w-3xl text-sm text-slate-500">Pilih laporan untuk mengatur periode, mencari data, mencetak, atau mengunduh CSV yang dapat dibuka di Excel.</p></div>
    @php $reports = is_array($reports ?? null) ? $reports : []; @endphp
    @if(empty($reports))
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Tidak ada laporan yang tersedia untuk akun ini.</div>
    @else
    @foreach(collect($reports)->groupBy(fn ($definition) => $definition[2], true) as $category => $items)
        <section class="mb-6"><h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-600">{{ $category }}</h2><div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">@foreach($items as $key => [$title,$description])<a href="{{ route('reports.show', $key) }}" class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md"><span class="flex items-center justify-between"><span class="font-semibold text-slate-900 group-hover:text-blue-700">{{ $title }}</span><span class="text-blue-600">→</span></span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $description }}</span></a>@endforeach</div></section>
    @endforeach
    @endif
</x-dashboard-layout>
