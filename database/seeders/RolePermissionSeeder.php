<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // =========================
        // 1. DAFTAR PERMISSION
        // =========================

        $permissions = [
            'view_dashboard',

            'view_products',
            'create_products',
            'edit_products',
            'delete_products',

            'view_sales',
            'create_sales',
            'edit_sales',
            'collect_sales_payments',
            'discount_sales',

            'view_purchases',
            'create_purchases',
            'edit_purchases',

            'view_service',
            'create_service',
            'edit_service',
            'update_service_progress',

            'view_stock',
            'adjust_stock',

            'view_income',
            'create_income',
            'view_expense',
            'create_expense',
            'edit_expense',
            'delete_expense',
            'view_cash',
            'view_payroll',
            'manage_payroll',

            'view_reports',
            'view_profit_loss',
            'view_hpp',

            'manage_users',
            'manage_roles',

            'use_ai',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => ucwords(str_replace('_', ' ', $permissionName))]
            );
        }

        // =========================
        // 2. DAFTAR ROLE
        // =========================

        $roles = [
            'PIMPINAN' => 'Pemilik/pimpinan usaha',
            'MANAGER' => 'Pengelola operasional usaha',
            'ADMIN' => 'Administrator sistem',
            'KASIR' => 'Petugas transaksi dan pembayaran',
            'TEKNISI' => 'Petugas teknis/service',
            'PELANGGAN' => 'Customer yang menggunakan layanan',
        ];

        foreach ($roles as $roleName => $description) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => $description]
            );
        }

        // =========================
        // 3. AMBIL ROLE
        // =========================

        $pimpinan = Role::where('name', 'PIMPINAN')->first();
        $manager = Role::where('name', 'MANAGER')->first();
        $admin = Role::where('name', 'ADMIN')->first();
        $kasir = Role::where('name', 'KASIR')->first();
        $teknisi = Role::where('name', 'TEKNISI')->first();
        $pelanggan = Role::where('name', 'PELANGGAN')->first();

        // =========================
        // 4. PERMISSION PER ROLE
        // =========================

        $allPermissions = Permission::pluck('id', 'name');

        // PIMPINAN
        $pimpinan->permissions()->sync(
            $allPermissions->values()
        );

        // MANAGER
        $managerPermissions = [
            'view_dashboard',
            'view_products',
            'create_products',
            'edit_products',
            'view_sales',
            'create_sales',
            'edit_sales',
            'collect_sales_payments',
            'discount_sales',
            'view_purchases',
            'create_purchases',
            'edit_purchases',
            'view_service',
            'create_service',
            'edit_service',
            'update_service_progress',
            'view_stock',
            'adjust_stock',
            'view_income',
            'create_income',
            'view_expense',
            'create_expense',
            'edit_expense',
            'delete_expense',
            'view_cash',
            'view_reports',
            'view_profit_loss',
            'view_hpp',
            'use_ai',
            'view_payroll',
            'manage_payroll',
        ];

        $manager->permissions()->sync(
            collect($managerPermissions)
                ->map(fn ($name) => $allPermissions[$name])
                ->filter()
                ->values()
        );

        // ADMIN
        $adminPermissions = [
            'view_dashboard',
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',
            'view_sales',
            'create_sales',
            'edit_sales',
            'collect_sales_payments',
            'discount_sales',
            'view_purchases',
            'create_purchases',
            'edit_purchases',
            'view_service',
            'create_service',
            'edit_service',
            'update_service_progress',
            'view_stock',
            'adjust_stock',
            'manage_users',
            'manage_roles',
            'use_ai',
        ];

        $admin->permissions()->sync(
            collect($adminPermissions)
                ->map(fn ($name) => $allPermissions[$name])
                ->filter()
                ->values()
        );

        // KASIR
        $kasirPermissions = [
            'view_dashboard',
            'view_products',
            'view_sales',
            'create_sales',
            'edit_sales',
            'collect_sales_payments',
            'discount_sales',
            'view_service',
            'create_service',
            'view_stock',
            'view_income',
            'create_income',
            'view_cash',
            'use_ai',
        ];

        $kasir->permissions()->sync(
            collect($kasirPermissions)
                ->map(fn ($name) => $allPermissions[$name])
                ->filter()
                ->values()
        );

        // TEKNISI
        $teknisiPermissions = [
            'view_dashboard',
            'view_products',
            'view_service',
            'create_service',
            'edit_service',
            'update_service_progress',
            'view_stock',
            'use_ai',
        ];

        $teknisi->permissions()->sync(
            collect($teknisiPermissions)
                ->map(fn ($name) => $allPermissions[$name])
                ->filter()
                ->values()
        );

        // PELANGGAN
        $pelangganPermissions = [
            'view_dashboard',
            'view_service',
        ];

        $pelanggan->permissions()->sync(
            collect($pelangganPermissions)
                ->map(fn ($name) => $allPermissions[$name])
                ->filter()
                ->values()
        );
    }
}
