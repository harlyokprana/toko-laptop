<x-dashboard-layout title="Terima Service">
    <div class="mb-5 flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-900 text-white"><x-nav-icon name="service" class="h-6 w-6"/></span><div><p class="text-xs text-slate-500">Service · Penerimaan</p><h1 class="text-xl font-bold text-slate-950">Terima Service</h1><p class="text-xs text-slate-500">Catat pelanggan dan kondisi perangkat. Nomor service dibuat otomatis.</p></div></div>
    @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    @if($isCashier && ! $openShift)<div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span>Buka shift kasir sebelum menerima service.</span><a href="{{ route('cash.shifts.index') }}" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white">Buka Shift</a></div>@endif
    @if($technicians->isEmpty())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Belum ada teknisi aktif. Aktifkan atau tambahkan teknisi sebelum menerima service agar pemeriksaan dapat ditugaskan.</div>@endif

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.75fr)]">
        <form method="POST" action="{{ route('service.store') }}" class="space-y-4">@csrf
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center gap-2"><x-nav-icon name="people" class="h-5 w-5 text-slate-800"/><h2 class="text-sm font-bold text-slate-900">Data Pelanggan</h2></div>
                <label class="block text-xs font-medium text-slate-600" for="pelanggan-id">Pelanggan <span class="text-rose-600">*</span><select id="pelanggan-id" name="pelanggan_id" required class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500"><option value="">Cari nama, kode, atau nomor telepon…</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('pelanggan_id') == $customer->id)>{{ $customer->nama }} · {{ $customer->kode_pelanggan }}{{ $customer->telepon ? ' · '.$customer->telepon : '' }}</option>@endforeach</select></label>
                <button type="button" id="service-add-customer" class="mt-3 rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">+ Tambah pelanggan baru</button>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center gap-2"><x-nav-icon name="purchase" class="h-5 w-5 text-slate-800"/><h2 class="text-sm font-bold text-slate-900">Data Perangkat</h2></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs font-medium text-slate-600">Jenis Perangkat <span class="text-rose-600">*</span><select name="jenis_perangkat" required class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm"><option value="Laptop" @selected(old('jenis_perangkat', 'Laptop') === 'Laptop')>Laptop</option><option value="Desktop" @selected(old('jenis_perangkat') === 'Desktop')>Desktop</option><option value="Lainnya" @selected(old('jenis_perangkat') === 'Lainnya')>Lainnya</option></select></label>
                    <label class="block text-xs font-medium text-slate-600">Merek <span class="text-rose-600">*</span><input name="merek" required maxlength="100" value="{{ old('merek') }}" placeholder="Contoh: ASUS" class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm"></label>
                    <label class="block text-xs font-medium text-slate-600">Model<input name="model" maxlength="150" value="{{ old('model') }}" placeholder="Contoh: VivoBook 14 A1404" class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm"></label>
                    <label class="block text-xs font-medium text-slate-600">Serial Number<input name="serial_number" maxlength="100" value="{{ old('serial_number') }}" placeholder="Nomor seri perangkat" class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm"></label>
                    <label class="block text-xs font-medium text-slate-600 sm:col-span-2">Keluhan Pelanggan <span class="text-rose-600">*</span><textarea name="keluhan" rows="4" required maxlength="5000" placeholder="Jelaskan masalah yang disampaikan pelanggan…" class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('keluhan') }}</textarea></label>
                    <label class="block text-xs font-medium text-slate-600">Teknisi <span class="text-rose-600">*</span><select name="teknisi_id" required class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm"><option value="">Pilih teknisi aktif</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(old('teknisi_id') == $technician->id)>{{ $technician->name }}</option>@endforeach</select></label>
                    <label class="block text-xs font-medium text-slate-600">Catatan Awal<input name="keterangan" maxlength="2000" value="{{ old('keterangan') }}" placeholder="Aksesori yang ikut diserahkan, kondisi fisik…" class="mt-1.5 block w-full rounded-lg border-slate-200 text-sm"></label>
                </div>
            </section>
            <div class="flex justify-end gap-2"><a href="{{ route('module.service') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600">Batal</a><button @disabled(($isCashier && ! $openShift) || $technicians->isEmpty()) class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300">Simpan & Buat Nomor Service</button></div>
        </form>

        <aside class="space-y-4 xl:sticky xl:top-20">
            <section class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5"><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Nomor Service</p><p class="mt-2 text-lg font-bold text-slate-900">Dibuat otomatis</p><p class="mt-1 text-xs leading-5 text-slate-500">Nomor service tampil setelah penerimaan berhasil disimpan.</p></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="mb-4 flex items-center gap-2"><x-nav-icon name="service" class="h-5 w-5 text-slate-800"/><h2 class="text-sm font-bold text-slate-900">Alur Service</h2></div><ol class="space-y-4">@foreach([['Diterima','Perangkat dan keluhan dicatat.'],['Diagnosa','Teknisi memeriksa dan mencatat penyebab.'],['Persetujuan','Estimasi dan sparepart menunggu persetujuan pelanggan.'],['Perbaikan','Sparepart disiapkan dan pengerjaan dilakukan.'],['Testing','Hasil perbaikan diuji.'],['Selesai','Pelanggan menerima pemberitahuan dan tagihan.'],['Diambil','Pembayaran lunas dan perangkat diserahkan.']] as $index => [$label, $detail])<li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full {{ $index === 0 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500' }} text-[10px] font-bold">{{ $index + 1 }}</span><span><strong class="block text-xs text-slate-800">{{ $label }}</strong><span class="text-[11px] text-slate-500">{{ $detail }}</span></span></li>@endforeach</ol></section>
        </aside>
    </div>

    <div id="service-customer-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900">Tambah Pelanggan Baru</h2>
                <button type="button" data-service-customer-close class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Tutup">✕</button>
            </div>
            <div class="grid gap-3">
                <label class="text-sm font-medium text-slate-700">Nama Lengkap
                    <input id="service-customer-name" maxlength="255" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">Telepon <span class="text-rose-600">*</span>
                    <input id="service-customer-phone" maxlength="30" required autocomplete="tel" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    <span class="mt-1 block text-xs font-normal text-slate-500">Digunakan sebagai username dan kata sandi awal pelanggan.</span>
                </label>
                <label class="text-sm font-medium text-slate-700">Email (opsional)
                    <input id="service-customer-email" type="email" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
            </div>
            <p id="service-customer-error" role="alert" class="mt-3 hidden text-sm text-rose-700"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-service-customer-close class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600">Batal</button>
                <button type="button" id="service-customer-save" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Simpan</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('service-customer-modal');
            const customerSelect = document.getElementById('pelanggan-id');
            const error = document.getElementById('service-customer-error');
            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.getElementById('service-add-customer').addEventListener('click', () => {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.getElementById('service-customer-name').focus();
                error.classList.add('hidden');
                error.textContent = '';
            });
            modal.querySelectorAll('[data-service-customer-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });
            document.getElementById('service-customer-save').addEventListener('click', async () => {
                const button = document.getElementById('service-customer-save');
                const nama = document.getElementById('service-customer-name').value.trim();
                const telepon = document.getElementById('service-customer-phone').value.trim();
                const email = document.getElementById('service-customer-email').value.trim();
                error.classList.add('hidden');
                error.textContent = '';

                if (!nama || !telepon) {
                    error.textContent = 'Nama dan nomor telepon wajib diisi.';
                    error.classList.remove('hidden');
                    return;
                }

                button.disabled = true;
                try {
                    const response = await fetch(@json(route('service.customers.quick-store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ nama, telepon, email }),
                    });
                    const responseText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch {
                        throw new Error(`Server mengembalikan respons tidak valid (HTTP ${response.status}).`);
                    }

                    if (!response.ok) {
                        const message = result.errors
                            ? Object.values(result.errors).flat()[0]
                            : (result.message || 'Pelanggan tidak dapat disimpan.');
                        throw new Error(message);
                    }

                    const option = new Option(
                        `${result.nama} · ${result.kode_pelanggan} · ${result.telepon}`,
                        result.id,
                        true,
                        true,
                    );
                    customerSelect.add(option);
                    customerSelect.value = String(result.id);
                    closeModal();
                    document.getElementById('service-customer-name').value = '';
                    document.getElementById('service-customer-phone').value = '';
                    document.getElementById('service-customer-email').value = '';
                } catch (requestError) {
                    error.textContent = requestError.message;
                    error.classList.remove('hidden');
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
</x-dashboard-layout>
