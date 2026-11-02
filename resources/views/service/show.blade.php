<x-dashboard-layout title="Detail Service">
    @php
        $money = fn ($amount) => 'Rp '.number_format((float) $amount, 0, ',', '.');
        $statusLabels = collect($statusProgress)->keyBy('status');
        $progressEntries = $service->progress->sortBy('created_at');
        $currentProgress = (int) ($progressEntries->last()?->progress_percent ?? 0);
        $partSubtotal = (float) $service->details->sum('subtotal');
        $subtotal = (float) $service->jasa + $partSubtotal;
        $balance = max(0, (float) $service->total - (float) $service->dibayar);
        $cashierNeedsShift = auth()->user()->role?->name === 'KASIR'
            && ! \App\Models\KasirShift::where('user_id', auth()->id())->where('status', 'OPEN')->exists();
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div><a href="{{ route('module.service') }}" class="text-xs font-semibold text-blue-700">← Daftar Service</a><div class="mt-2 flex flex-wrap items-center gap-2"><h1 class="text-2xl font-bold text-slate-950">{{ $service->no_service }}</h1><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800">{{ $statusLabels->get($service->status)['label'] ?? str_replace('_', ' ', $service->status) }}</span></div><p class="mt-1 text-xs text-slate-500">Diterima {{ $service->tanggal_masuk->format('d M Y H:i') }}@if($service->tanggal_selesai) · Selesai {{ $service->tanggal_selesai->format('d M Y H:i') }}@endif</p></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('service.print', [$service, 'intake']) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">Cetak Tanda Terima</a>
            @if(in_array($service->status, ['SELESAI', 'SUDAH_DIAMBIL'], true) && $canViewBilling)
                <a href="{{ route('service.print', [$service, 'final']) }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-xs font-semibold text-white">{{ $balance <= 0 ? 'Cetak Kwitansi' : 'Cetak Tagihan' }}</a>
            @endif
            @if(auth()->user()->hasPermission('create_service') && auth()->user()->role?->name !== 'PELANGGAN')<a href="{{ route('service.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-xs font-semibold text-white">＋ Terima Service</a>@endif
        </div>
    </div>
    @if(session('status'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <div class="mb-4 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $currentProgress }}%"></div></div>
    <div class="mb-5 grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9">@foreach($statusProgress as $step)<div class="rounded-lg border px-2 py-2 text-center {{ $service->status === $step['status'] ? 'border-blue-300 bg-blue-50 text-blue-800' : 'border-slate-100 bg-white text-slate-500' }}"><span class="block text-[10px] font-semibold">{{ $step['label'] }}</span></div>@endforeach</div>

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,.75fr)]">
        <div class="space-y-4">
            <section id="informasi" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Informasi Service</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2"><div><p class="text-[11px] text-slate-500">Pelanggan</p><p class="mt-1 font-semibold text-slate-900">{{ $service->pelanggan?->nama ?? '—' }}</p><p class="text-xs text-slate-500">{{ $service->pelanggan?->telepon ?? 'Kontak tidak tersedia' }}</p></div><div><p class="text-[11px] text-slate-500">Teknisi</p><p class="mt-1 font-semibold text-slate-900">{{ $service->teknisi?->name ?? 'Belum ditugaskan' }}</p></div><div><p class="text-[11px] text-slate-500">Perangkat</p><p class="mt-1 font-semibold text-slate-900">{{ trim(($service->jenis_perangkat ?? '').' · '.($service->merek ?? '').' '.($service->model ?? '')) }}</p></div><div><p class="text-[11px] text-slate-500">Serial Number</p><p class="mt-1 font-semibold text-slate-900">{{ $service->serial_number ?: '—' }}</p></div><div class="sm:col-span-2"><p class="text-[11px] text-slate-500">Keluhan</p><p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $service->keluhan }}</p></div><div class="sm:col-span-2"><p class="text-[11px] text-slate-500">Diagnosa teknisi</p><p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $service->diagnosa ?: 'Belum dicatat.' }}</p></div>@if($service->keterangan)<div class="sm:col-span-2"><p class="text-[11px] text-slate-500">Keterangan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $service->keterangan }}</p></div>@endif</div>
            </section>

            @if($canUpdateProgress)
                <section class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Update Progress & Diagnosa</h2><form method="POST" action="{{ route('service.progress.store', $service) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                    <label class="text-xs font-medium text-slate-600">Status<select name="status" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@foreach($statusProgress as $step)@if(in_array($step['status'], $availableStatuses, true))<option value="{{ $step['status'] }}" @selected($service->status === $step['status'])>{{ $step['label'] }}</option>@endif @endforeach</select></label>
                    <label class="text-xs font-medium text-slate-600">Progress (%)<input type="number" name="progress_percent" min="0" max="100" value="{{ old('progress_percent', $currentProgress) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                    <label class="text-xs font-medium text-slate-600 sm:col-span-2">Diagnosa<textarea name="diagnosa" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="Hasil pemeriksaan teknisi">{{ old('diagnosa', $service->diagnosa) }}</textarea></label>
                    <label class="text-xs font-medium text-slate-600 sm:col-span-2">Catatan progress <span class="text-rose-600">*</span><textarea name="catatan" required rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="Tuliskan aktivitas yang dilakukan">{{ old('catatan') }}</textarea></label>
                    <div class="sm:col-span-2"><button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Simpan Progress</button></div>
                </form></section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="font-bold text-slate-900">Timeline Service</h2><span class="text-xs text-slate-500">{{ $progressEntries->count() }} pembaruan</span></div>
                @forelse($progressEntries as $entry)<article class="relative ml-2 mt-4 border-l border-slate-200 pb-4 pl-5 last:border-transparent"><span class="absolute -left-[5px] top-1 h-2.5 w-2.5 rounded-full bg-blue-600 ring-4 ring-blue-50"></span><div class="flex flex-wrap justify-between gap-2"><p class="text-sm font-semibold text-slate-900">{{ $statusLabels->get($entry->status)['label'] ?? str_replace('_', ' ', $entry->status) }} <span class="ml-1 text-xs font-normal text-blue-700">{{ $entry->progress_percent }}%</span></p><time class="text-[11px] text-slate-400">{{ $entry->created_at?->format('d M Y H:i') }}</time></div><p class="mt-1 text-sm text-slate-700">{{ $entry->catatan ?: 'Tidak ada catatan.' }}</p><p class="mt-1 text-[11px] text-slate-400">{{ $entry->updater?->name ?? 'Sistem' }}</p></article>@empty<p class="mt-4 text-sm text-slate-500">Belum ada aktivitas.</p>@endforelse
            </section>

            @if($canRequestApproval)
                <section class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Estimasi, Jasa & Sparepart</h2><p class="mt-1 text-xs text-slate-500">Total dihitung di server. Sparepart baru menurunkan stok setelah disetujui dan tersedia.</p>
                    <form method="POST" action="{{ route('service.approvals.store', $service) }}" class="mt-4 space-y-4" id="estimate-form">@csrf
                        <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs font-medium text-slate-600">Biaya jasa (Rp)<input name="jasa" type="number" min="0" step="0.01" required value="{{ old('jasa', $service->jasa) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-medium text-slate-600">Biaya tambahan (Rp)<input name="biaya_tambahan" type="number" min="0" step="0.01" value="{{ old('biaya_tambahan', $service->biaya_tambahan) }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="text-xs font-medium text-slate-600">Jenis diskon<select name="diskon_tipe" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="NOMINAL">Nominal (Rp)</option><option value="PERSEN">Persentase (%)</option></select></label><label class="text-xs font-medium text-slate-600">Nilai diskon<input name="diskon_nilai" type="number" min="0" step="0.01" value="0" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-600"><input type="checkbox" name="pajak_aktif" value="1" class="rounded border-slate-300"> Terapkan pajak</label><label class="text-xs font-medium text-slate-600">Pajak (%)<input name="pajak_persen" type="number" min="0" max="100" step="0.01" value="11" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label></div>
                        <div><div class="mb-2 flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-800">Sparepart yang diusulkan</h3><button type="button" id="add-part" class="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700">＋ Tambah Sparepart</button></div><div id="part-lines" class="space-y-2">@foreach(old('parts', [['produk_id' => '', 'qty' => 1]]) as $i => $part)<div class="part-line grid gap-2 sm:grid-cols-[minmax(0,1fr)_100px_36px]"><select name="parts[{{ $i }}][produk_id]" class="rounded-lg border-slate-300 text-sm"><option value="">Pilih produk sparepart</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($part['produk_id'] ?? '') == $product->id)>{{ $product->kode_produk }} · {{ $product->nama_produk }} (stok {{ $product->stok }})</option>@endforeach</select><input type="number" name="parts[{{ $i }}][qty]" min="1" max="1000" value="{{ $part['qty'] ?? 1 }}" class="rounded-lg border-slate-300 text-sm" aria-label="Jumlah"><button type="button" class="remove-part rounded-lg border border-rose-200 text-rose-600" aria-label="Hapus sparepart">×</button></div>@endforeach</div></div>
                        <label class="block text-xs font-medium text-slate-600">Diagnosa / catatan pengajuan<textarea name="catatan" required rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="Jelaskan temuan, biaya, dan alasan penggantian sparepart">{{ old('catatan', $service->diagnosa) }}</textarea></label>
                        <button class="rounded-lg bg-violet-700 px-4 py-2.5 text-sm font-semibold text-white">Kirim Estimasi untuk Persetujuan</button>
                    </form>
                </section>
            @endif

            @if($pendingApproval)
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Menunggu Persetujuan</p><h2 class="mt-1 text-lg font-bold text-slate-900">Estimasi {{ $money($pendingApproval->estimasi) }}</h2></div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Stok belum dipotong</span></div><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $pendingApproval->catatan }}</p><p class="mt-2 text-xs text-slate-500">Diajukan {{ $pendingApproval->requestedBy?->name ?? 'Petugas' }} · {{ $pendingApproval->created_at->format('d M Y H:i') }}</p>
                    @if($pendingApproval->details->isNotEmpty())<ul class="mt-3 space-y-1 text-sm text-slate-700">@foreach($pendingApproval->details as $detail)<li>{{ $detail->qty }} × {{ $detail->produk?->nama_produk }} @if($canViewBilling) — {{ $money($detail->subtotal) }}@endif</li>@endforeach</ul>@endif
                    @if($canDecideApproval)<form method="POST" action="{{ route('service.approvals.decision', [$service, $pendingApproval]) }}" class="mt-4 space-y-3">@csrf<label class="block text-xs font-medium text-slate-600">{{ auth()->user()->role?->name === 'PELANGGAN' ? 'Catatan keputusan (opsional)' : 'Bukti konfirmasi pelanggan / alasan keputusan (wajib)' }}<textarea name="catatan" maxlength="2000" rows="2" @required(auth()->user()->role?->name !== 'PELANGGAN') class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="{{ auth()->user()->role?->name === 'PELANGGAN' ? 'Catatan persetujuan atau alasan penolakan' : 'Contoh: pelanggan menyetujui melalui telepon setelah estimasi dijelaskan' }}"></textarea></label><div class="flex flex-wrap gap-2"><button name="decision" value="APPROVED" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Setujui Estimasi</button><button name="decision" value="REJECTED" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Tolak Estimasi</button></div></form>@endif
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Sparepart</h2></div>@if($service->details->isEmpty())<p class="px-5 py-6 text-sm text-slate-500">Belum ada sparepart pada service ini.</p>@else<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-5 py-3">Produk</th><th class="px-4 py-3 text-right">Qty</th>@if($canViewBilling)<th class="px-4 py-3 text-right">Harga</th><th class="px-5 py-3 text-right">Subtotal</th>@endif<th class="px-4 py-3">Status Stok</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($service->details as $detail)<tr><td class="px-5 py-3"><span class="font-medium text-slate-900">{{ $detail->produk?->nama_produk ?? 'Produk dihapus' }}</span><span class="block text-xs text-slate-500">{{ $detail->produk?->kode_produk }}</span></td><td class="px-4 py-3 text-right">{{ number_format((float) $detail->qty, 0, ',', '.') }}</td>@if($canViewBilling)<td class="px-4 py-3 text-right">{{ $money($detail->harga) }}</td><td class="px-5 py-3 text-right font-semibold">{{ $money($detail->subtotal) }}</td>@endif<td class="px-4 py-3 text-xs">@if($detail->approval?->status === 'PENDING')<span class="text-amber-700">Menunggu approval</span>@elseif($detail->stock_deducted_at)<span class="text-emerald-700">Stok dipakai</span>@else<span class="text-blue-700">Disetujui · menunggu stok</span>@endif</td></tr>@endforeach</tbody></table></div>@endif</section>
        </div>

        <aside class="space-y-4">
            @if($service->status === 'SELESAI' && $balance <= 0 && (auth()->user()->role?->name === 'KASIR' || auth()->user()->hasPermission('edit_service')))
                <section class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-900">Serah-terima perangkat</h2>
                    <p class="mt-1 text-xs text-slate-500">Pastikan tagihan lunas dan catat nama orang yang menerima perangkat.</p>
                    <form method="POST" action="{{ route('service.handover.store', $service) }}" class="mt-4 space-y-3">@csrf
                        <label class="block text-xs font-medium text-slate-600">Nama penerima <span class="text-rose-600">*</span><input name="nama_penerima" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                        <label class="block text-xs font-medium text-slate-600">Catatan<input name="catatan" maxlength="1000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                        <button class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Catat serah-terima</button>
                    </form>
                </section>
            @endif
            @if($canViewBilling)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Ringkasan Biaya</h2><div class="mt-4 space-y-2 text-sm"><div class="flex justify-between text-slate-600"><span>Jasa + sparepart</span><span>{{ $money($subtotal) }}</span></div><div class="flex justify-between text-slate-600"><span>Diskon</span><span>− {{ $money($service->diskon) }}</span></div><div class="flex justify-between text-slate-600"><span>Pajak ({{ number_format((float) $service->pajak_persen, 2, ',', '.') }}%)</span><span>{{ $money($service->pajak) }}</span></div><div class="flex justify-between text-slate-600"><span>Biaya tambahan</span><span>{{ $money($service->biaya_tambahan) }}</span></div><div class="flex justify-between border-t border-slate-100 pt-3 font-bold text-blue-800"><span>Total</span><span>{{ $money($service->total) }}</span></div><div class="flex justify-between text-emerald-700"><span>Dibayar</span><span>{{ $money($service->dibayar) }}</span></div><div class="flex justify-between font-semibold text-amber-800"><span>Sisa tagihan</span><span>{{ $money($balance) }}</span></div></div></section>
                @if($canDecideApproval && auth()->user()->role?->name === 'PELANGGAN' && $service->approvals->where('status', 'APPROVED')->isNotEmpty())<p class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">Persetujuan Anda telah tercatat. Hubungi petugas untuk informasi pembayaran atau pengambilan perangkat.</p>@endif
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Riwayat Pembayaran</h2></div>@forelse($payments as $payment)<div class="flex items-center justify-between gap-2 border-b border-slate-50 px-5 py-3 last:border-0"><div><p class="text-sm font-medium text-slate-900">{{ $payment->metode === 'PIUTANG' ? 'Sisa piutang' : $payment->metode }}</p><p class="text-[11px] text-slate-500">{{ $payment->created_at?->format('d M Y H:i') ?? $payment->tanggal?->format('d M Y') }} · {{ $payment->status }}</p></div><span class="text-sm font-semibold">{{ $money($payment->nominal) }}</span></div>@empty<p class="px-5 py-6 text-sm text-slate-500">Belum ada pembayaran.</p>@endforelse</section>
                @if(auth()->user()->hasPermission('create_service') && $balance > 0 && $service->status !== 'BATAL')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Pembayaran Service</h2>@if($service->approvals->where('status', 'PENDING')->isNotEmpty())<p class="mt-2 text-xs text-amber-700">Pembayaran dapat dicatat setelah estimasi disetujui.</p>@elseif($cashierNeedsShift)<a href="{{ route('cash.shifts.index') }}" class="mt-3 inline-flex rounded-lg bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-900">Buka shift kasir</a>@else<form method="POST" action="{{ route('service.payments.store', $service) }}" class="mt-4 space-y-3" id="payments-form">@csrf<div id="payment-lines"><div class="payment-line grid gap-2 sm:grid-cols-[1fr_1fr_32px]"><select name="payments[0][metode]" required class="rounded-lg border-slate-300 text-sm"><option value="CASH">Tunai</option><option value="TRANSFER">Transfer</option><option value="QRIS">QRIS</option><option value="DEBIT">Debit</option><option value="KREDIT">Kredit</option></select><input name="payments[0][nominal]" type="number" min="0.01" max="{{ $balance }}" step="0.01" required placeholder="Nominal" class="rounded-lg border-slate-300 text-sm"><button type="button" class="remove-payment rounded-lg border border-rose-200 text-rose-600" aria-label="Hapus pembayaran">×</button><input name="payments[0][keterangan]" placeholder="Keterangan opsional" class="col-span-full rounded-lg border-slate-300 text-xs"></div></div><div class="mt-2 flex items-center justify-between"><button type="button" id="add-payment" class="text-xs font-semibold text-blue-700">＋ Split payment</button><span class="text-xs text-slate-500">Maks {{ $money($balance) }}</span></div><button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Simpan Pembayaran</button></form>@endif</section>
                @endif
            @else
                <section class="rounded-2xl border border-blue-100 bg-blue-50 p-5"><h2 class="font-bold text-blue-950">Akses Teknisi</h2><p class="mt-2 text-sm text-blue-900">Halaman ini hanya menampilkan informasi perangkat, diagnosis, sparepart, dan progress pekerjaan. Rincian HPP, tagihan, pembayaran, kas, dan laba perusahaan tidak ditampilkan.</p></section>
            @endif
        </aside>
    </div>

    @if($canRequestApproval || (auth()->user()->hasPermission('create_service') && $canViewBilling))<script>
        (() => {
            const partLines = document.getElementById('part-lines');
            const addPart = document.getElementById('add-part');
            if (partLines && addPart) {
                let partIndex = partLines.querySelectorAll('.part-line').length;
                addPart.addEventListener('click', () => {
                    const row = partLines.querySelector('.part-line').cloneNode(true);
                    row.querySelector('select').name = `parts[${partIndex}][produk_id]`;
                    row.querySelector('select').value = '';
                    row.querySelector('input').name = `parts[${partIndex}][qty]`;
                    row.querySelector('input').value = 1;
                    partLines.append(row);
                    partIndex++;
                });
                partLines.addEventListener('click', e => { if (e.target.closest('.remove-part') && partLines.querySelectorAll('.part-line').length > 1) e.target.closest('.part-line').remove(); });
            }
            const paymentLines = document.getElementById('payment-lines');
            const addPayment = document.getElementById('add-payment');
            if (paymentLines && addPayment) {
                let paymentIndex = 1;
                addPayment.addEventListener('click', () => {
                    const row = paymentLines.querySelector('.payment-line').cloneNode(true);
                    row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/payments\[\d+\]/, `payments[${paymentIndex}]`); if (input.tagName === 'INPUT') input.value = ''; });
                    paymentLines.append(row);
                    paymentIndex++;
                });
                paymentLines.addEventListener('click', e => { if (e.target.closest('.remove-payment') && paymentLines.querySelectorAll('.payment-line').length > 1) e.target.closest('.payment-line').remove(); });
            }
        })();
    </script>@endif
</x-dashboard-layout>
