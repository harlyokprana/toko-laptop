<x-dashboard-layout :title="$product->exists ? 'Edit Produk' : 'Tambah Produk'">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('products.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Kembali ke produk</a>
        <div class="mb-6 mt-4"><h2 class="text-2xl font-bold tracking-tight text-slate-950">{{ $product->exists ? 'Edit Produk' : 'Tambah Produk' }}</h2><p class="mt-1 text-sm text-slate-500">Pilih jenis produk, lalu isi field yang sesuai. SKU dibuat otomatis oleh sistem.</p></div>
        @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8" x-data="{ jenis: '{{ old('jenis_produk', $product->jenis_produk ?? '') }}' }">
            @csrf
            @if ($product->exists) @method('PUT') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">Jenis Produk<span class="text-rose-600"> *</span>
                    <select name="jenis_produk" required @change="jenis = $event.target.value" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Pilih jenis produk</option>
                        <option value="LAPTOP" @selected(old('jenis_produk', $product->jenis_produk) === 'LAPTOP')>Laptop</option>
                        <option value="AKSESORIS" @selected(old('jenis_produk', $product->jenis_produk) === 'AKSESORIS')>Aksesori</option>
                        <option value="SPAREPART" @selected(old('jenis_produk', $product->jenis_produk) === 'SPAREPART')>Sparepart</option>
                        <option value="PC_RAKITAN" @selected(old('jenis_produk', $product->jenis_produk) === 'PC_RAKITAN')>PC Rakitan</option>
                    </select>
                    <x-input-error :messages="$errors->get('jenis_produk')" class="mt-1" />
                </label>
                <label class="block text-sm font-medium text-slate-700">SKU
                    <input name="kode_produk" value="{{ old('kode_produk', $product->kode_produk) }}" readonly class="mt-1.5 block w-full rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-600" placeholder="Otomatis oleh sistem">
                    <span class="mt-1 block text-xs text-slate-400">SKU dibuat otomatis dan tidak dapat diedit.</span>
                </label>
<label class="block text-sm font-medium text-slate-700">Nama Produk / Nama Barang<span class="text-rose-600"> *</span>
                    <input name="nama_produk" value="{{ old('nama_produk', $product->nama_produk) }}" maxlength="255" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <x-input-error :messages="$errors->get('nama_produk')" class="mt-1" />
                </label>
            </div>
            <template x-if="jenis === 'LAPTOP'">
                <div class="grid gap-5 sm:grid-cols-2 border-t border-slate-100 pt-5">
                    <label class="block text-sm font-medium text-slate-700">Merek / Brand<span class="text-rose-600"> *</span>
                        <input name="merek" list="brand-list" value="{{ old('merek', $product->merek) }}" placeholder="Pilih atau ketik merek baru" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Series / Model<span class="text-rose-600"> *</span>
                        <input name="tipe" value="{{ old('tipe', $product->tipe) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Processor
                        <input name="atribut[processor]" list="processor-list" value="{{ old('atribut.processor', data_get($product->atribut, 'processor')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">RAM
                        <select name="atribut[ram]" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih RAM</option>
                            @foreach (['4 GB','8 GB','16 GB','32 GB','64 GB'] as $ram)
                                <option value="{{ $ram }}" @selected(old('atribut.ram', data_get($product->atribut, 'ram')) === $ram)>{{ $ram }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Storage
                        <input name="atribut[storage]" list="storage-list" value="{{ old('atribut.storage', data_get($product->atribut, 'storage')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Ukuran Layar
                        <input name="atribut[ukuran_layar]" list="screen-list" value="{{ old('atribut.ukuran_layar', data_get($product->atribut, 'ukuran_layar')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Kondisi
                        <select name="atribut[kondisi]" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih kondisi</option>
                            <option value="Baru" @selected(old('atribut.kondisi', data_get($product->atribut, 'kondisi')) === 'Baru')>Baru</option>
                            <option value="Bekas" @selected(old('atribut.kondisi', data_get($product->atribut, 'kondisi')) === 'Bekas')>Bekas</option>
                        </select>
                    </label>
                </div>
            </template>
            <template x-if="jenis === 'AKSESORIS'">
                <div class="grid gap-5 sm:grid-cols-2 border-t border-slate-100 pt-5">
                    <label class="block text-sm font-medium text-slate-700">Jenis Aksesori<span class="text-rose-600"> *</span>
                        <select name="atribut[jenis_aksesori]" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih jenis aksesori</option>
                            @foreach (['Mouse','Keyboard','Headset','Webcam','Charger','Kabel','USB Hub','Cooling Pad','Flashdisk','Mousepad','Speaker','Adaptor','Card Reader','Converter','Docking Station','Power Bank','Gamepad / Controller','Laptop Stand','Cleaning Kit','HDMI Adapter','Lainnya'] as $acc)
                                <option value="{{ $acc }}" @selected(old('atribut.jenis_aksesori', data_get($product->atribut, 'jenis_aksesori')) === $acc)>{{ $acc }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('atribut.jenis_aksesori')" class="mt-1" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Jenis Aksesori Custom (jika Lainnya)
                        <input name="atribut[jenis_aksesori_custom]" value="{{ old('atribut.jenis_aksesori_custom') }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Spesifikasi Singkat
                        <textarea name="atribut[spesifikasi_singkat]" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('atribut.spesifikasi_singkat', data_get($product->atribut, 'spesifikasi_singkat')) }}</textarea>
                    </label>
                </div>
            </template>
            <template x-if="jenis === 'SPAREPART'">
                <div class="grid gap-5 sm:grid-cols-2 border-t border-slate-100 pt-5">
                    <label class="block text-sm font-medium text-slate-700">Merek / Brand<span class="text-rose-600"> *</span>
                        <input name="merek" list="brand-list" value="{{ old('merek', $product->merek) }}" placeholder="Pilih atau ketik merek baru" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Series / Model<span class="text-rose-600"> *</span>
                        <input name="tipe" value="{{ old('tipe', $product->tipe) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Spesifikasi Singkat
                        <textarea name="atribut[spesifikasi_singkat]" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('atribut.spesifikasi_singkat', data_get($product->atribut, 'spesifikasi_singkat')) }}</textarea>
                    </label>
                </div>
            </template>
            <template x-if="jenis === 'PC_RAKITAN'">
                <div class="grid gap-5 sm:grid-cols-2 border-t border-slate-100 pt-5">
                    <label class="block text-sm font-medium text-slate-700">Merek / Brand<span class="text-rose-600"> *</span>
                        <input name="merek" list="brand-list" value="{{ old('merek', $product->merek) }}" placeholder="Pilih atau ketik merek baru" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Motherboard<span class="text-rose-600"> *</span>
                        <input name="atribut[motherboard]" list="motherboard-list" value="{{ old('atribut.motherboard', data_get($product->atribut, 'motherboard')) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Processor
                        <input name="atribut[processor]" list="processor-list" value="{{ old('atribut.processor', data_get($product->atribut, 'processor')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">RAM
                        <select name="atribut[ram]" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih RAM</option>
                            @foreach (['4 GB','8 GB','16 GB','32 GB','64 GB'] as $ram)
                                <option value="{{ $ram }}" @selected(old('atribut.ram', data_get($product->atribut, 'ram')) === $ram)>{{ $ram }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Storage
                        <input name="atribut[storage]" list="storage-list" value="{{ old('atribut.storage', data_get($product->atribut, 'storage')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Ukuran LCD
                        <input name="atribut[ukuran_lcd]" list="screen-list" value="{{ old('atribut.ukuran_lcd', data_get($product->atribut, 'ukuran_lcd')) }}" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Kondisi
                        <select name="atribut[kondisi]" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Pilih kondisi</option>
                            <option value="Baru" @selected(old('atribut.kondisi', data_get($product->atribut, 'kondisi')) === 'Baru')>Baru</option>
                            <option value="Bekas" @selected(old('atribut.kondisi', data_get($product->atribut, 'kondisi')) === 'Bekas')>Bekas</option>
                        </select>
                    </label>
                </div>
            </template>

            <div class="grid gap-5 sm:grid-cols-2 border-t border-slate-100 pt-5">
                <input type="hidden" name="satuan" value="{{ old('satuan', $product->satuan ?: 'PCS') }}">
                <label class="block text-sm font-medium text-slate-700">Harga Jual<span class="text-rose-600"> *</span>
                    <input type="number" name="harga_jual" value="{{ old('harga_jual', $product->harga_jual ?? '0.00') }}" min="0" step="0.01" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <x-input-error :messages="$errors->get('harga_jual')" class="mt-1" />
                </label>
                <input type="hidden" name="stok_minimum" value="{{ (int) old('stok_minimum', $product->stok_minimum ?? 0) }}">
                <label class="block text-sm font-medium text-slate-700">Status<span class="text-rose-600"> *</span>
                    <select name="status" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="AKTIF" @selected(old('status', $product->status ?? 'AKTIF') === 'AKTIF')>Aktif</option>
                        <option value="NONAKTIF" @selected(old('status', $product->status) === 'NONAKTIF')>Nonaktif</option>
                    </select>
                </label>
            </div>

            <datalist id="brand-list">
                @foreach ($brands as $brand)
                    <option value="{{ $brand }}">
                @endforeach
            </datalist>
            <datalist id="processor-list">
                <option value="Intel Core i3"><option value="Intel Core i5"><option value="Intel Core i7"><option value="Intel Core i9"><option value="AMD Ryzen 3"><option value="AMD Ryzen 5"><option value="AMD Ryzen 7"><option value="AMD Ryzen 9">
            </datalist>
            <datalist id="storage-list">
                <option value="128 GB SSD"><option value="256 GB SSD"><option value="512 GB SSD"><option value="1 TB SSD"><option value="1 TB HDD">
            </datalist>
            <datalist id="screen-list">
                <option value="13.3 inch"><option value="14 inch"><option value="15.6 inch"><option value="16 inch"><option value="17.3 inch"><option value="24 inch">
            </datalist>
            <datalist id="motherboard-list">
                <option value="ASRock"><option value="Asus"><option value="Gigabyte"><option value="MSI">
            </datalist>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <a href="{{ route('products.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Batal</a>
                <button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Simpan Produk</button>
            </div>
        </form>
    </div>
</x-dashboard-layout>