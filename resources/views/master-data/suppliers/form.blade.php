<x-dashboard-layout :title="$supplier->exists ? 'Edit Supplier' : 'Tambah Supplier'">
    <div class="mx-auto max-w-3xl"><a href="{{ route('suppliers.index') }}" class="text-sm font-semibold text-blue-700">← Kembali ke supplier</a><h2 class="mb-6 mt-4 text-2xl font-bold text-slate-950">{{ $supplier->exists ? 'Edit Supplier' : 'Tambah Supplier' }}</h2>
        @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">@csrf @if($supplier->exists)@method('PUT')@endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Kode Supplier<input name="kode_supplier" value="{{ old('kode_supplier', $supplier->kode_supplier) }}" maxlength="3" required @readonly(!$supplier->exists) class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm read-only:bg-slate-50 read-only:text-slate-500">@unless($supplier->exists)<span class="mt-1 block text-xs font-normal text-slate-500">Kode singkat dibuat otomatis. Maksimal 50 supplier.</span>@endunless</label>
                <label class="text-sm font-medium text-slate-700">Nama Supplier<input name="nama" value="{{ old('nama', $supplier->nama) }}" maxlength="255" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Nama Kontak<input name="kontak" value="{{ old('kontak', $supplier->kontak) }}" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Telepon<input name="telepon" value="{{ old('telepon', $supplier->telepon) }}" maxlength="30" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Email<input type="email" name="email" value="{{ old('email', $supplier->email) }}" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Status<select name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"><option value="AKTIF" @selected(old('status', $supplier->status ?? 'AKTIF') === 'AKTIF')>Aktif</option><option value="NONAKTIF" @selected(old('status', $supplier->status) === 'NONAKTIF')>Nonaktif</option></select></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Alamat<textarea name="alamat" rows="3" maxlength="5000" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">{{ old('alamat', $supplier->alamat) }}</textarea></label>
            </div>
            <div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5"><a href="{{ route('suppliers.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600">Batal</a><button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Simpan Supplier</button></div>
        </form>
    </div>
</x-dashboard-layout>
