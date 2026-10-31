<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    private const PERMISSION_GROUPS = [
        'Dashboard' => [
            'view_dashboard' => 'Melihat dashboard',
        ],
        'Produk & Inventaris' => [
            'view_products' => 'Melihat katalog produk',
            'create_products' => 'Menambahkan produk',
            'edit_products' => 'Mengubah data produk',
            'delete_products' => 'Menghapus produk yang belum digunakan',
            'view_stock' => 'Melihat stok dan detail unit',
            'adjust_stock' => 'Menerima stok dan melakukan penyesuaian',
        ],
        'Penjualan & Pelanggan' => [
            'view_sales' => 'Melihat transaksi dan data pelanggan',
            'create_sales' => 'Membuat transaksi dan mencatat pembayaran',
            'edit_sales' => 'Mengubah data pelanggan',
            'collect_sales_payments' => 'Menerima pembayaran susulan transaksi',
            'discount_sales' => 'Memberikan diskon pada transaksi',
        ],
        'Pembelian' => [
            'view_purchases' => 'Melihat supplier dan purchase order',
            'create_purchases' => 'Membuat supplier dan purchase order',
            'edit_purchases' => 'Mengubah, menyetujui, menerima, dan membayar purchase order',
        ],
        'Service' => [
            'view_service' => 'Melihat data service',
            'create_service' => 'Menerima service baru',
            'edit_service' => 'Mengubah data dan proses service',
            'update_service_progress' => 'Memperbarui progress service',
        ],
        'Kas & Pendapatan' => [
            'view_cash' => 'Melihat kas dan mengelola shift',
            'view_income' => 'Melihat pendapatan',
            'create_income' => 'Mencatat pendapatan',
        ],
        'Pengeluaran' => [
            'view_expense' => 'Melihat pengeluaran',
            'create_expense' => 'Mencatat pengeluaran',
            'edit_expense' => 'Mengubah pengeluaran',
            'delete_expense' => 'Menghapus pengeluaran',
        ],
        'Laporan & Penggajian' => [
            'view_reports' => 'Melihat laporan usaha',
            'view_profit_loss' => 'Melihat laporan laba rugi',
            'view_hpp' => 'Melihat HPP dan nilai persediaan',
            'view_payroll' => 'Melihat penggajian',
            'manage_payroll' => 'Membuat, membayar, dan membatalkan penggajian',
        ],
        'Pengaturan & Pengguna' => [
            'manage_users' => 'Mengelola akun pengguna',
            'manage_roles' => 'Mengelola role dan hak akses',
        ],
        'Fitur Lainnya' => [
            'use_ai' => 'Menggunakan AI Assistant',
        ],
    ];

    public function index(): View
    {
        $permissions = Permission::query()->get(['id', 'name']);
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->with('permissions:id,name')
            ->orderBy('name')
            ->paginate(12);

        $roles->getCollection()->transform(function (Role $role) use ($permissions) {
            $assigned = $role->permissions->pluck('name')->flip();
            $role->active_modules = collect($this->groupedPermissions($permissions))
                ->filter(fn (Collection $group) => $group->contains(fn (array $permission) => $assigned->has($permission['name'])))
                ->count();
            $role->display_name = Str::title(Str::lower($role->name));

            return $role;
        });

        return view('roles.index', [
            'roles' => $roles,
            'roleCount' => Role::query()->count(),
            'activeRoleCount' => Role::query()->where('is_active', true)->count(),
            'permissionCount' => $permissions->count(),
        ]);
    }

    public function create(): View
    {
        return view('roles.form', ['role' => new Role, 'isCreate' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => Str::upper(trim((string) $request->input('name')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9 _-]+$/', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $role = Role::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN),
        ]);

        return redirect()->route('roles.permissions.edit', $role)
            ->with('status', 'Role berhasil dibuat. Atur hak aksesnya sebelum digunakan.');
    }

    public function edit(Role $role): View
    {
        $role->display_name = Str::title(Str::lower($role->name));

        return view('roles.form', ['role' => $role, 'isCreate' => false]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $request->merge(['name' => Str::upper(trim((string) $request->input('name')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9 _-]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($role->isSystemRole() && $data['name'] !== $role->name) {
            return back()->withInput()->withErrors(['name' => 'Nama role sistem tidak dapat diubah.']);
        }

        if ($role->isSystemRole() && ! filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            return back()->withInput()->withErrors(['is_active' => 'Role sistem tidak dapat dinonaktifkan.']);
        }

        if ($request->user()->role_id === $role->id && ! filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            return back()->withInput()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan role yang sedang digunakan akun Anda.']);
        }

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN),
        ]);

        return redirect()->route('module.roles')->with('status', 'Data role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isSystemRole()) {
            return back()->withErrors(['role' => 'Role bawaan sistem tidak dapat dihapus.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Role tidak dapat dihapus karena masih digunakan akun.']);
        }

        $role->delete();

        return redirect()->route('module.roles')->with('status', 'Role berhasil dihapus.');
    }

    public function editPermissions(Role $role): View
    {
        $role->load('permissions:id,name');
        $role->display_name = Str::title(Str::lower($role->name));

        return view('roles.permissions', [
            'role' => $role,
            'permissionGroups' => $this->groupedPermissions(Permission::query()->orderBy('name')->get()),
            'assignedPermissionIds' => $role->permissions->modelKeys(),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
        ]);
        $permissionIds = collect($data['permissions'] ?? [])->map(fn ($id) => (int) $id);

        if ($role->name === 'PIMPINAN') {
            $required = Permission::query()->whereIn('name', ['view_dashboard', 'manage_roles'])->pluck('id');
            if ($required->diff($permissionIds)->isNotEmpty()) {
                return back()->withErrors(['permissions' => 'Role Pimpinan harus tetap memiliki akses dashboard dan pengelolaan role.']);
            }
        }

        if ($request->user()->role_id === $role->id && ! $permissionIds->contains(Permission::query()->where('name', 'manage_roles')->value('id'))) {
            return back()->withErrors(['permissions' => 'Role yang sedang Anda gunakan harus tetap memiliki akses pengelolaan role.']);
        }

        DB::transaction(fn () => $role->permissions()->sync($permissionIds->all()));

        return redirect()->route('roles.permissions.edit', $role)->with('status', 'Hak akses berhasil disimpan.');
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, Collection<int, array{id: int, name: string, label: string}>>
     */
    private function groupedPermissions(Collection $permissions): array
    {
        $byName = $permissions->keyBy('name');
        $groups = [];

        foreach (self::PERMISSION_GROUPS as $groupName => $definitions) {
            $group = collect($definitions)->map(function (string $label, string $name) use ($byName) {
                $permission = $byName->get($name);

                return $permission ? [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $label,
                ] : null;
            })->filter()->values();

            if ($group->isNotEmpty()) {
                $groups[$groupName] = $group;
            }
        }

        $knownNames = collect(self::PERMISSION_GROUPS)->flatMap(fn (array $group) => array_keys($group))->flip();
        $other = $permissions->reject(fn (Permission $permission) => $knownNames->has($permission->name))
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->description ?: Str::headline($permission->name),
            ])->values();

        if ($other->isNotEmpty()) {
            $groups['Fitur Lainnya'] = ($groups['Fitur Lainnya'] ?? collect())->concat($other)->values();
        }

        return $groups;
    }
}
