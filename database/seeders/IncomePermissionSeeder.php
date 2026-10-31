<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class IncomePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'create_income'],
            ['description' => 'Create income records'],
        );

        Role::query()->whereIn('name', ['PIMPINAN', 'MANAGER', 'KASIR'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }
}
