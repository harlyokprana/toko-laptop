<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_product_without_setting_purchase_cost_or_stock(): void
    {
        $user = $this->userWithPermissions(['view_products', 'create_products']);
        $category = $this->category();
        $this->actingAs($user)->get(route('products.create'))
            ->assertOk()
            ->assertSee('name="stok_minimum" value="0"', false);

        $response = $this->actingAs($user)->post(route('products.store'), [
            'jenis_produk' => 'AKSESORIS',
            'nama_produk' => 'Mouse Wireless',
            'atribut' => ['jenis_aksesori' => 'Mouse'],
            'satuan' => 'PCS',
            'harga_jual' => '250000',
            'stok_minimum' => 3,
            'status' => 'AKTIF',
            'harga_beli' => '125000',
            'stok' => 20,
        ]);

        $product = Produk::where('nama_produk', 'Mouse Wireless')->firstOrFail();

        $response->assertRedirect(route('products.index'));
        $this->assertSame('ACC-0001', $product->kode_produk);
        $this->assertSame($category->id, $product->kategori_id);
        $this->assertSame('0.00', $product->harga_beli);
        $this->assertSame('0.00', $product->stok);
        $this->actingAs($user)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Produk berhasil ditambahkan.')
            ->assertSee('Mouse Wireless')
            ->assertDontSee('Harga Beli');
    }

    public function test_product_creation_creates_a_compatible_legacy_category_when_needed(): void
    {
        $user = $this->userWithPermissions(['view_products', 'create_products']);

        $this->actingAs($user)->post(route('products.store'), [
            'jenis_produk' => 'PC_RAKITAN',
            'nama_produk' => 'PC Rakitan Test',
            'merek' => 'Custom',
            'atribut' => ['motherboard' => 'ASUS B650'],
            'satuan' => 'UNIT',
            'harga_jual' => 15000000,
            'stok_minimum' => 0,
            'status' => 'AKTIF',
        ])->assertRedirect();

        $product = Produk::where('nama_produk', 'PC Rakitan Test')->firstOrFail();
        $this->assertDatabaseHas('kategori', [
            'id' => $product->kategori_id,
            'nama' => 'PC Rakitan',
            'jenis' => 'LAINNYA',
        ]);
    }

    public function test_product_table_shows_laptop_specifications(): void
    {
        $user = $this->userWithPermissions(['view_products']);
        $category = Kategori::create(['nama' => 'Laptop Spesifikasi', 'jenis' => 'LAPTOP', 'status' => 'AKTIF']);
        Produk::create([
            'kode_produk' => 'LAP-SPEC-01',
            'nama_produk' => 'Laptop Test',
            'jenis_produk' => 'LAPTOP',
            'kategori_id' => $category->id,
            'merek' => 'Lenovo',
            'tipe' => 'ThinkPad',
            'atribut' => [
                'processor' => 'Intel Core i7',
                'ram' => '16 GB',
                'storage' => 'SSD NVMe 512 GB',
                'ukuran_layar' => '14 inch',
            ],
            'satuan' => 'UNIT',
            'harga_jual' => 10000000,
            'stok' => 1,
            'stok_minimum' => 0,
            'status' => 'AKTIF',
        ]);

        $this->actingAs($user)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Intel Core i7')
            ->assertSee('RAM 16 GB')
            ->assertSee('Storage SSD NVMe 512 GB')
            ->assertSee('Layar 14 inch');
    }

    public function test_new_product_brands_are_available_as_suggestions_next_time(): void
    {
        $user = $this->userWithPermissions(['view_products', 'create_products']);

        $this->actingAs($user)->post(route('products.store'), [
            'jenis_produk' => 'LAPTOP',
            'nama_produk' => 'MacBook Air',
            'merek' => 'Apple',
            'tipe' => 'MacBook Air M4',
            'satuan' => 'UNIT',
            'harga_jual' => 18000000,
            'stok_minimum' => 0,
            'status' => 'AKTIF',
        ])->assertRedirect();

        $this->actingAs($user)->get(route('products.create'))
            ->assertOk()
            ->assertSee('<option value="Apple">', false);
    }

    public function test_pimpinan_can_create_edit_and_delete_unused_product(): void
    {
        $user = $this->userWithPermissions(['view_products', 'create_products', 'edit_products', 'delete_products', 'view_profit_loss']);
        $this->category();

        $this->actingAs($user)->post(route('products.store'), [
            'jenis_produk' => 'AKSESORIS',
            'nama_produk' => 'SSD 1TB',
            'atribut' => ['jenis_aksesori' => 'Lainnya', 'jenis_aksesori_custom' => 'SSD'],
            'satuan' => 'PCS',
            'harga_beli' => '900000',
            'harga_jual' => '1250000',
            'stok_minimum' => 2,
            'status' => 'AKTIF',
        ])->assertRedirect();

        $product = Produk::where('nama_produk', 'SSD 1TB')->firstOrFail();

        $this->actingAs($user)->put(route('products.update', $product), [
            'jenis_produk' => 'AKSESORIS',
            'nama_produk' => 'SSD 1TB Gen 4',
            'atribut' => ['jenis_aksesori' => 'Lainnya', 'jenis_aksesori_custom' => 'SSD'],
            'satuan' => 'PCS',
            'harga_beli' => '950000',
            'harga_jual' => '1300000',
            'stok_minimum' => 4,
            'status' => 'AKTIF',
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('produk', ['id' => $product->id, 'nama_produk' => 'SSD 1TB Gen 4', 'harga_beli' => '0.00']);

        $this->actingAs($user)->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('produk', ['id' => $product->id]);
    }

    public function test_product_with_existing_stock_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions(['view_products', 'delete_products']);
        $product = $this->product(['stok' => 4]);

        $this->actingAs($user)->from(route('products.index'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('produk', ['id' => $product->id]);
    }

    public function test_technician_can_list_products_but_cannot_create_them(): void
    {
        $user = $this->userWithPermissions(['view_products']);

        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('products.create'))->assertForbidden();
        $this->actingAs($user)->post(route('products.store'), [])->assertForbidden();
    }

    public function test_categories_page_redirects_to_the_unified_product_catalog(): void
    {
        $user = $this->userWithPermissions(['view_products']);

        $this->actingAs($user)->get(route('categories.index'))
            ->assertRedirect(route('products.index'));
    }

    private function category(): Kategori
    {
        return Kategori::create(['nama' => 'Aksesori', 'jenis' => 'AKSESORIS', 'status' => 'AKTIF']);
    }

    private function product(array $overrides = []): Produk
    {
        return Produk::create(array_merge([
            'kode_produk' => 'PRODUCT-'.uniqid(),
            'nama_produk' => 'Produk Test',
            'kategori_id' => $this->category()->id,
            'satuan' => 'PCS',
            'harga_beli' => 0,
            'harga_jual' => 100000,
            'stok' => 0,
            'stok_minimum' => 0,
            'status' => 'AKTIF',
        ], $overrides));
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
