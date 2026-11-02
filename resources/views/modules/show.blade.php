<x-dashboard-layout :title="$title">
    <div class="mb-7 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">Ringkasan dan data {{ strtolower($title) }}.</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">{{ $title }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($module === 'sales' && auth()->user()->hasPermission('create_sales'))
                <a href="{{ route('sales.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Transaksi Penjualan</a>
            @endif
            @if ($module === 'service' && auth()->user()->hasPermission('create_service'))
                <a href="{{ route('service.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Terima Service</a>
            @endif
            @if ($module === 'income' && auth()->user()->hasPermission('create_income'))
                <a href="{{ route('income.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Setor Pendapatan</a>
            @endif
            @if ($module === 'users' && auth()->user()->hasPermission('manage_users'))
                <a href="{{ route('users.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Tambah Pengguna</a>
            @endif
        </div>
    </div>

    @if (session('status'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif

    @if (count($stats))
        <section aria-label="Ringkasan {{ $title }}" class="mb-6 grid gap-4 sm:grid-cols-2 {{ count($stats) > 2 ? 'xl:grid-cols-4' : 'xl:grid-cols-2' }}">
            @foreach ($stats as $stat)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-900/[0.02]">
                    <h3 class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</h3>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-slate-950">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $stat['detail'] }}</p>
                </article>
            @endforeach
        </section>
    @endif

    @if ($notice)
        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">{{ $notice }}</div>
    @endif

    @if ($columns)
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @if ($rows->isEmpty())
                <div class="px-6 py-14 text-center">
                    <h3 class="font-semibold text-slate-900">Belum ada data {{ strtolower($title) }}</h3>
                    <p class="mt-1 text-sm text-slate-500">Catatan akan muncul di halaman ini setelah transaksi dibuat.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>@foreach ($columns as $key => $label)<th class="whitespace-nowrap px-5 py-3">{{ $label }}</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $row)
                                <tr class="align-top hover:bg-slate-50/70">
                                    @foreach ($columns as $key => $label)
                                        <td class="max-w-sm px-5 py-4 text-slate-700 {{ in_array($key, ['invoice', 'nomor', 'kode', 'tanggal', 'masuk', 'waktu'], true) ? 'whitespace-nowrap' : '' }}">
                                            @if ($module === 'sales' && $key === 'invoice')
                                                <a href="{{ route('sales.show', $row['id']) }}" class="font-semibold text-blue-700 hover:underline">{{ $row[$key] }}</a>
                                            @elseif ($module === 'service-progress' && $key === 'service')
                                                <a href="{{ route('service.show', $row['id']) }}" class="font-semibold text-blue-700 hover:underline">{{ $row[$key] }}</a>
                                            @else
                                                {{ $row[$key] ?? '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($rows->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $rows->links() }}</div>@endif
            @endif
        </div>
    @elseif ($module === 'ai')
        <section class="rounded-2xl border border-slate-200 bg-white p-7 sm:p-9">
            <div class="grid h-12 w-12 place-items-center rounded-xl bg-violet-50 text-violet-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.36-6.36-2.12 2.12M7.76 16.24l-2.12 2.12m12.72 0-2.12-2.12M7.76 7.76 5.64 5.64"/><circle cx="12" cy="12" r="4"/></svg></div>
            <h3 class="mt-5 text-lg font-semibold text-slate-900">Ruang AI</h3>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Menu ini sudah terbuka untuk role yang memiliki permission <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">use_ai</code>. Untuk mulai memakai asisten, aplikasi masih memerlukan provider AI dan konfigurasi kredensial.</p>
        </section>
    @endif
</x-dashboard-layout>
