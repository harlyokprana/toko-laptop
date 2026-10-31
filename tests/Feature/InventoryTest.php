<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_adjustments_update_balance_and_record_a_mutation(): void
    {
        $user = $this->userWithPermissions(['view_stock', 'adjust_stock']);
        $product = $this->productWithStock(5);

        $this->actingAs($user)->post(route('stock.adjustments.store'), [
            'product_id' => $product->id,
            'direction' => 'MASUK',
            'quantity' => 3,
            'note' => 'Stock opname',
        ])->assertRedirect(route('stock.products.show', $product));

        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 8]);
        $this->assertDatabaseHas('stok_mutasi', [
            'produk_id' => $product->id,
            'jenis_mutasi' => 'ADJUSTMENT',
            'qty_masuk' => 3,
            'qty_keluar' => 0,
            'stok_saldo' => 8,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->post(route('stock.adjustments.store'), [
            'product_id' => $product->id,
            'direction' => 'KELUAR',
            'quantity' => 2,
            'note' => 'Barang rusak',
        ])->assertRedirect(route('stock.products.show', $product));

        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 6]);
        $this->assertSame(2, StokMutasi::where('produk_id', $product->id)->count());
    }

    public function test_stock_cannot_be_adjusted_below_zero(): void
    {
        $user = $this->userWithPermissions(['view_stock', 'adjust_stock']);
        $product = $this->productWithStock(2);

        $this->actingAs($user)->from(route('stock.adjustments.create'))
            ->post(route('stock.adjustments.store'), [
                'product_id' => $product->id,
                'direction' => 'KELUAR',
                'quantity' => 3,
                'note' => 'Test batas stok',
            ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 2]);
        $this->assertSame(0, StokMutasi::where('produk_id', $product->id)->count());
    }

    public function test_adjustment_requires_both_stock_view_and_adjust_permissions(): void
    {
        $product = $this->productWithStock(2);
        $viewer = $this->userWithPermissions(['view_stock']);

        $this->actingAs($viewer)->get(route('stock.index'))->assertOk();
        $this->actingAs($viewer)->get(route('stock.adjustments.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('stock.adjustments.store'), [])->assertForbidden();
    }

    private function productWithStock(int $stock): Produk
    {
        $category = Kategori::create(['nama' => 'Kategori Tes', 'jenis' => 'LAPTOP', 'status' => 'AKTIF']);

        return Produk::create([
            'kode_produk' => 'TEST-'.uniqid(),
            'nama_produk' => 'Laptop Tes',
            'kategori_id' => $category->id,
            'satuan' => 'UNIT',
            'harga_beli' => 5000000,
            'harga_jual' => 6500000,
            'stok' => $stock,
            'stok_minimum' => 1,
            'status' => 'AKTIF',
        ]);
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
