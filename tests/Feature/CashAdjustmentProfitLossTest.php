<?php

namespace Tests\Feature;

use App\Models\KasMutasi;
use App\Models\Kategori;
use App\Models\Pembelian;
use App\Models\Pendapatan;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CashLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdjustmentProfitLossTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_cash_adjustment_changes_balance_without_changing_income_or_expense(): void
    {
        $user = $this->userWithPermissions(['view_cash', 'view_reports', 'view_profit_loss']);
        $this->seedOpeningCash($user);

        $this->adjustCash($user, 'MASUK', 500000);

        $this->assertSame(10500000.0, (float) KasMutasi::query()->latest('id')->value('saldo'));
        $this->assertDatabaseHas('kas_mutasi', [
            'jenis' => 'ADJUSTMENT', 'debit' => 500000, 'kredit' => 0,
            'saldo' => 10500000, 'user_id' => $user->id, 'keterangan' => 'Kas manual: Koreksi saldo hasil opname',
        ]);
        $this->assertDatabaseCount('pendapatan', 0);
        $this->assertDatabaseCount('pengeluaran', 0);
        $this->assertProfitLoss(0, 0, 0);
    }

    public function test_negative_cash_adjustment_changes_balance_without_changing_income_or_expense(): void
    {
        $user = $this->userWithPermissions(['view_cash', 'view_reports', 'view_profit_loss']);
        $this->seedOpeningCash($user);

        $this->adjustCash($user, 'KELUAR', 500000);

        $this->assertSame(9500000.0, (float) KasMutasi::query()->latest('id')->value('saldo'));
        $this->assertDatabaseHas('kas_mutasi', [
            'jenis' => 'ADJUSTMENT', 'debit' => 0, 'kredit' => 500000,
            'saldo' => 9500000, 'user_id' => $user->id, 'keterangan' => 'Kas manual: Koreksi saldo hasil opname',
        ]);
        $this->assertDatabaseCount('pendapatan', 0);
        $this->assertDatabaseCount('pengeluaran', 0);
        $this->assertProfitLoss(0, 0, 0);
    }

    public function test_cash_top_up_only_changes_balance_and_is_not_recorded_as_revenue(): void
    {
        $user = $this->userWithPermissions(['view_cash', 'view_reports', 'view_profit_loss']);
        $this->seedOpeningCash($user);

        $this->actingAs($user)->post(route('cash.movements.store'), [
            'jenis' => 'PENAMBAHAN_KAS',
            'nominal' => 500000,
            'keterangan' => 'Setoran saldo kas',
        ])->assertRedirect();

        $this->assertSame(10500000.0, (float) KasMutasi::query()->latest('id')->value('saldo'));
        $this->assertDatabaseCount('pendapatan', 0);
        $this->assertDatabaseCount('pengeluaran', 0);
        $this->assertProfitLoss(0, 0, 0);
    }

    public function test_cash_adjustment_does_not_inflate_sales_revenue(): void
    {
        $user = $this->userWithPermissions([
            'view_cash', 'view_sales', 'create_sales', 'view_reports', 'view_profit_loss',
        ]);
        $this->seedOpeningCash($user);
        $product = $this->product(3000000, 5000000);

        $this->actingAs($user)->post(route('sales.store'), [
            'items' => [['produk_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'CASH', 'amount' => 5000000]],
        ])->assertRedirect();
        $this->adjustCash($user, 'MASUK', 500000);

        $this->assertSame(1, Penjualan::query()->count());
        $this->assertSame(1, Pendapatan::query()->count());
        $this->assertDatabaseHas('kas_mutasi', ['jenis' => 'ADJUSTMENT', 'debit' => 500000]);
        $response = $this->actingAs($user)->get(route('reports.show', 'profit-loss'))->assertOk();
        $response->assertSee('Rp 5.000.000');
        $response->assertDontSee('Rp 5.500.000');
        $this->assertProfitLoss(2000000, 0, 2000000);
    }

    public function test_cash_adjustment_does_not_inflate_operating_expenses(): void
    {
        $user = $this->userWithPermissions([
            'view_cash', 'view_expense', 'create_expense', 'view_reports', 'view_profit_loss',
        ]);
        $this->seedOpeningCash($user);

        $this->actingAs($user)->post(route('expenses.store'), [
            'tanggal' => today()->toDateString(),
            'kategori_beban' => 'OPERASIONAL',
            'keterangan' => 'Biaya operasional toko',
            'nominal' => 1000000,
            'metode_bayar' => 'CASH',
        ])->assertRedirect();
        $this->adjustCash($user, 'KELUAR', 500000);

        $this->assertDatabaseCount('pengeluaran', 1);
        $this->assertDatabaseHas('kas_mutasi', ['jenis' => 'ADJUSTMENT', 'kredit' => 500000]);
        $this->assertProfitLoss(0, 1000000, -1000000);
    }

    public function test_profit_loss_excludes_cash_adjustments_and_counts_purchase_cogs_and_expenses(): void
    {
        $user = $this->userWithPermissions([
            'view_cash', 'view_sales', 'create_sales', 'view_purchases', 'create_purchases',
            'edit_purchases', 'view_expense', 'create_expense', 'view_reports', 'view_profit_loss',
        ]);
        $this->seedOpeningCash($user);
        $product = $this->product(0, 5000000);
        $supplier = Supplier::create([
            'kode_supplier' => 'SUP-'.uniqid(), 'nama' => 'Supplier Tes', 'status' => 'AKTIF',
        ]);

        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => today()->toDateString(),
            'ongkir' => 0,
            'pajak_persen' => 0,
            'items' => [[
                'product_id' => $product->id, 'qty' => 1, 'price' => 3000000,
                'discount_type' => 'PERCENT', 'discount_value' => 0,
            ]],
        ])->assertRedirect();
        $purchase = Pembelian::query()->firstOrFail();
        $this->post(route('purchase-orders.approve', $purchase))->assertRedirect();
        $detail = $purchase->details()->firstOrFail();
        $this->post(route('purchase-orders.receiving.store', $purchase), [
            'tanggal_terima' => now()->format('Y-m-d H:i:s'),
            'items' => [['detail_id' => $detail->id, 'qty' => 1, 'kondisi' => 'BAIK']],
        ])->assertRedirect();

        $this->post(route('sales.store'), [
            'items' => [['produk_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'CASH', 'amount' => 5000000]],
        ])->assertRedirect();
        $this->post(route('expenses.store'), [
            'tanggal' => today()->toDateString(),
            'kategori_beban' => 'OPERASIONAL',
            'keterangan' => 'Biaya operasional toko',
            'nominal' => 1000000,
            'metode_bayar' => 'CASH',
        ])->assertRedirect();

        Pengeluaran::query()->create([
            'tanggal' => now(), 'kategori_beban' => 'PENYESUAIAN_KAS',
            'keterangan' => 'Adjustment lama', 'nominal' => 500000,
            'metode_bayar' => 'CASH', 'referensi_type' => 'MANUAL_KAS',
            'referensi_id' => 1, 'user_id' => $user->id,
        ]);
        $this->adjustCash($user, 'MASUK', 500000);
        $this->adjustCash($user, 'KELUAR', 500000);

        $this->assertSame(14000000.0, (float) KasMutasi::query()->latest('id')->value('saldo'));
        $this->assertProfitLoss(2000000, 1000000, 1000000);
        $this->actingAs($user)->get(route('reports.show', 'pendapatan'))
            ->assertOk()->assertDontSee('Adjustment lama');
        $this->actingAs($user)->get(route('reports.show', 'pengeluaran'))
            ->assertOk()->assertDontSee('Adjustment lama');
    }

    private function seedOpeningCash(User $user): void
    {
        app(CashLedgerService::class)->adjustment(
            10000000, 0, $user->id, 'OPENING_BALANCE', null, 'Saldo awal kas',
        );
    }

    private function adjustCash(User $user, string $direction, int $amount): void
    {
        $this->actingAs($user)->post(route('cash.movements.store'), [
            'jenis' => 'ADJUSTMENT',
            'arah' => $direction,
            'nominal' => $amount,
            'keterangan' => 'Koreksi saldo hasil opname',
        ])->assertRedirect();
    }

    private function assertProfitLoss(float $gross, float $expenses, float $net): void
    {
        $response = $this->get(route('reports.show', 'profit-loss'))->assertOk();
        $stats = collect($response->viewData('stats'))->keyBy('label');

        $this->assertSame('Rp '.number_format($gross, 0, ',', '.'), $stats->get('Laba Kotor')['value']);
        $this->assertSame('Rp '.number_format($expenses, 0, ',', '.'), $stats->get('Total Beban')['value']);
        $this->assertSame('Rp '.number_format($net, 0, ',', '.'), $stats->get('Laba Bersih')['value']);
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_'.uniqid()]);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function product(int $cost, int $price): Produk
    {
        $code = 'SKU-'.uniqid();
        $category = Kategori::create([
            'nama' => 'Kategori '.$code, 'jenis' => 'AKSESORIS', 'status' => 'AKTIF',
        ]);

        return Produk::create([
            'kode_produk' => $code, 'nama_produk' => 'Produk Tes', 'kategori_id' => $category->id,
            'satuan' => 'PCS', 'harga_beli' => $cost, 'harga_jual' => $price,
            'stok' => $cost > 0 ? 1 : 0, 'stok_minimum' => 0, 'status' => 'AKTIF',
        ]);
    }
}
