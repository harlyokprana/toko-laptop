<x-dashboard-layout :title="$isCreate ? 'Tambah Role' : 'Edit Role'">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('module.roles') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Kembali ke Role & Permission</a>
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-6">
                <p class="text-sm text-slate-500">Role adalah kelompok akses untuk pengguna aplikasi.</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">{{ $isCreate ? 'Tambah Role' : 'Edit Role' }}</h1>
            </div>
            @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

            <form method="POST" action="{{ $isCreate ? route('roles.store') : route('roles.update', $role) }}" class="space-y-5">
                @csrf
                @unless($isCreate)@method('PUT')@endunless
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Nama Role</label>
                    @if(! $isCreate && $role->isSystemRole())
                        <input type="hidden" name="name" value="{{ old('name', $role->name) }}">
                        <input id="name" type="text" value="{{ $role->display_name }}" disabled class="block w-full rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-500">
                        <p class="mt-1 text-xs text-slate-500">Nama role bawaan sistem tidak dapat diubah.</p>
                    @else
                        <input id="name" name="name" type="text" maxlength="50" required value="{{ old('name', $isCreate ? '' : $role->display_name) }}" placeholder="Contoh: Kasir Cabang" class="block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @endif
                    @error('name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Deskripsi</label>
                    <textarea id="description" name="description" rows="3" maxlength="255" placeholder="Jelaskan tanggung jawab role ini." class="block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $role->description) }}</textarea>
                    @error('description')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="is_active" class="mb-1.5 block text-sm font-semibold text-slate-700">Status</label>
                    @if(! $isCreate && $role->isSystemRole())
                        <input type="hidden" name="is_active" value="1">
                        <input id="is_active" type="text" value="Aktif — role sistem selalu tersedia" disabled class="block w-full rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-500">
                    @else
                        <select id="is_active" name="is_active" required class="block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" @selected((string) old('is_active', $isCreate ? '1' : (int) $role->is_active) === '1')>Aktif</option>
                            <option value="0" @selected((string) old('is_active', $isCreate ? '1' : (int) $role->is_active) === '0')>Nonaktif</option>
                        </select>
                        @if(! $isCreate)<p class="mt-1 text-xs text-slate-500">Role nonaktif tidak memberikan akses kepada pengguna yang menggunakannya.</p>@endif
                    @endif
                    @error('is_active')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-5">
                    <a href="{{ route('module.roles') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
                    <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">{{ $isCreate ? 'Simpan Role' : 'Simpan Perubahan' }}</button>
                </div>
            </form>
        </section>
    </div>
</x-dashboard-layout>
