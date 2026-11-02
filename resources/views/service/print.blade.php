<x-dashboard-layout :title="$document === 'intake' ? 'Tanda Terima Service' : ($balance <= 0 ? 'Kwitansi Service' : 'Tagihan Service')">
    <style>
        .print-document { display: none; }
        @media print {
            @page { margin: 14mm; }
            html, body { background: #fff !important; color: #111827 !important; }
            .app-sidebar, .app-header { display: none !important; }
            .app-main { max-width: none !important; padding: 0 !important; }
            .print-document { display: block !important; background: #fff !important; color: #111827 !important; }
            .print-document * { color: #111827 !important; }
        }
    </style>

    @php($money = fn ($amount) => 'Rp '.number_format((float) $amount, 0, ',', '.'))
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <div>
            <a href="{{ route('service.show', $service) }}" class="text-sm font-semibold text-blue-700">← Detail Service</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">{{ $document === 'intake' ? 'Tanda Terima Service' : ($balance <= 0 ? 'Kwitansi Pembayaran Service' : 'Tagihan Service') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $service->no_service }}</p>
        </div>
        <button type="button" onclick="window.print()" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Cetak</button>
    </div>

    <article class="print-document mx-auto max-w-3xl bg-white text-slate-900">
        <header class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-5">
            <div>
                <p class="text-xl font-extrabold tracking-tight">Laptop Prime.Com</p>
                <p class="mt-1 text-sm text-slate-600">Toko Laptop · Layanan Service</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold uppercase tracking-wide">{{ $document === 'intake' ? 'Tanda Terima Service' : ($balance <= 0 ? 'Kwitansi Pembayaran' : 'Tagihan Service') }}</h2>
                <p class="mt-1 text-sm font-semibold">{{ $service->no_service }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $document === 'intake' ? $service->tanggal_masuk?->format('d M Y H:i') : ($service->tanggal_selesai?->format('d M Y H:i') ?? $service->updated_at?->format('d M Y H:i')) }}</p>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-6 border-b border-slate-200 py-5 text-sm">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Pelanggan</p>
                <p class="font-semibold">{{ $service->pelanggan?->nama ?? '—' }}</p>
                @if($service->pelanggan?->telepon)<p class="mt-1 text-slate-600">{{ $service->pelanggan->telepon }}</p>@endif
            </div>
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Perangkat</p>
                <p class="font-semibold">{{ trim($service->jenis_perangkat.' '.$service->merek.' '.$service->model) }}</p>
                <p class="mt-1 text-slate-600">Serial: {{ $service->serial_number ?: '—' }}</p>
            </div>
        </section>

        @if($document === 'intake')
            <section class="space-y-4 py-5 text-sm">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Keluhan pelanggan</p><p class="mt-1 whitespace-pre-line">{{ $service->keluhan ?: '—' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kondisi / aksesori / catatan awal</p><p class="mt-1 whitespace-pre-line">{{ $service->keterangan ?: 'Tidak ada catatan awal.' }}</p></div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teknisi</p><p class="mt-1">{{ $service->teknisi?->name ?? 'Belum ditugaskan' }}</p></div>
            </section>
            <footer class="mt-10 grid grid-cols-2 gap-10 text-center text-sm">
                <div class="border-t border-slate-400 pt-2">Petugas penerima</div>
                <div class="border-t border-slate-400 pt-2">Pelanggan</div>
            </footer>
        @else
            <table class="my-5 w-full border-collapse text-sm">
                <thead><tr class="border-b border-slate-300 text-left text-xs uppercase tracking-wide text-slate-600"><th class="py-2 pr-3">Rincian</th><th class="py-2 pl-3 text-right">Jumlah</th></tr></thead>
                <tbody>
                    <tr class="border-b border-slate-100"><td class="py-3 pr-3">Jasa service</td><td class="py-3 pl-3 text-right">{{ $money($service->jasa) }}</td></tr>
                    @foreach($service->details as $detail)
                        <tr class="border-b border-slate-100"><td class="py-3 pr-3">{{ $detail->produk?->nama_produk ?? 'Sparepart' }} × {{ number_format((float) $detail->qty, 0, ',', '.') }}</td><td class="py-3 pl-3 text-right">{{ $money($detail->subtotal) }}</td></tr>
                    @endforeach
                    @if((float) $service->diskon > 0)<tr><td class="py-2 pr-3">Diskon</td><td class="py-2 pl-3 text-right">− {{ $money($service->diskon) }}</td></tr>@endif
                    @if((float) $service->pajak > 0)<tr><td class="py-2 pr-3">Pajak</td><td class="py-2 pl-3 text-right">{{ $money($service->pajak) }}</td></tr>@endif
                    @if((float) $service->biaya_tambahan > 0)<tr><td class="py-2 pr-3">Biaya tambahan</td><td class="py-2 pl-3 text-right">{{ $money($service->biaya_tambahan) }}</td></tr>@endif
                </tbody>
            </table>
            <section class="ml-auto w-full max-w-sm space-y-2 border-t border-slate-300 py-4 text-sm">
                <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ $money($service->total) }}</span></div>
                <div class="flex justify-between"><span>Sudah dibayar</span><span>{{ $money($service->dibayar) }}</span></div>
                <div class="flex justify-between font-semibold"><span>Sisa tagihan</span><span>{{ $money($balance) }}</span></div>
            </section>
            <section class="mt-5">
                <h3 class="font-semibold">Riwayat pembayaran</h3>
                @forelse($payments as $payment)
                    <div class="flex justify-between gap-4 border-b border-slate-100 py-2 text-sm"><span>{{ $payment->tanggal?->format('d M Y') }} · {{ $payment->metode }}</span><span>{{ $money($payment->nominal) }}</span></div>
                @empty
                    <p class="mt-2 text-sm text-slate-600">Belum ada pembayaran tercatat.</p>
                @endforelse
            </section>
            <footer class="mt-10 border-t border-slate-200 pt-4 text-center text-xs text-slate-500">Simpan dokumen ini sebagai bukti transaksi service.</footer>
        @endif
    </article>

    @if($document === 'intake')<script>window.addEventListener('load', () => window.print());</script>@endif
</x-dashboard-layout>
