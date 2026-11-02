<x-dashboard-layout title="Detail Penjualan">
    <style>
        .print-invoice { display: none; }
        @media print {
            @page { margin: 14mm; }
            :root { color-scheme: light !important; }
            html, body { background: #fff !important; color: #111827 !important; }
            .app-sidebar, .app-header { display: none !important; }
            .app-main { max-width: none !important; padding: 0 !important; }
            .print-invoice { display: block !important; background: #fff !important; color: #111827 !important; }
            .print-invoice * { color: #111827 !important; }
        }
    </style>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 print:hidden"><div><a href="{{ route('module.sales') }}" class="text-sm font-semibold text-blue-700">← Kembali ke penjualan</a><h2 class="mt-3 text-2xl font-bold text-slate-950">Detail Transaksi</h2><p class="mt-1 text-sm text-slate-500">Invoice {{ $sale->no_invoice }}</p></div><button type="button" onclick="window.print()" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Cetak</button></div>
    @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 print:hidden">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 print:hidden">{{ $errors->first() }}</div>@endif
    <article id="print-invoice" class="print-invoice mx-auto max-w-4xl bg-white text-slate-900">
        <header class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-5">
            <div>
                <p class="text-xl font-extrabold tracking-tight">Laptop Prime.Com</p>
                <p class="mt-1 text-sm text-slate-600">Toko Laptop · Penjualan dan layanan</p>
            </div>
            <div class="text-right">
                <h1 class="text-2xl font-bold uppercase tracking-widest">Invoice</h1>
                <p class="mt-1 text-sm font-semibold">{{ $sale->no_invoice }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ ($sale->tanggal ?? $sale->created_at)->format('d M Y') }}</p>
            </div>
        </header>
        <section class="grid grid-cols-2 gap-8 border-b border-slate-200 py-5 text-sm">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Tagihan kepada</p>
                <p class="font-semibold">{{ $sale->pelanggan?->nama ?? 'Pelanggan umum' }}</p>
                @if($sale->pelanggan?->telepon)<p class="mt-1 text-slate-600">{{ $sale->pelanggan->telepon }}</p>@endif
            </div>
            <div class="text-right">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Status pembayaran</p>
                <p class="font-semibold">{{ str_replace('_', ' ', $sale->status) }}</p>
            </div>
        </section>
        <table class="my-5 w-full border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-300 text-left text-xs uppercase tracking-wide text-slate-600">
                    <th class="py-2 pr-3">Produk</th>
                    <th class="px-3 py-2 text-right">Qty</th>
                    <th class="px-3 py-2 text-right">Harga</th>
                    <th class="px-3 py-2 text-right">Diskon</th>
                    <th class="py-2 pl-3 text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $detail)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 pr-3 font-medium">{{ $detail->produk?->nama_produk ?? 'Produk' }}</td>
                        <td class="px-3 py-3 text-right">{{ number_format((float) $detail->qty, 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right">Rp {{ number_format((float) $detail->harga, 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right">Rp {{ number_format((float) $detail->diskon, 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap py-3 pl-3 text-right font-semibold">Rp {{ number_format((float) $detail->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <section class="ml-auto w-full max-w-sm space-y-2 border-b border-slate-200 pb-5 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span>Rp {{ number_format((float) $sale->subtotal, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Diskon transaksi</span><span>− Rp {{ number_format((float) $sale->diskon, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>PPN ({{ number_format((float) $sale->pajak_persen, 2, ',', '.') }}%)</span><span>Rp {{ number_format((float) $sale->pajak, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Biaya tambahan</span><span>Rp {{ number_format((float) $sale->biaya_tambahan, 0, ',', '.') }}</span></div>
            <div class="flex justify-between border-t border-slate-300 pt-2 text-base font-bold"><span>Total</span><span>Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span>Sudah dibayar</span><span>Rp {{ number_format((float) $sale->dibayar, 0, ',', '.') }}</span></div>
            <div class="flex justify-between font-semibold"><span>Sisa tagihan</span><span>Rp {{ number_format(max(0, (float) $sale->total - (float) $sale->dibayar), 0, ',', '.') }}</span></div>
        </section>
        @if($sale->keterangan)<p class="mt-4 text-sm"><span class="font-semibold">Catatan:</span> {{ $sale->keterangan }}</p>@endif
        <footer class="mt-10 border-t border-slate-200 pt-4 text-center text-xs text-slate-500">Terima kasih telah berbelanja di Laptop Prime.Com.</footer>
    </article>
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(300px,0.6fr)] print:hidden">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-wrap justify-between gap-4 border-b border-slate-100 p-5"><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Invoice</p><h3 class="mt-1 text-xl font-bold text-slate-950">{{ $sale->no_invoice }}</h3><p class="mt-1 text-sm text-slate-500">{{ $sale->created_at->format('d M Y, H:i') }}</p></div><span class="h-fit rounded-full px-3 py-1.5 text-xs font-bold {{ $sale->status === 'SELESAI' ? 'bg-emerald-100 text-emerald-800' : ($sale->status === 'BATAL' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-900') }}">{{ str_replace('_', ' ', $sale->status) }}</span></div>
            <div class="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-3"><div><p class="text-xs text-slate-500">Pelanggan</p><p class="mt-1 font-medium text-slate-900">{{ $sale->pelanggan?->nama ?? 'Pelanggan umum' }}</p></div><div><p class="text-xs text-slate-500">Kasir</p><p class="mt-1 font-medium text-slate-900">{{ $sale->user?->name ?? '—' }}</p></div><div><p class="text-xs text-slate-500">Jenis</p><p class="mt-1 font-medium text-slate-900">{{ str_replace('_', ' ', $sale->jenis_penjualan) }}</p></div></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Serial</th><th class="px-5 py-3 text-right">Qty</th><th class="px-5 py-3 text-right">Harga</th><th class="px-5 py-3 text-right">Diskon</th><th class="px-5 py-3 text-right">Subtotal</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($sale->details as $detail)@php($specification = $detail->produk?->spesifikasiRingkas($detail->laptop))<tr><td class="px-5 py-4"><span class="font-medium text-slate-900">{{ $detail->produk?->nama_produk ?? 'Produk' }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $detail->produk?->kode_produk ?? '—' }}</span>@if($specification)<span class="mt-0.5 block text-xs text-slate-600">{{ $specification }}</span>@endif</td><td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $detail->laptop?->serial_number ?? '—' }}</td><td class="px-5 py-4 text-right">{{ number_format((float) $detail->qty, 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-4 text-right">Rp {{ number_format((float) $detail->harga, 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-4 text-right">Rp {{ number_format((float) $detail->diskon, 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-4 text-right font-semibold">Rp {{ number_format((float) $detail->subtotal, 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
            <div class="border-t border-slate-100 p-5"><div class="ml-auto max-w-sm space-y-2 text-sm"><div class="flex justify-between text-slate-600"><span>Subtotal</span><span>Rp {{ number_format((float) $sale->subtotal, 0, ',', '.') }}</span></div><div class="flex justify-between text-slate-600"><span>Diskon</span><span>− Rp {{ number_format((float) $sale->diskon, 0, ',', '.') }}</span></div><div class="flex justify-between text-slate-600"><span>PPN ({{ number_format((float) $sale->pajak_persen, 2, ',', '.') }}%)</span><span>Rp {{ number_format((float) $sale->pajak, 0, ',', '.') }}</span></div><div class="flex justify-between text-slate-600"><span>Biaya tambahan</span><span>Rp {{ number_format((float) $sale->biaya_tambahan, 0, ',', '.') }}</span></div><div class="flex justify-between border-t border-slate-200 pt-3 text-base font-bold text-slate-950"><span>Total</span><span>Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</span></div><div class="flex justify-between text-slate-600"><span>Sudah dibayar</span><span>Rp {{ number_format((float) $sale->dibayar, 0, ',', '.') }}</span></div><div class="flex justify-between font-semibold {{ (float) $sale->total - (float) $sale->dibayar > 0 ? 'text-amber-800' : 'text-emerald-800' }}"><span>{{ $sale->status === 'DRAFT' ? 'Draft · belum dibayar' : 'Sisa tagihan' }}</span><span>Rp {{ number_format(max(0, (float) $sale->total - (float) $sale->dibayar), 0, ',', '.') }}</span></div></div></div>
            @if($sale->keterangan)<div class="border-t border-slate-100 px-5 py-4 text-sm text-slate-600"><span class="font-semibold">Catatan:</span> {{ $sale->keterangan }}</div>@endif
        </section>
        <div class="space-y-5">
            @if(auth()->user()->hasPermission('collect_sales_payments') && (float) $sale->total > (float) $sale->dibayar && ! in_array($sale->status, ['BATAL', 'DRAFT'], true))
                <section class="rounded-2xl border border-slate-200 bg-white p-5 print:hidden"><h3 class="font-bold text-slate-900">Terima Pembayaran</h3><p class="mt-1 text-sm text-slate-500">Sisa tagihan Rp {{ number_format((float) $sale->total - (float) $sale->dibayar, 0, ',', '.') }}.</p>@if(auth()->user()->role?->name === 'KASIR' && ! \App\Models\KasirShift::where('user_id', auth()->id())->where('status', 'OPEN')->exists())<a href="{{ route('cash.shifts.index') }}" class="mt-4 inline-block rounded-lg bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-900">Buka shift untuk menerima pembayaran</a>@else<form method="POST" action="{{ route('sales.payments.store', $sale) }}" class="mt-4 space-y-4">@csrf<label class="block text-sm font-medium text-slate-700">Jumlah<input type="number" name="nominal" min="0.01" max="{{ (float) $sale->total - (float) $sale->dibayar }}" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label><label class="block text-sm font-medium text-slate-700">Metode<select name="metode" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@foreach(['CASH' => 'Tunai', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS', 'DEBIT' => 'Debit', 'KREDIT' => 'Kredit'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Simpan Pembayaran</button></form>@endif</section>
            @endif
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-900">Riwayat Pembayaran</h3></div>@if($payments->isEmpty())<p class="px-5 py-8 text-sm text-slate-500">Belum ada pembayaran.</p>@else<div class="divide-y divide-slate-100">@foreach($payments as $payment)<div class="flex items-start justify-between gap-3 px-5 py-4"><div><p class="text-sm font-semibold text-slate-900">{{ $payment->metode === 'PIUTANG' ? 'Sisa piutang' : str_replace('_', ' ', $payment->metode) }}</p><p class="mt-1 text-xs text-slate-500">{{ $payment->created_at?->format('d M Y H:i') ?? $payment->tanggal->format('d M Y') }} · {{ $payment->status }}</p>@if($payment->keterangan)<p class="mt-1 text-xs text-slate-600">{{ $payment->keterangan }}</p>@endif</div><p class="whitespace-nowrap text-sm font-semibold {{ $payment->status === 'PENDING' ? 'text-amber-800' : 'text-slate-900' }}">Rp {{ number_format((float) $payment->nominal, 0, ',', '.') }}</p></div>@endforeach</div>@endif</section>
        </div>
    </div>
</x-dashboard-layout>
