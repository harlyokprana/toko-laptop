<x-dashboard-layout title="Tambah Pengguna">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('module.users') }}" class="text-sm font-semibold text-blue-700">← Kembali ke User Management</a>
        <div class="mb-6 mt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Manajemen akun</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Tambah Pengguna</h1>
            <p class="mt-1 text-sm text-slate-500">Buat akun staf dan tentukan role aksesnya.</p>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('users.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">
                    Nama lengkap
                    <input name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Nomor telepon
                    <input name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Role
                    <select name="role_id" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Pilih role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}{{ $role->description ? ' — '.$role->description : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Kata sandi
                    <input type="password" name="password" required autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Konfirmasi kata sandi
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-sm font-medium text-slate-700">
                    Status akun
                    <select name="status" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="AKTIF" @selected(old('status', 'AKTIF') === 'AKTIF')>Aktif</option>
                        <option value="NONAKTIF" @selected(old('status') === 'NONAKTIF')>Nonaktif</option>
                    </select>
                </label>
            </div>
            <p class="mt-5 rounded-lg bg-slate-50 px-3 py-2.5 text-xs leading-5 text-slate-600">Role Pimpinan tidak dapat dipilih untuk mencegah pembuatan akun dengan akses pemilik tambahan.</p>
            <div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5">
                <a href="{{ route('module.users') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600">Batal</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
