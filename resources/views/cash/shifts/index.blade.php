<x-dashboard-layout title="Shift Kasir">
    <div class="mb-7"><p class="text-sm text-slate-500">Catat kas awal dan rekonsiliasi kas pada akhir shift.</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Shift Kasir</h2></div>
    @if(session('status'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    @if(!$activeShift)
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-8"><h3 class="text-lg font-semibold text-slate-900">Belum ada shift aktif</h3><p class="mt-1 text-sm text-slate-500">Masukkan jumlah kas awal sebelum mulai melayani transaksi.</p>
            <form method="POST" action="{{ route('cash.shifts.open') }}" class="mt-6 space-y-5">@csrf
                <label class="block text-sm font-medium text-slate-700">Kas awal (Rp)<input name="opening_cash" type="number" min="0" step="0.01" required value="{{ old('opening_cash', 0) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="block text-sm font-medium text-slate-700">Catatan pembukaan<textarea name="opening_note" rows="2" maxlength="1000" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">{{ old('opening_note') }}</textarea></label>
                <button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Buka Shift</button>
            </form>
        </section>
    @else
        <section class="mb-6 grid gap-4 sm:grid-cols-3"><article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Mulai shift</p><p class="mt-2 font-semibold text-slate-900">{{ $activeShift->opened_at->format('d M Y H:i') }}</p></article><article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Kas awal</p><p class="mt-2 text-xl font-bold text-slate-900">Rp {{ number_format((float) $activeShift->opening_cash, 0, ',', '.') }}</p></article><article class="rounded-2xl border border-blue-200 bg-blue-50 p-5"><p class="text-sm text-blue-800">Kas seharusnya saat ini</p><p class="mt-2 text-xl font-bold text-blue-950">Rp {{ number_format($expectedCash, 0, ',', '.') }}</p><p class="mt-1 text-xs text-blue-800">Kas awal ditambah penerimaan tunai tercatat selama shift.</p></article></section>
        <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 sm:p-8"><h3 class="text-lg font-semibold text-slate-900">Tutup Shift</h3><p class="mt-1 text-sm text-slate-500">Hitung uang fisik, masukkan hasilnya, lalu simpan untuk melihat selisih kas.</p>
            <form method="POST" action="{{ route('cash.shifts.close', $activeShift) }}" class="mt-6 space-y-5">@csrf
                <label class="block text-sm font-medium text-slate-700">Kas fisik saat tutup (Rp)<input name="closing_cash" type="number" min="0" step="0.01" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="block text-sm font-medium text-slate-700">Catatan penutupan<textarea name="closing_note" rows="2" maxlength="1000" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></textarea></label>
                <button class="rounded-lg bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white">Tutup Shift</button>
            </form>
        </section>
    @endif
    <section class="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold text-slate-900">Riwayat Shift</h3></div>
        @if($shifts->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-500">Belum ada shift yang ditutup.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Mulai</th><th class="px-5 py-3">Selesai</th><th class="px-5 py-3">Kas Seharusnya</th><th class="px-5 py-3">Kas Fisik</th><th class="px-5 py-3">Selisih</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($shifts as $shift)
                            <tr><td class="px-5 py-4">{{ $shift->opened_at->format('d M Y H:i') }}</td><td class="px-5 py-4">{{ $shift->closed_at?->format('d M Y H:i') }}</td><td class="px-5 py-4">Rp {{ number_format((float) $shift->expected_cash, 0, ',', '.') }}</td><td class="px-5 py-4">Rp {{ number_format((float) $shift->closing_cash, 0, ',', '.') }}</td><td class="px-5 py-4 font-semibold {{ (float) $shift->cash_difference < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rp {{ number_format((float) $shift->cash_difference, 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($shifts->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">{{ $shifts->links() }}</div>
            @endif
        @endif
    </section>
</x-dashboard-layout>
