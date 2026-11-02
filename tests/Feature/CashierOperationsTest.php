<?php

namespace Tests\Feature;

use App\Models\KasirShift;
use App\Models\KasMutasi;
use App\Models\Kategori;
use App\Models\Laptop;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pendapatan;
use App\Models\Penjualan;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceApproval;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_must_open_shift_and_sale_posts_stock_payment_income_and_cash(): void
    {
        $cashier = $this->cashier();
        $product = $this->product('SKU-001', 3, 'AKSESORIS');
        $this->actingAs($cashier)->get(route('sales.create'))->assertOk();

        $salePayload = [
            'items' => [['produk_id' => $product->id, 'qty' => 1]],
            'metode' => 'CASH', 'dibayar' => 75000, 'diskon' => 0,
        ];
        $this->actingAs($cashier)->from(route('sales.create'))->post(route('sales.store'), $salePayload)
            ->assertSessionHasErrors('shift');
        $this->assertSame(0, Penjualan::count());

        $this->post(route('cash.shifts.open'), ['opening_cash' => 100000])->assertRedirect();
        $this->get(route('cash.shifts.index'))->assertOk()->assertSee('Tutup Shift');
        $this->post(route('sales.store'), $salePayload)->assertRedirect();

        $sale = Penjualan::firstOrFail();
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 2]);
        $this->assertDatabaseHas('pembayaran', ['referensi_type' => 'PENJUALAN', 'referensi_id' => $sale->id, 'nominal' => 50000]);
        $this->assertDatabaseHas('pendapatan', ['referensi_type' => 'PENJUALAN', 'referensi_id' => $sale->id, 'nominal' => 50000]);
        $this->assertDatabaseHas('kas_mutasi', ['referensi_type' => 'penjualan', 'referensi_id' => $sale->id, 'debit' => 50000]);
        $this->assertDatabaseHas('stok_mutasi', ['referensi_type' => 'penjualan', 'referensi_id' => $sale->id, 'qty_keluar' => 1]);

        $shift = KasirShift::where('user_id', $cashier->id)->where('status', 'OPEN')->firstOrFail();
        $this->post(route('cash.shifts.close', $shift), ['closing_cash' => 150000])
            ->assertRedirect();
        $this->assertDatabaseHas('kasir_shifts', ['id' => $shift->id, 'status' => 'CLOSED', 'expected_cash' => 150000, 'cash_difference' => 0]);
    }

    public function test_cashier_can_receive_a_service_with_part_and_payment(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('cash.shifts.open'), ['opening_cash' => 200000])->assertRedirect();
        [$customer, $customerAccount] = $this->customerWithAccount('CUS-001');
        $technician = $this->technician();
        $part = $this->product('PART-001', 4, 'SPAREPART', 25000);
        $this->get(route('service.create'))->assertOk()->assertSee('Terima Service');

        $this->post(route('service.store'), [
            'pelanggan_id' => $customer->id,
            'jenis_perangkat' => 'Laptop', 'merek' => 'Lenovo', 'model' => 'ThinkPad T14',
            'serial_number' => 'SN-CUST-1', 'keluhan' => 'Tidak mengisi daya',
            'teknisi_id' => $technician->id,
        ])->assertRedirect(route('service.print', [Service::firstOrFail(), 'intake']));

        $service = Service::firstOrFail();
        $this->get(route('service.print', [$service, 'intake']))
            ->assertOk()
            ->assertSee('Tanda Terima Service')
            ->assertSee('SN-CUST-1')
            ->assertSee('Tidak mengisi daya');
        $this->assertSame(0.0, (float) $service->total);
        $this->post(route('service.approvals.store', $service), [
            'jasa' => 50000, 'diskon_tipe' => 'NOMINAL', 'catatan' => 'Belum ada diagnosis.',
        ])->assertForbidden();
        $this->actingAs($technician)->post(route('service.progress.store', $service), [
            'status' => 'DIAGNOSA',
            'diagnosa' => 'Konektor pengisian daya rusak.',
            'catatan' => 'Pemeriksaan awal selesai.',
        ])->assertRedirect();
        $this->actingAs($cashier);
        $this->post(route('service.approvals.store', $service), [
            'jasa' => 50000, 'diskon_tipe' => 'NOMINAL', 'diskon_nilai' => 0,
            'pajak_aktif' => 0, 'pajak_persen' => 11, 'parts' => [['produk_id' => $part->id, 'qty' => 1]],
            'catatan' => 'Ganti konektor pengisian daya',
        ])->assertRedirect();
        $approval = $service->approvals()->firstOrFail();
        $this->assertDatabaseHas('produk', ['id' => $part->id, 'stok' => 4]);
        $this->post(route('service.approvals.decision', [$service, $approval]), ['decision' => 'APPROVED'])
            ->assertSessionHasErrors('catatan');
        $this->assertDatabaseHas('service_approvals', ['id' => $approval->id, 'status' => 'PENDING']);
        $this->actingAs($customerAccount)
            ->post(route('service.approvals.decision', [$service, $approval]), ['decision' => 'APPROVED'])
            ->assertRedirect();
        $this->assertDatabaseHas('service_approvals', [
            'id' => $approval->id,
            'approved_by' => $customerAccount->id,
        ]);
        $this->actingAs($cashier);
        $service->refresh();
        $this->assertSame(75000.0, (float) $service->total);
        $this->post(route('service.payments.store', $service), ['nominal' => 75000, 'metode' => 'CASH'])->assertRedirect();
        $service->refresh();
        $this->assertSame(75000.0, (float) $service->total);
        $this->assertSame(75000.0, (float) $service->dibayar);
        $this->assertDatabaseHas('service_progress', ['service_id' => $service->id, 'status' => 'DITERIMA']);
        $this->assertDatabaseHas('produk', ['id' => $part->id, 'stok' => 3]);
        $this->assertDatabaseHas('pendapatan', ['referensi_type' => 'SERVICE', 'referensi_id' => $service->id, 'nominal' => 75000]);
    }

    public function test_completed_service_print_shows_paid_amount_and_remaining_balance(): void
    {
        $cashier = $this->cashier();
        [$customer] = $this->customerWithAccount('CUS-PRINT');
        $technician = $this->technician();
        $service = Service::create([
            'no_service' => 'SRV-PRINT-001',
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now()->subDay(),
            'tanggal_selesai' => now(),
            'jenis_perangkat' => 'Laptop',
            'merek' => 'Lenovo',
            'model' => 'ThinkPad',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
            'status' => 'SELESAI',
            'jasa' => 100000,
            'total' => 150000,
            'dibayar' => 50000,
        ]);
        Pembayaran::create([
            'tanggal' => now(),
            'referensi_type' => 'SERVICE',
            'referensi_id' => $service->id,
            'metode' => 'TRANSFER',
            'nominal' => 50000,
            'status' => 'LUNAS',
            'user_id' => $cashier->id,
            'created_at' => now(),
        ]);

        $this->actingAs($cashier)->get(route('service.print', [$service, 'final']))
            ->assertOk()
            ->assertSee('Tagihan Service')
            ->assertSee('Rp 150.000')
            ->assertSee('Rp 50.000')
            ->assertSee('Rp 100.000')
            ->assertSee('TRANSFER');

        $this->actingAs($technician)
            ->get(route('service.print', [$service, 'final']))
            ->assertForbidden();
    }

    public function test_cashier_can_record_cash_income_and_non_cash_income_without_changing_cash_balance(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('cash.shifts.open'), ['opening_cash' => 0])->assertRedirect();
        $this->get(route('income.create'))->assertOk()->assertSee('Setor / Catat Pendapatan');

        $this->post(route('income.store'), [
            'jenis_pendapatan' => 'PRINTING', 'sumber' => 'Cetak dokumen', 'nominal' => 10000,
            'metode' => 'CASH', 'keterangan' => 'Tunai',
        ])->assertRedirect(route('module.income'));
        $this->post(route('income.store'), [
            'jenis_pendapatan' => 'SEWA', 'sumber' => 'Sewa adaptor', 'nominal' => 20000,
            'metode' => 'TRANSFER',
        ])->assertRedirect(route('module.income'));

        $this->assertSame(2, Pendapatan::count());
        $this->assertDatabaseHas('kas_mutasi', ['jenis' => 'PENDAPATAN', 'debit' => 10000]);
        $this->assertDatabaseHas('pembayaran', ['referensi_type' => 'PENDAPATAN', 'metode' => 'TRANSFER', 'nominal' => 20000]);
        $this->assertSame(1, KasMutasi::count());
    }

    public function test_service_balance_can_be_collected_after_repair(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('cash.shifts.open'), ['opening_cash' => 10000])->assertRedirect();
        [$customer, $customerAccount] = $this->customerWithAccount('CUS-SRV-02');
        $technician = $this->technician();

        $this->post(route('service.store'), [
            'pelanggan_id' => $customer->id,
            'jenis_perangkat' => 'Laptop', 'keluhan' => 'Layar tidak tampil',
            'teknisi_id' => $technician->id,
        ])->assertRedirect();

        $service = Service::firstOrFail();
        $this->actingAs($technician)->post(route('service.progress.store', $service), [
            'status' => 'DIAGNOSA',
            'diagnosa' => 'Kabel fleksibel layar perlu diperiksa.',
            'catatan' => 'Pemeriksaan awal selesai.',
        ])->assertRedirect();
        $this->actingAs($cashier);
        $this->post(route('service.approvals.store', $service), [
            'jasa' => 80000, 'diskon_tipe' => 'NOMINAL', 'diskon_nilai' => 0,
            'pajak_aktif' => 0, 'pajak_persen' => 11, 'catatan' => 'Pemeriksaan layar dan perbaikan.',
        ])->assertRedirect();
        $approval = $service->approvals()->firstOrFail();
        $this->actingAs($customerAccount)
            ->post(route('service.approvals.decision', [$service, $approval]), ['decision' => 'APPROVED'])
            ->assertRedirect();
        $this->actingAs($cashier);
        $service->refresh();
        $this->get(route('service.show', $service))->assertOk()->assertSee('Simpan Pembayaran');
        $this->assertDatabaseHas('pembayaran', ['referensi_type' => 'SERVICE', 'referensi_id' => $service->id, 'metode' => 'PIUTANG', 'status' => 'PENDING', 'nominal' => 80000]);

        $this->post(route('service.payments.store', $service), ['nominal' => 30000, 'metode' => 'CASH'])->assertRedirect();
        $service->refresh();
        $this->assertSame(30000.0, (float) $service->dibayar);
        $this->assertDatabaseHas('pembayaran', ['referensi_type' => 'SERVICE', 'referensi_id' => $service->id, 'metode' => 'PIUTANG', 'status' => 'PENDING', 'nominal' => 50000]);

        $this->post(route('service.payments.store', $service), ['nominal' => 50000, 'metode' => 'TRANSFER'])->assertRedirect();
        $this->assertDatabaseHas('service', ['id' => $service->id, 'dibayar' => 80000]);
    }

    public function test_cashier_records_paid_service_handover_and_technician_cannot_do_it(): void
    {
        $cashier = $this->cashier();
        $technician = $this->technician();
        $customer = Pelanggan::create(['kode_pelanggan' => 'CUS-HANDOVER', 'nama' => 'Pelanggan Service', 'status' => 'AKTIF']);
        $service = Service::create([
            'no_service' => 'SRV-HANDOVER-001',
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
            'status' => 'SELESAI',
            'total' => 80000,
            'dibayar' => 80000,
        ]);

        $this->actingAs($technician)
            ->post(route('service.handover.store', $service), ['nama_penerima' => 'Pelanggan Service'])
            ->assertForbidden();
        $this->post(route('service.progress.store', $service), [
            'status' => 'SUDAH_DIAMBIL',
            'catatan' => 'Pelanggan mengambil perangkat.',
        ])->assertNotFound();
        $this->actingAs($cashier)
            ->post(route('service.handover.store', $service), ['nama_penerima' => 'Pelanggan Service'])
            ->assertRedirect();

        $this->assertDatabaseHas('service', ['id' => $service->id, 'status' => 'SUDAH_DIAMBIL']);
        $this->assertDatabaseHas('service_progress', [
            'service_id' => $service->id,
            'status' => 'SUDAH_DIAMBIL',
            'updated_by' => $cashier->id,
            'catatan' => 'Perangkat diserahkan kepada Pelanggan Service.',
        ]);
    }

    public function test_service_cannot_be_handed_over_before_completion_or_full_payment(): void
    {
        $cashier = $this->cashier();
        $customer = Pelanggan::create(['kode_pelanggan' => 'CUS-HANDOVER-2', 'nama' => 'Pelanggan Service', 'status' => 'AKTIF']);
        $technician = $this->technician();
        $service = Service::create([
            'no_service' => 'SRV-HANDOVER-002',
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
            'status' => 'DALAM_PERBAIKAN',
            'total' => 80000,
            'dibayar' => 30000,
        ]);

        $this->actingAs($cashier)
            ->post(route('service.handover.store', $service), ['nama_penerima' => 'Pelanggan Service'])
            ->assertSessionHasErrors('handover');
        $this->assertDatabaseHas('service', ['id' => $service->id, 'status' => 'DALAM_PERBAIKAN']);
        $this->assertDatabaseCount('service_progress', 0);

        $service->forceFill(['status' => 'SELESAI'])->save();
        $this->post(route('service.handover.store', $service), ['nama_penerima' => 'Pelanggan Service'])
            ->assertSessionHasErrors('handover');
        $this->assertDatabaseHas('service', ['id' => $service->id, 'status' => 'SELESAI']);
        $this->assertDatabaseCount('service_progress', 0);
    }

    public function test_cashier_must_record_customer_consent_when_deciding_an_estimate(): void
    {
        $cashier = $this->cashier();
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'CUS-APPROVAL',
            'nama' => 'Pelanggan Service',
            'status' => 'AKTIF',
        ]);
        $service = Service::create([
            'no_service' => 'SRV-APPROVAL-001',
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'diagnosa' => 'Konektor pengisian daya rusak.',
            'status' => 'MENUNGGU_PERSETUJUAN',
            'jasa' => 80000,
            'total' => 80000,
        ]);
        $approval = ServiceApproval::create([
            'service_id' => $service->id,
            'estimasi' => 80000,
            'status' => 'PENDING',
            'requested_by' => $cashier->id,
        ]);

        $this->actingAs($cashier)
            ->post(route('service.approvals.decision', [$service, $approval]), ['decision' => 'APPROVED'])
            ->assertSessionHasErrors('catatan');
        $this->assertDatabaseHas('service_approvals', ['id' => $approval->id, 'status' => 'PENDING']);

        $this->post(route('service.approvals.decision', [$service, $approval]), [
            'decision' => 'APPROVED',
            'catatan' => 'Pelanggan menyetujui di loket setelah rincian biaya dijelaskan.',
        ])->assertRedirect();
        $this->assertDatabaseHas('service_approvals', [
            'id' => $approval->id,
            'status' => 'APPROVED',
            'approved_by' => $cashier->id,
            'catatan' => 'Keputusan: Pelanggan menyetujui di loket setelah rincian biaya dijelaskan.',
        ]);
    }

    public function test_pos_can_sell_a_serialized_laptop_on_credit_and_collect_the_receivable_later(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->post(route('cash.shifts.open'), ['opening_cash' => 50000])->assertRedirect();
        $customer = Pelanggan::create(['kode_pelanggan' => 'CUS-REC-01', 'nama' => 'Pelanggan Piutang', 'status' => 'AKTIF']);
        $product = $this->product('LAP-001', 1, 'LAPTOP', 50000);
        $product->update([
            'jenis_produk' => 'LAPTOP',
            'merek' => 'Lenovo',
            'tipe' => 'ThinkPad',
            'atribut' => [
                'processor' => 'Intel Core i7',
                'ram' => '16 GB',
                'storage' => 'SSD NVMe 512 GB',
                'ukuran_layar' => '14"',
                'kondisi' => 'Baru',
            ],
        ]);
        $supplier = Supplier::create(['kode_supplier' => 'SUP-001', 'nama' => 'Supplier Test', 'status' => 'AKTIF']);
        $laptop = Laptop::create([
            'produk_id' => $product->id, 'supplier_id' => $supplier->id, 'sku' => 'UNIT-LAP-001',
            'serial_number' => 'SERIAL-LAP-001', 'merek' => 'Lenovo', 'model_seri' => 'ThinkPad',
            'kondisi' => 'Baru', 'hpp' => 30000, 'harga_jual' => 50000, 'status' => 'READY',
        ]);
        $this->get(route('sales.create'))
            ->assertOk()
            ->assertSee('SERIAL-LAP-001')
            ->assertSee('Intel Core i7')
            ->assertSee('RAM 16 GB')
            ->assertSee('Storage SSD NVMe 512 GB')
            ->assertSee('data-name="Produk LAP-001 · Lenovo · ThinkPad · Intel Core i7 · RAM 16 GB · Storage SSD NVMe 512 GB · Layar 14&quot; · Kondisi Baru"', false);

        $this->post(route('sales.store'), [
            'pelanggan_id' => $customer->id,
            'items' => [['produk_id' => $product->id, 'laptop_id' => $laptop->id, 'qty' => 1, 'diskon_persen' => 10]],
            'diskon_tipe' => 'PERSEN', 'diskon_transaksi' => 5,
            'pajak_aktif' => 1, 'pajak_persen' => 11, 'biaya_tambahan' => 1000,
            'metode' => 'PIUTANG', 'dibayar' => 0,
        ])->assertRedirect();

        $sale = Penjualan::firstOrFail();
        $response = $this->get(route('sales.show', $sale));
        $response
            ->assertOk()
            ->assertSee('Terima Pembayaran')
            ->assertSee('RAM 16 GB')
            ->assertSee('Storage SSD NVMe 512 GB');

        $document = new \DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);
        $invoice = (new \DOMXPath($document))->query('//*[@id="print-invoice"]')->item(0);

        $this->assertNotNull($invoice);
        $invoiceText = $invoice->textContent;
        $this->assertStringContainsString('LAP-001', $invoiceText);
        $this->assertStringNotContainsString('SERIAL-LAP-001', $invoiceText);
        $this->assertStringNotContainsString('Intel Core i7', $invoiceText);
        $this->assertStringNotContainsString('RAM 16 GB', $invoiceText);
        $this->assertStringNotContainsString('Storage SSD NVMe 512 GB', $invoiceText);
        $this->assertStringContainsString('Invoice', $invoiceText);
        $this->assertSame('PIUTANG', $sale->status);
        $this->assertSame(48452.5, (float) $sale->total);
        $this->assertDatabaseHas('laptop', ['id' => $laptop->id, 'status' => 'SOLD', 'penjualan_id' => $sale->id]);
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 0]);
        $this->assertDatabaseHas('pembayaran', ['referensi_id' => $sale->id, 'metode' => 'PIUTANG', 'status' => 'PENDING']);
        $this->assertSame(0, Pendapatan::count());

        $this->post(route('sales.payments.store', $sale), ['nominal' => 10000, 'metode' => 'CASH'])->assertRedirect();
        $sale->refresh();
        $this->assertSame('SEBAGIAN', $sale->status);
        $this->assertSame(10000.0, (float) $sale->dibayar);
        $this->assertDatabaseHas('kas_mutasi', ['referensi_id' => $sale->id, 'debit' => 10000]);

        $remaining = round((float) $sale->total - (float) $sale->dibayar, 2);
        $this->post(route('sales.payments.store', $sale), ['nominal' => $remaining, 'metode' => 'TRANSFER'])->assertRedirect();
        $this->assertDatabaseHas('penjualan', ['id' => $sale->id, 'status' => 'SELESAI', 'dibayar' => $sale->total]);
    }

    public function test_cashier_routes_require_the_matching_permissions(): void
    {
        $role = Role::create(['name' => 'LIMITED_CASHIER']);
        $permission = Permission::create(['name' => 'view_cash']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('sales.create'))->assertForbidden();
        $this->actingAs($user)->post(route('income.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('cash.shifts.index'))->assertOk();
    }

    public function test_sale_discount_requires_discount_permission(): void
    {
        $role = Role::create(['name' => 'NO_DISCOUNT']);
        $role->permissions()->attach(Permission::create(['name' => 'create_sales']));
        $user = User::factory()->create(['role_id' => $role->id]);
        $product = $this->product('SKU-NODISCOUNT', 1, 'AKSESORIS');

        $this->actingAs($user)->post(route('sales.store'), [
            'items' => [['produk_id' => $product->id, 'qty' => 1]],
            'diskon_transaksi' => 1000,
            'intent' => 'draft',
        ])->assertSessionHasErrors('diskon_transaksi');

        $this->assertSame(0, Penjualan::query()->count());
        $this->assertSame(1, (int) $product->fresh()->stok);
    }

    private function cashier(): User
    {
        $role = Role::create(['name' => 'KASIR']);
        foreach (['view_dashboard', 'view_sales', 'create_sales', 'collect_sales_payments', 'discount_sales', 'view_service', 'create_service', 'view_income', 'create_income', 'view_cash'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    /** @return array{Pelanggan, User} */
    private function customerWithAccount(string $code): array
    {
        $role = Role::firstOrCreate(['name' => 'PELANGGAN']);
        $account = User::factory()->create(['role_id' => $role->id]);
        $customer = Pelanggan::create([
            'user_id' => $account->id,
            'kode_pelanggan' => $code,
            'nama' => 'Pelanggan Service',
            'status' => 'AKTIF',
        ]);

        return [$customer, $account];
    }

    private function technician(): User
    {
        $role = Role::create(['name' => 'TEKNISI']);
        foreach (['view_service', 'create_service', 'update_service_progress'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function product(string $code, int $stock, string $categoryType, int $price = 50000): Produk
    {
        $category = Kategori::create(['nama' => 'Kategori '.$code, 'jenis' => $categoryType, 'status' => 'AKTIF']);

        return Produk::create([
            'kode_produk' => $code, 'nama_produk' => 'Produk '.$code, 'kategori_id' => $category->id,
            'satuan' => 'PCS', 'harga_beli' => (int) ($price * 0.6), 'harga_jual' => $price,
            'stok' => $stock, 'stok_minimum' => 1, 'status' => 'AKTIF',
        ]);
    }
}
