<x-dashboard-layout :title="$customer->exists ? 'Edit Pelanggan' : 'Tambah Pelanggan'">
    <div class="mx-auto max-w-3xl"><a href="{{ route('customers.index') }}" class="text-sm font-semibold text-blue-700">← Kembali ke pelanggan</a><h2 class="mb-6 mt-4 text-2xl font-bold text-slate-950">{{ $customer->exists ? 'Edit Pelanggan' : 'Tambah Pelanggan' }}</h2>
        @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">@csrf @if($customer->exists)@method('PUT')@endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Kode Pelanggan<input name="kode_pelanggan" value="{{ old('kode_pelanggan', $customer->kode_pelanggan) }}" maxlength="30" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Nama Lengkap<input name="nama" value="{{ old('nama', $customer->nama) }}" maxlength="255" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Email<input type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Telepon<input name="telepon" value="{{ old('telepon', $customer->telepon) }}" maxlength="30" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Alamat<textarea name="alamat" rows="3" maxlength="5000" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">{{ old('alamat', $customer->alamat) }}</textarea></label>
                <label class="text-sm font-medium text-slate-700">Status<select name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"><option value="AKTIF" @selected(old('status', $customer->status ?? 'AKTIF') === 'AKTIF')>Aktif</option><option value="NONAKTIF" @selected(old('status', $customer->status) === 'NONAKTIF')>Nonaktif</option></select></label>
            </div>
            <div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5"><a href="{{ route('customers.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600">Batal</a><button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Simpan Pelanggan</button></div>
        </form>
    </div>
</x-dashboard-layout>
