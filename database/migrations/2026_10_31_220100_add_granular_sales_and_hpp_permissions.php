<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'collect_sales_payments' => 'Collect follow-up sales payments',
            'discount_sales' => 'Apply discounts to sales',
            'view_hpp' => 'View product cost and inventory value',
        ] as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['description' => $description, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $rolePermissionNames = [
            'PIMPINAN' => ['collect_sales_payments', 'discount_sales', 'view_hpp'],
            'MANAGER' => ['collect_sales_payments', 'discount_sales', 'view_hpp'],
            'ADMIN' => ['collect_sales_payments', 'discount_sales'],
            'KASIR' => ['collect_sales_payments', 'discount_sales'],
        ];
        $timestamps = ['created_at' => now(), 'updated_at' => now()];

        foreach ($rolePermissionNames as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if ($roleId === null) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    $timestamps,
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['collect_sales_payments', 'discount_sales', 'view_hpp'])
            ->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
