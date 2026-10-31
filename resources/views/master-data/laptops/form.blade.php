<x-dashboard-layout :title="$laptop->exists ? 'Edit Laptop' : 'Tambah Laptop'">
    <div class="mx-auto max-w-4xl"><a href="{{ route('laptops.index') }}" class="text-sm font-semibold text-blue-700">← Kembali ke master laptop</a><h2 class="mb-2 mt-4 text-2xl font-bold text-slate-950">{{ $laptop->exists ? 'Edit Laptop' : 'Tambah Laptop' }}</h2><p class="mb-6 text-sm text-slate-500">Setiap unit dicatat dengan kode dan serial number unik.</p>
        @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $laptop->exists ? route('laptops.update', $laptop) : route('laptops.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">@csrf @if($laptop->exists)@method('PUT')@endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">SKU<input name="sku" value="{{ old('sku', $laptop->sku) }}" maxlength="50" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Serial Number<input name="serial_number" value="{{ old('serial_number', $laptop->serial_number) }}" maxlength="100" required class="mt-1.5 block w-full rounded-lg border-slate-300 font-mono text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Produk
                    <select name="produk_id" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Pilih produk laptop (opsional)</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(old('produk_id', $laptop->produk_id) == $product->id)>{{ $product->kode_produk }} — {{ $product->nama_produk }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Supplier
                    <input type="search" placeholder="Cari supplier..." class="supplier-search mt-1.5 block w-full rounded-lg border-slate-300 text-sm" autocomplete="off">
                    <select name="supplier_id" class="supplier-select mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Pilih supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $laptop->supplier_id) == $supplier->id)>{{ $supplier->nama }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Merek<input name="merek" value="{{ old('merek', $laptop->merek) }}" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Model / Seri<input name="model_seri" value="{{ old('model_seri', $laptop->model_seri) }}" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Tanggal Perolehan<input type="date" name="tanggal_perolehan" value="{{ old('tanggal_perolehan', $laptop->tanggal_perolehan?->format('Y-m-d')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Status<select name="status" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">@foreach(['READY','REPAIR','REJECT','DISPLAY','DIPAKAI_INTERNAL'] as $status)<option value="{{ $status }}" @selected(old('status', $laptop->status ?? 'READY') === $status)>{{ str_replace('_', ' ', $status) }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-slate-700">Kondisi *
                    <select name="kondisi" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        @foreach(['Baru','Seken'] as $k)<option value="{{ $k }}" @selected(old('kondisi', $laptop->kondisi ?? 'Baru') === $k)>{{ $k }}</option>@endforeach
                    </select>
                </label>
                @php
                    $procOpts = ['Intel Core i3','Intel Core i5','Intel Core i7','Intel Core i9','AMD Ryzen 3','AMD Ryzen 5','AMD Ryzen 7','AMD Ryzen 9','Apple M1','Apple M2','Apple M3'];
                    $gpuOpts = ['Integrated / Intel UHD','Intel Iris Xe','NVIDIA GeForce MX','NVIDIA GeForce RTX','AMD Radeon','Apple GPU'];
                    $ramOpts = ['4 GB','8 GB','16 GB','32 GB','64 GB'];
                    $storageJenisOpts = ['HDD','SSD SATA','SSD NVMe','eMMC'];
                    $storageCapOpts = ['128 GB','256 GB','512 GB','1 TB'];
                    $screenOpts = ['13"','14"','15.6"','16"'];
                    $resOpts = ['1366×768','1920×1080','2560×1440','3840×2160'];
                    $inOpts = function($v,$opts){ return $v && !in_array($v,$opts,true); };
                @endphp
                @php $procVal = old('processor', $laptop->processor); $procCustom = $inOpts($procVal, $procOpts); @endphp
                <label class="text-sm font-medium text-slate-700">Processor
                    <select class="spec-select mt-1.5 block w-full rounded-lg border-slate-300 text-sm" data-target="processor">
                        <option value="">Pilih processor</option>
                        @foreach($procOpts as $o)<option value="{{ $o }}" @selected($procVal === $o)>{{ $o }}</option>@endforeach
                        <option value="__custom__" @selected($procCustom)>Lainnya (ketik sendiri)</option>
                    </select>
                    <input type="text" name="processor" value="{{ $procCustom ? $procVal : '' }}" maxlength="255" class="spec-custom mt-2 block w-full rounded-lg border-slate-300 text-sm {{ $procCustom ? '' : 'hidden' }}" placeholder="Ketik processor">
                </label>

                <label class="text-sm font-medium text-slate-700">RAM
                    <select name="ram" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Pilih RAM</option>
                        @foreach($ramOpts as $o)<option value="{{ $o }}" @selected(old('ram', $laptop->ram) === $o)>{{ $o }}</option>@endforeach
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">Storage — Jenis
                    <select name="storage_jenis" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Pilih jenis</option>
                        @foreach($storageJenisOpts as $o)<option value="{{ $o }}" @selected(old('storage_jenis', $laptop->storage_jenis) === $o)>{{ $o }}</option>@endforeach
                    </select>
                </label>

                @php $capVal = old('storage_kapasitas', $laptop->storage_kapasitas); $capCustom = $inOpts($capVal, $storageCapOpts); @endphp
                <label class="text-sm font-medium text-slate-700">Storage — Kapasitas
                    <select class="spec-select mt-1.5 block w-full rounded-lg border-slate-300 text-sm" data-target="storage_kapasitas">
                        <option value="">Pilih kapasitas</option>
                        @foreach($storageCapOpts as $o)<option value="{{ $o }}" @selected($capVal === $o)>{{ $o }}</option>@endforeach
                        <option value="__custom__" @selected($capCustom)>Lainnya (ketik sendiri)</option>
                    </select>
                    <input type="text" name="storage_kapasitas" value="{{ $capCustom ? $capVal : '' }}" maxlength="30" class="spec-custom mt-2 block w-full rounded-lg border-slate-300 text-sm {{ $capCustom ? '' : 'hidden' }}" placeholder="contoh: 2 TB">
                </label>


                @php $gpuVal = old('gpu', $laptop->gpu); $gpuCustom = $inOpts($gpuVal, $gpuOpts); @endphp
                <label class="text-sm font-medium text-slate-700">GPU
                    <select class="spec-select mt-1.5 block w-full rounded-lg border-slate-300 text-sm" data-target="gpu">
                        <option value="">Pilih GPU</option>
                        @foreach($gpuOpts as $o)<option value="{{ $o }}" @selected($gpuVal === $o)>{{ $o }}</option>@endforeach
                        <option value="__custom__" @selected($gpuCustom)>Lainnya (ketik sendiri)</option>
                    </select>
                    <input type="text" name="gpu" value="{{ $gpuCustom ? $gpuVal : '' }}" maxlength="255" class="spec-custom mt-2 block w-full rounded-lg border-slate-300 text-sm {{ $gpuCustom ? '' : 'hidden' }}" placeholder="Ketik GPU">
                </label>

                @php $screenVal = old('ukuran_layar', $laptop->ukuran_layar); $screenCustom = $inOpts($screenVal, $screenOpts); @endphp
                <label class="text-sm font-medium text-slate-700">Ukuran Layar
                    <select class="spec-select mt-1.5 block w-full rounded-lg border-slate-300 text-sm" data-target="ukuran_layar">
                        <option value="">Pilih ukuran</option>
                        @foreach($screenOpts as $o)<option value="{{ $o }}" @selected($screenVal === $o)>{{ $o }}</option>@endforeach
                        <option value="__custom__" @selected($screenCustom)>Lainnya (ketik sendiri)</option>
                    </select>
                    <input type="text" name="ukuran_layar" value="{{ $screenCustom ? $screenVal : '' }}" maxlength="20" class="spec-custom mt-2 block w-full rounded-lg border-slate-300 text-sm {{ $screenCustom ? '' : 'hidden' }}" placeholder='contoh: 17.3"'>
                </label>

                @php $resVal = old('resolusi_layar', $laptop->resolusi_layar); $resCustom = $inOpts($resVal, $resOpts); @endphp
                <label class="text-sm font-medium text-slate-700">Resolusi Layar
                    <select class="spec-select mt-1.5 block w-full rounded-lg border-slate-300 text-sm" data-target="resolusi_layar">
                        <option value="">Pilih resolusi</option>
                        @foreach($resOpts as $o)<option value="{{ $o }}" @selected($resVal === $o)>{{ $o }}</option>@endforeach
                        <option value="__custom__" @selected($resCustom)>Lainnya (ketik sendiri)</option>
                    </select>
                    <input type="text" name="resolusi_layar" value="{{ $resCustom ? $resVal : '' }}" maxlength="30" class="spec-custom mt-2 block w-full rounded-lg border-slate-300 text-sm {{ $resCustom ? '' : 'hidden' }}" placeholder="contoh: 2880×1800">
                </label>


                <label class="text-sm font-medium text-slate-700">Harga Jual *
                    <input type="text" inputmode="numeric" name="harga_jual" value="{{ old('harga_jual', $laptop->harga_jual ?? '') }}" data-money="true" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                @if(auth()->user()->hasPermission('view_profit_loss'))
                    <label class="text-sm font-medium text-slate-700">HPP
                        <input type="text" inputmode="numeric" name="hpp" value="{{ old('hpp', $laptop->hpp ?? 0) }}" data-money="true" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Biaya Tambahan
                        <input type="text" inputmode="numeric" name="biaya_tambahan" value="{{ old('biaya_tambahan', $laptop->biaya_tambahan ?? 0) }}" data-money="true" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    </label>
                @endif
            </div>
            <div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5"><a href="{{ route('laptops.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600">Batal</a><button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Simpan Laptop</button></div>
        </form>
    </div>
    <script>
        document.querySelectorAll('.spec-select').forEach(function (sel) {
            var target = sel.dataset.target;
            var input = document.querySelector('input[name="' + target + '"]');
            if (!input) return;
            sel.addEventListener('change', function () {
                if (sel.value === '__custom__') { input.classList.remove('hidden'); input.value = ''; input.focus(); }
                else { input.classList.add('hidden'); input.value = sel.value; }
            });
            if (sel.value && sel.value !== '__custom__') input.value = sel.value;
            if (sel.value === '__custom__') input.classList.remove('hidden');
        });
    </script>
    <script>
        document.querySelectorAll('.supplier-search').forEach(function (inp) {
            var sel = inp.parentElement.querySelector('.supplier-select');
            if (!sel) return;
            inp.addEventListener('input', function () {
                var q = inp.value.toLowerCase();
                sel.querySelectorAll('option').forEach(function (opt) {
                    if (opt.value === '') { opt.hidden = false; return; }
                    opt.hidden = opt.textContent.toLowerCase().indexOf(q) === -1;
                });
            });
        });
    </script>

</x-dashboard-layout>