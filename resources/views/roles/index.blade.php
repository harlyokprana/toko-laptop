<x-dashboard-layout title="Role & Permission">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">Atur kelompok akses dan tindakan yang dapat dilakukan setiap role.</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Role & Permission</h1>
        </div>
        <a href="{{ route('roles.create') }}" class="rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Tambah Role</a>
    </div>

    @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <section aria-label="Ringkasan role" class="mb-5 grid gap-3 sm:grid-cols-3">
        @foreach([
            ['label' => 'Total Role', 'value' => $roleCount, 'detail' => 'Kelompok akses terdaftar'],
            ['label' => 'Role Aktif', 'value' => $activeRoleCount, 'detail' => 'Dapat digunakan untuk akses aplikasi'],
            ['label' => 'Jenis Hak Akses', 'value' => $permissionCount, 'detail' => 'Tindakan yang dapat diatur'],
        ] as $stat)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-950">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $stat['detail'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-4 xl:grid-cols-2" aria-label="Daftar role">
        @forelse($roles as $role)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-950">{{ $role->display_name }}</h2>
                            @if($role->is_active)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Aktif</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Nonaktif</span>
                            @endif
                            @if($role->isSystemRole())
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">Role sistem</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $role->description ?: 'Belum ada deskripsi.' }}</p>
                    </div>
                    <span class="shrink-0 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ number_format($role->users_count, 0, ',', '.') }} pengguna</span>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">{{ $role->active_modules }} Modul</span>
                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">{{ $role->permissions_count }} Hak Akses</span>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                    <a href="{{ route('roles.permissions.edit', $role) }}" class="rounded-lg bg-blue-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-blue-800">Kelola Akses</a>
                    <a href="{{ route('roles.edit', $role) }}" class="rounded-lg border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                    @if(! $role->isSystemRole() && $role->users_count === 0)
                        <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role {{ $role->display_name }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-lg border border-rose-200 px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white px-6 py-14 text-center text-sm text-slate-500 xl:col-span-2">Belum ada role yang terdaftar.</div>
        @endforelse
    </section>

    @if($roles->hasPages())
        <div class="mt-5">{{ $roles->links() }}</div>
    @endif
</x-dashboard-layout>
