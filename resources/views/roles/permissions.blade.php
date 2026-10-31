<x-dashboard-layout title="Kelola Akses Role">
    @php
        $selectedPermissionIds = collect(old('permissions', $assignedPermissionIds))->map(fn ($id) => (int) $id)->all();
    @endphp
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('module.roles') }}" class="text-sm font-semibold text-blue-700 hover:underline">← Kembali ke daftar role</a>
            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Kelola Akses</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">{{ $role->display_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Pilih tindakan yang boleh dilakukan pengguna dengan role ini.</p>
        </div>
        <span class="rounded-full {{ $role->is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }} px-3 py-1.5 text-xs font-semibold">{{ $role->is_active ? 'Aktif' : 'Nonaktif' }}</span>
    </div>

    @if(session('status'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('roles.permissions.update', $role) }}">
        @csrf @method('PUT')
        <div class="sticky top-[68px] z-20 mb-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="flex flex-wrap items-center gap-3">
                <label class="relative min-w-[220px] flex-1">
                    <span class="sr-only">Cari fitur atau hak akses</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                    <input id="permission-search" type="search" placeholder="Cari fitur atau hak akses..." class="h-10 w-full rounded-lg border-slate-200 bg-slate-50 pl-9 text-sm focus:border-blue-500 focus:ring-blue-500">
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input id="select-all-permissions" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                    Pilih semua
                </label>
                <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Simpan Perubahan</button>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            @foreach($permissionGroups as $groupName => $permissions)
                @php $groupId = 'permission-group-'.\Illuminate\Support\Str::slug($groupName); @endphp
                <section class="permission-group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-group="{{ \Illuminate\Support\Str::lower($groupName) }}">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-800">{{ $groupName }}</h2>
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input type="checkbox" data-group-toggle="{{ $groupId }}" class="group-toggle rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                            Pilih modul
                        </label>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($permissions as $permission)
                            <label class="permission-item flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50" data-search="{{ \Illuminate\Support\Str::lower($groupName.' '.$permission['label'].' '.$permission['name']) }}">
                                <input type="checkbox" name="permissions[]" value="{{ $permission['id'] }}" @checked(in_array($permission['id'], $selectedPermissionIds, true)) class="permission-checkbox mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-500" data-group-id="{{ $groupId }}">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-800">{{ $permission['label'] }}</span>
                                    <span class="mt-0.5 block text-[11px] text-slate-400">{{ $permission['name'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </form>

    <script>
        (() => {
            const checkboxes = [...document.querySelectorAll('.permission-checkbox')];
            const selectAll = document.getElementById('select-all-permissions');
            const search = document.getElementById('permission-search');
            const groupToggles = [...document.querySelectorAll('.group-toggle')];
            const updateSelectionState = () => {
                selectAll.checked = checkboxes.length > 0 && checkboxes.every(input => input.checked);
                groupToggles.forEach(toggle => {
                    const members = checkboxes.filter(input => input.dataset.groupId === toggle.dataset.groupToggle);
                    toggle.checked = members.length > 0 && members.every(input => input.checked);
                    toggle.indeterminate = members.some(input => input.checked) && !toggle.checked;
                });
            };

            selectAll.addEventListener('change', () => {
                checkboxes.forEach(input => { input.checked = selectAll.checked; });
                updateSelectionState();
            });
            checkboxes.forEach(input => input.addEventListener('change', updateSelectionState));
            groupToggles.forEach(toggle => toggle.addEventListener('change', () => {
                checkboxes.filter(input => input.dataset.groupId === toggle.dataset.groupToggle)
                    .forEach(input => { input.checked = toggle.checked; });
                updateSelectionState();
            }));
            search.addEventListener('input', () => {
                const query = search.value.trim().toLocaleLowerCase();
                document.querySelectorAll('.permission-group').forEach(section => {
                    let visible = 0;
                    section.querySelectorAll('.permission-item').forEach(item => {
                        const matches = item.dataset.search.includes(query);
                        item.hidden = !matches;
                        if (matches) visible++;
                    });
                    section.hidden = visible === 0;
                });
            });
            updateSelectionState();
        })();
    </script>
</x-dashboard-layout>
