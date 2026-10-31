<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Laptop;
use App\Models\Pelanggan;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_role_can_create_search_and_update_customer_without_deleting_history(): void
    {
        $user = $this->userWithPermissions(['view_sales', 'create_sales', 'edit_sales']);

        $this->actingAs($user)->post(route('customers.store'), [
            'kode_pelanggan' => 'CUS-001',
            'nama' => 'Budi Laptop',
            'email' => 'budi@example.com',
            'telepon' => '08123456789',
            'alamat' => 'Jakarta',
            'status' => 'AKTIF',
        ])->assertRedirect();

        $customer = Pelanggan::where('kode_pelanggan', 'CUS-001')->firstOrFail();

        $this->actingAs($user)->get(route('customers.index', ['q' => '08123456789']))
            ->assertOk()->assertSee('Budi Laptop');

        $this->actingAs($user)->put(route('customers.update', $customer), [
            'kode_pelanggan' => 'CUS-001',
            'nama' => 'Budi Prime',
            'email' => 'budi@example.com',
            'telepon' => '08123456789',
            'alamat' => 'Jakarta',
            'status' => 'NONAKTIF',
        ])->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('pelanggan', ['id' => $customer->id, 'nama' => 'Budi Prime', 'status' => 'NONAKTIF']);
        $this->actingAs($user)->delete(route('customers.update', $customer))->assertStatus(405);
    }

    public function test_purchase_role_can_create_search_and_deactivate_supplier(): void
    {
        $user = $this->userWithPermissions(['view_purchases', 'create_purchases', 'edit_purchases']);

        $this->actingAs($user)->post(route('suppliers.store'), [
            'kode_supplier' => 'SUP-001',
            'nama' => 'PT Laptop Indonesia',
            'kontak' => 'Rina',
            'telepon' => '021555000',
            'email' => 'supplier@example.com',
            'alamat' => 'Jakarta',
            'status' => 'AKTIF',
        ])->assertRedirect();

        $supplier = Supplier::where('kode_supplier', 'SUP-001')->firstOrFail();
        $this->actingAs($user)->get(route('suppliers.index', ['q' => 'supplier@example.com']))
            ->assertOk()->assertSee('PT Laptop Indonesia');

        $this->actingAs($user)->put(route('suppliers.update', $supplier), [
            'kode_supplier' => 'SUP-001',
            'nama' => 'PT Laptop Indonesia',
            'kontak' => 'Rina',
            'telepon' => '021555000',
            'email' => 'supplier@example.com',
            'alamat' => 'Jakarta',
            'status' => 'NONAKTIF',
        ])->assertRedirect(route('suppliers.show', $supplier));

        $this->assertDatabaseHas('supplier', ['id' => $supplier->id, 'status' => 'NONAKTIF']);
    }

    public function test_laptop_hpp_is_not_exposed_or_writable_without_financial_permission(): void
    {
        $user = $this->userWithPermissions(['view_stock', 'adjust_stock']);
        $supplier = Supplier::create(['kode_supplier' => 'SUP-02', 'nama' => 'Supplier Dua', 'status' => 'AKTIF']);
        $this->actingAs($user)->get(route('laptops.create'))
            ->assertOk()
            ->assertDontSee('name="hpp"', false)
            ->assertDontSee('name="biaya_tambahan"', false);

        $this->actingAs($user)->post(route('laptops.store'), [
            'sku' => 'LPT-002',
            'serial_number' => 'SERIAL-002',
            'supplier_id' => $supplier->id,
            'merek' => 'Lenovo',
            'model_seri' => 'ThinkPad T14',
            'kondisi' => 'Baru',
            'harga_jual' => 15000000,
            'hpp' => 10000000,
            'biaya_tambahan' => 250000,
            'status' => 'READY',
        ])->assertRedirect();

        $laptop = Laptop::where('sku', 'LPT-002')->firstOrFail();

        $this->assertSame('0.00', $laptop->hpp);
        $this->assertSame('0.00', $laptop->biaya_tambahan);
        $this->actingAs($user)->get(route('laptops.show', $laptop))
            ->assertOk()->assertDontSee('HPP')->assertDontSee('10000000');
    }

    public function test_pimpinan_can_create_and_search_individual_laptop_with_hpp(): void
    {
        $user = $this->userWithPermissions(['view_stock', 'adjust_stock', 'view_profit_loss']);
        $supplier = Supplier::create(['kode_supplier' => 'SUP-03', 'nama' => 'Supplier Tiga', 'status' => 'AKTIF']);
        $this->actingAs($user)->get(route('laptops.create'))
            ->assertOk()
            ->assertSee('name="produk_id"', false)
            ->assertSee('name="hpp"', false)
            ->assertSee('name="biaya_tambahan"', false);
        $category = Kategori::create(['nama' => 'Laptop Test', 'jenis' => 'LAPTOP', 'status' => 'AKTIF']);
        $product = Produk::create([
            'kode_produk' => 'PROD-LAP-1', 'nama_produk' => 'ThinkPad T14', 'kategori_id' => $category->id,
            'merek' => 'Lenovo', 'tipe' => 'T14', 'satuan' => 'UNIT', 'harga_beli' => 9000000,
            'harga_jual' => 12000000, 'stok' => 0, 'stok_minimum' => 0, 'status' => 'AKTIF',
        ]);

        $this->actingAs($user)->post(route('laptops.store'), [
            'sku' => 'LPT-003',
            'serial_number' => 'SERIAL-003',
            'produk_id' => $product->id,
            'supplier_id' => $supplier->id,
            'tanggal_perolehan' => '2026-10-25',
            'merek' => 'Lenovo',
            'model_seri' => 'ThinkPad T14',
            'kondisi' => 'Baru',
            'spesifikasi' => 'Core i7, RAM 16GB',
            'harga_jual' => '12500000',
            'hpp' => '9500000',
            'biaya_tambahan' => '150000',
            'status' => 'READY',
        ])->assertRedirect();

        $laptop = Laptop::where('serial_number', 'SERIAL-003')->firstOrFail();
        $this->assertSame('9500000.00', $laptop->hpp);
        $this->assertSame($product->id, $laptop->produk_id);
        $this->actingAs($user)->get(route('laptops.index', ['q' => 'SERIAL-003']))
            ->assertOk()->assertSee('SERIAL-003');
        $this->actingAs($user)->get(route('laptops.show', $laptop))
            ->assertOk()->assertSee('HPP')->assertSee('9.500.000');
    }

    public function test_stock_viewer_cannot_create_laptop_without_adjust_stock_permission(): void
    {
        $user = $this->userWithPermissions(['view_stock']);

        $this->actingAs($user)->get(route('laptops.index'))->assertOk();
        $this->actingAs($user)->get(route('laptops.create'))->assertForbidden();
        $this->actingAs($user)->post(route('laptops.store'), [])->assertForbidden();
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_ROLE']);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
