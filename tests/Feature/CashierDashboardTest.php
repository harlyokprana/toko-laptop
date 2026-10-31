<?php

namespace Tests\Feature;

use App\Models\KasMutasi;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\Role;
use App\Models\Service;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_dashboard_uses_real_sales_service_payment_and_cash_data(): void
    {
        $cashier = $this->userWithPermissions([
            'view_dashboard', 'view_sales', 'create_sales', 'view_service', 'create_service',
            'view_stock', 'view_cash', 'view_income', 'create_income',
        ]);
        $customer = Pelanggan::create(['kode_pelanggan' => 'CUS-DASH-1', 'nama' => 'Andi Dashboard', 'status' => 'AKTIF']);
        Penjualan::create([
            'no_invoice' => 'INV-DASH-1', 'tanggal' => today(), 'pelanggan_id' => $customer->id,
            'jenis_penjualan' => 'LAPTOP', 'subtotal' => 250000, 'total' => 250000, 'dibayar' => 250000,
            'status' => 'SELESAI', 'user_id' => $cashier->id,
        ]);
        Service::create([
            'no_service' => 'SRV-DASH-1', 'pelanggan_id' => $customer->id, 'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop', 'merek' => 'Lenovo', 'model' => 'ThinkPad',
            'keluhan' => 'Tidak menyala', 'status' => 'DIAGNOSA', 'jasa' => 75000, 'total' => 75000, 'dibayar' => 0,
        ]);
        Pembayaran::create([
            'tanggal' => now(), 'referensi_type' => 'PENJUALAN', 'referensi_id' => 1,
            'metode' => 'CASH', 'nominal' => 250000, 'status' => 'LUNAS', 'user_id' => $cashier->id,
        ]);
        KasMutasi::create([
            'tanggal' => now(), 'jenis' => 'PENDAPATAN', 'keterangan' => 'Penjualan demo',
            'debit' => 250000, 'kredit' => 0, 'saldo' => 250000, 'user_id' => $cashier->id,
        ]);

        $this->actingAs($cashier)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang, '.$cashier->name)
            ->assertSee('Penjualan Hari Ini')
            ->assertSee('Service Masuk Hari Ini')
            ->assertSee('Kas Masuk Hari Ini')
            ->assertSee('Pendapatan Hari Ini')
            ->assertSee('Penjualan 7 Hari Terakhir')
            ->assertSee('Metode Pembayaran Hari Ini')
            ->assertSee('Status Service')
            ->assertSee('Stok Menipis')
            ->assertSee('INV-DASH-1')
            ->assertSee('SRV-DASH-1')
            ->assertDontSee('Purchase Order');
    }

    public function test_cashier_sidebar_groups_only_operational_cashier_menus(): void
    {
        $cashier = $this->userWithPermissions(['view_dashboard', 'view_sales', 'create_sales', 'view_service', 'create_service', 'view_cash', 'view_income', 'create_income']);

        $this->actingAs($cashier)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Laptop Prime.Com')
            ->assertSee('Kasir (POS)')
            ->assertSee('Shift & Kas')
            ->assertSee('Setoran Kas')
            ->assertDontSee('Role & Permission')
            ->assertDontSee('Pengeluaran');
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::create(['name' => 'KASIR']);
        foreach ($names as $name) {
            $role->permissions()->attach(Permission::create(['name' => $name]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
