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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_role_can_create_search_and_update_customer_without_deleting_history(): void
    {
        $user = $this->userWithPermissions(['view_sales', 'create_sales', 'edit_sales']);
        Role::firstOrCreate(['name' => 'PELANGGAN']);

        $this->actingAs($user)->get(route('customers.create'))
            ->assertOk()
            ->assertSee('value="C-00001"', false)
            ->assertSee('readonly', false);

        $this->actingAs($user)->post(route('customers.store'), [
            'kode_pelanggan' => 'CUS-FORGED',
            'nama' => 'Budi Laptop',
            'email' => 'budi@example.com',
            'telepon' => '08123456789',
            'alamat' => 'Jakarta',
            'status' => 'AKTIF',
        ])->assertRedirect();

        $customer = Pelanggan::where('nama', 'Budi Laptop')->firstOrFail();
        $this->assertSame('C-00001', $customer->kode_pelanggan);
        $account = $customer->user;
        $this->assertNotNull($account);
        $this->assertSame('08123456789', $account->phone);
        $this->assertSame('PELANGGAN', $account->role->name);
        $this->assertSame('AKTIF', $account->status);
        $this->assertTrue(Hash::check('08123456789', $account->password));

        $this->actingAs($user)->get(route('customers.index', ['q' => '08123456789']))
            ->assertOk()->assertSee('Budi Laptop');

        $this->actingAs($user)->put(route('customers.update', $customer), [
            'kode_pelanggan' => 'C-00001',
            'nama' => 'Budi Prime',
            'email' => 'budi@example.com',
            'telepon' => '08123456789',
            'alamat' => 'Jakarta',
            'status' => 'NONAKTIF',
        ])->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('pelanggan', ['id' => $customer->id, 'nama' => 'Budi Prime', 'status' => 'NONAKTIF']);
        $this->actingAs($user)->delete(route('customers.update', $customer))->assertStatus(405);

        $this->actingAs($user)->postJson(route('customers.quick-store'), [
            'nama' => 'Sari Contoh',
            'telepon' => '08123456780',
        ])->assertOk()->assertJsonPath('kode_pelanggan', 'C-00002');

        $this->assertDatabaseHas('users', ['phone' => '08123456780', 'status' => 'AKTIF']);

        $this->post('/logout');
        $this->post('/login', [
            'email' => '08123456789',
            'password' => '08123456789',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($account);
    }

    public function test_customer_phone_must_be_unique_and_duplicate_does_not_create_account(): void
    {
        Role::firstOrCreate(['name' => 'PELANGGAN']);
        $user = $this->userWithPermissions(['create_sales']);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'OLD-001',
            'nama' => 'Pelanggan Lama',
            'telepon' => '08111111111',
            'status' => 'AKTIF',
        ]);
        $account = User::factory()->create([
            'name' => $customer->nama,
            'phone' => $customer->telepon,
            'role_id' => Role::where('name', 'PELANGGAN')->value('id'),
        ]);
        $customer->user()->associate($account)->save();

        $this->actingAs($user)->post(route('customers.store'), [
            'nama' => 'Nomor Duplikat',
            'telepon' => '08111111111',
            'status' => 'AKTIF',
        ])->assertSessionHasErrors('telepon');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('pelanggan', 1);
    }

    public function test_existing_customer_account_is_linked_without_changing_its_password(): void
    {
        $customerRole = Role::firstOrCreate(['name' => 'PELANGGAN']);
        $user = $this->userWithPermissions(['create_sales']);
        $existingAccount = User::factory()->create([
            'name' => 'Akun Lama',
            'phone' => '08191919191',
            'role_id' => $customerRole->id,
            'password' => 'existing-password',
        ]);

        $this->actingAs($user)->post(route('customers.store'), [
            'nama' => 'Pelanggan Lama',
            'telepon' => '08191919191',
            'status' => 'AKTIF',
        ])->assertRedirect();

        $customer = Pelanggan::where('telepon', '08191919191')->firstOrFail();
        $this->assertSame($existingAccount->id, $customer->user_id);
        $this->assertTrue(Hash::check('existing-password', $existingAccount->fresh()->password));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_legacy_customer_without_account_gets_one_when_phone_is_added(): void
    {
        Role::firstOrCreate(['name' => 'PELANGGAN']);
        $user = $this->userWithPermissions(['edit_sales']);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'OLD-002',
            'nama' => 'Pelanggan Lama',
            'status' => 'AKTIF',
        ]);

        $this->actingAs($user)->put(route('customers.update', $customer), [
            'kode_pelanggan' => $customer->kode_pelanggan,
            'nama' => $customer->nama,
            'telepon' => '08222222222',
            'status' => 'AKTIF',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertNotNull($customer->user);
        $this->assertSame('08222222222', $customer->user->phone);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_service_role_can_quick_create_customer_and_account(): void
    {
        Role::firstOrCreate(['name' => 'PELANGGAN']);
        $user = $this->userWithPermissions(['create_service']);

        $this->actingAs($user)->postJson(route('service.customers.quick-store'), [
            'nama' => 'Pelanggan Service Baru',
            'telepon' => '08333333333',
        ])->assertOk()->assertJsonPath('nama', 'Pelanggan Service Baru');

        $this->assertDatabaseHas('users', [
            'phone' => '08333333333',
            'status' => 'AKTIF',
            'role_id' => Role::where('name', 'PELANGGAN')->value('id'),
        ]);
    }

    public function test_purchase_role_can_create_search_and_deactivate_supplier(): void
    {
        $user = $this->userWithPermissions(['view_purchases', 'create_purchases', 'edit_purchases']);

        $this->actingAs($user)->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('value="S01"', false)
            ->assertSee('readonly', false);

        $this->actingAs($user)->post(route('suppliers.store'), [
            'kode_supplier' => 'SUP-FORGED-CODE',
            'nama' => 'PT Laptop Indonesia',
            'kontak' => 'Rina',
            'telepon' => '021555000',
            'email' => 'supplier@example.com',
            'alamat' => 'Jakarta',
            'status' => 'AKTIF',
        ])->assertRedirect();

        $supplier = Supplier::where('nama', 'PT Laptop Indonesia')->firstOrFail();
        $this->assertSame('S01', $supplier->kode_supplier);
        $this->actingAs($user)->get(route('suppliers.index', ['q' => 'supplier@example.com']))
            ->assertOk()->assertSee('PT Laptop Indonesia');

        $this->actingAs($user)->put(route('suppliers.update', $supplier), [
            'kode_supplier' => 'S01',
            'nama' => 'PT Laptop Indonesia',
            'kontak' => 'Rina',
            'telepon' => '021555000',
            'email' => 'supplier@example.com',
            'alamat' => 'Jakarta',
            'status' => 'NONAKTIF',
        ])->assertRedirect(route('suppliers.show', $supplier));

        $this->assertDatabaseHas('supplier', ['id' => $supplier->id, 'status' => 'NONAKTIF']);
    }

    public function test_supplier_creation_stops_at_fifty_records(): void
    {
        $user = $this->userWithPermissions(['create_purchases']);

        for ($number = 1; $number <= 50; $number++) {
            Supplier::create([
                'kode_supplier' => 'OLD-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'nama' => 'Supplier '.$number,
                'status' => 'AKTIF',
            ]);
        }

        $this->actingAs($user)->post(route('suppliers.store'), [
            'nama' => 'Supplier Tambahan',
            'status' => 'AKTIF',
        ])->assertSessionHasErrors('kode_supplier');

        $this->assertDatabaseCount('supplier', 50);
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
