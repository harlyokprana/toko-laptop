<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_orders_require_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $this->actingAs($user)->get(route('purchase-orders.index'))->assertForbidden();
        $this->actingAs($user)->post(route('purchase-orders.store'), [])->assertForbidden();
    }

    public function test_creating_a_purchase_order_calculates_totals_and_does_not_change_stock(): void
    {
        $user = $this->userWithPermissions(['view_purchases', 'create_purchases']);
        [$supplier, $product] = $this->fixtures(5);
        $this->actingAs($user)->get(route('purchase-orders.create'))->assertOk()->assertSee('Buat Purchase Order');

        $this->actingAs($user)->post(route('purchase-orders.store'), $this->payload($supplier, $product))
            ->assertRedirect(route('purchase-orders.show', 1));

        $this->assertDatabaseHas('pembelian', [
            'id' => 1, 'no_invoice' => 'PO-'.now()->format('Ymd').'-0001', 'status' => 'MENUNGGU_APPROVAL',
            'subtotal' => 200, 'diskon' => 20, 'ongkir' => 10, 'pajak' => 19, 'total' => 209,
        ]);
        $this->assertDatabaseHas('pembelian_detail', ['pembelian_id' => 1, 'produk_id' => $product->id, 'qty' => 2, 'qty_diterima' => 0, 'subtotal' => 180]);
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 5]);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_approval_and_partial_receiving_update_stock_and_history_atomically(): void
    {
        $user = $this->userWithPermissions(['view_purchases', 'create_purchases', 'edit_purchases']);
        [$supplier, $product] = $this->fixtures(5);
        $this->actingAs($user)->post(route('purchase-orders.store'), $this->payload($supplier, $product));
        $purchase = \App\Models\Pembelian::query()->firstOrFail();

        $this->post(route('purchase-orders.approve', $purchase))->assertRedirect();
        $this->post(route('purchase-orders.process', $purchase))->assertRedirect();
        $this->get(route('purchase-orders.receiving', $purchase))->assertOk()->assertSee('Penerimaan Purchase Order');
        $this->get(route('purchase-orders.show', $purchase))->assertOk()->assertSee($purchase->no_invoice);
        $this->post(route('purchase-orders.receiving.store', $purchase), $this->receiptPayload($purchase, 1))->assertRedirect();
        $this->assertDatabaseHas('pembelian', ['id' => $purchase->id, 'status' => 'SEBAGIAN_DITERIMA']);
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 6]);
        $this->assertDatabaseHas('pembelian_detail', ['pembelian_id' => $purchase->id, 'qty_diterima' => 1]);
        $this->assertDatabaseHas('stok_mutasi', ['jenis_mutasi' => 'PEMBELIAN', 'referensi_type' => 'pembelian_penerimaan', 'qty_masuk' => 1, 'qty_keluar' => 0]);

        $receipt = $this->receiptPayload($purchase, 1);
        $receipt['tanggal_terima'] = now()->addMinute()->format('Y-m-d H:i:s');
        $this->post(route('purchase-orders.receiving.store', $purchase), $receipt)->assertRedirect();
        $this->assertDatabaseHas('pembelian', ['id' => $purchase->id, 'status' => 'SELESAI']);
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 7]);
        $this->assertDatabaseCount('pembelian_penerimaan', 2);
        $this->assertDatabaseCount('stok_mutasi', 2);
        $this->assertDatabaseHas('pembelian_history', ['pembelian_id' => $purchase->id, 'event' => 'PENERIMAAN']);
    }

    public function test_receiving_more_than_remaining_quantity_is_rejected_without_side_effects(): void
    {
        $user = $this->userWithPermissions(['view_purchases', 'create_purchases', 'edit_purchases']);
        [$supplier, $product] = $this->fixtures(5);
        $this->actingAs($user)->post(route('purchase-orders.store'), $this->payload($supplier, $product));
        $purchase = \App\Models\Pembelian::query()->firstOrFail();
        $this->post(route('purchase-orders.approve', $purchase));
        $this->post(route('purchase-orders.receiving.store', $purchase), $this->receiptPayload($purchase, 3))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 5]);
        $this->assertDatabaseCount('pembelian_penerimaan', 0);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_supplier_payment_is_separate_from_receiving_and_records_cash_out(): void
    {
        $user = $this->userWithPermissions(['view_purchases', 'create_purchases', 'edit_purchases']);
        [$supplier, $product] = $this->fixtures(5);
        $this->actingAs($user)->post(route('purchase-orders.store'), $this->payload($supplier, $product));
        $purchase = \App\Models\Pembelian::query()->firstOrFail();

        $this->post(route('purchase-orders.payments.store', $purchase), [
            'nominal' => 50, 'metode' => 'CASH', 'keterangan' => 'Uang muka supplier',
        ])->assertRedirect();

        $this->assertDatabaseHas('pembelian', ['id' => $purchase->id, 'dibayar' => 50, 'status' => 'MENUNGGU_APPROVAL']);
        $this->assertDatabaseHas('pembayaran', ['referensi_type' => 'PEMBELIAN', 'referensi_id' => $purchase->id, 'metode' => 'CASH', 'nominal' => 50]);
        $this->assertDatabaseHas('kas_mutasi', ['jenis' => 'PEMBAYARAN', 'kredit' => 50, 'debit' => 0]);
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 5]);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    private function fixtures(int $stock): array
    {
        $supplier = Supplier::create(['kode_supplier' => 'SUP-'.uniqid(), 'nama' => 'Supplier Tes', 'status' => 'AKTIF']);
        $category = Kategori::create(['nama' => 'Aksesori Tes', 'jenis' => 'AKSESORIS', 'status' => 'AKTIF']);
        $product = Produk::create([
            'kode_produk' => 'PROD-'.uniqid(), 'nama_produk' => 'Mouse Tes', 'kategori_id' => $category->id,
            'satuan' => 'PCS', 'harga_beli' => 100, 'harga_jual' => 150, 'stok' => $stock,
            'stok_minimum' => 1, 'status' => 'AKTIF',
        ]);
        return [$supplier, $product];
    }

    private function payload(Supplier $supplier, Produk $product): array
    {
        return [
            'supplier_id' => $supplier->id, 'tanggal' => now()->toDateString(), 'termin_pembayaran' => 'NET 30',
            'ongkir' => 10, 'pajak_persen' => 10, 'keterangan' => 'Pesanan tes',
            'items' => [['product_id' => $product->id, 'qty' => 2, 'price' => 100, 'discount_type' => 'PERCENT', 'discount_value' => 10]],
        ];
    }

    private function receiptPayload(\App\Models\Pembelian $purchase, int $quantity): array
    {
        return [
            'tanggal_terima' => now()->format('Y-m-d H:i:s'),
            'items' => [['detail_id' => $purchase->details()->firstOrFail()->id, 'qty' => $quantity, 'kondisi' => 'BAIK']],
        ];
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_'.uniqid()]);
        foreach ($permissions as $permission) $role->permissions()->attach(Permission::create(['name' => $permission]));
        return User::factory()->create(['role_id' => $role->id]);
    }
}
