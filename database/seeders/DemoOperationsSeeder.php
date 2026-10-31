<?php

namespace Database\Seeders;

use App\Models\KasMutasi;
use App\Models\Laptop;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pendapatan;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Service;
use App\Models\ServiceApproval;
use App\Models\ServiceDetail;
use App\Models\ServiceProgress;
use App\Models\StokMutasi;
use App\Models\User;
use App\Services\CashLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $cashier = User::query()->where('email', 'kasir@example.com')->firstOrFail();
        $technician = User::query()->where('email', 'teknisi@example.com')->firstOrFail();
        $manager = User::query()->where('email', 'manager@example.com')->firstOrFail();
        $customers = $this->customers();

        DB::transaction(function () use ($cashier, $technician, $manager, $customers): void {
            $this->seedSales($cashier, $customers);
            $this->seedServices($cashier, $technician, $manager, $customers);
            $this->seedManualIncome($cashier);
            $this->seedExpenses($cashier);
        });

        $this->command?->info('Transaksi demo idempotent tersedia: 10 penjualan, 10 service, pendapatan, dan pengeluaran.');
    }

    /** @return list<Pelanggan> */
    private function customers(): array
    {
        $samples = [
            ['Rina Lestari','081211110001'], ['Andi Pratama','081211110002'], ['Budi Santoso','081211110003'],
            ['Siti Rahmawati','081211110004'], ['Dewi Anggraini','081211110005'], ['Rizky Maulana','081211110006'],
            ['Yusuf Firmansyah','081211110007'], ['Nadia Putri','081211110008'], ['Fajar Nugraha','081211110009'],
            ['Maya Kartika','081211110010'],
        ];
        $customers = [];
        $demoCustomerId = User::query()->where('email', 'pelanggan@example.com')->value('id');
        foreach ($samples as $index => [$name, $phone]) {
            $number = $index + 1;
            $linkedCustomer = $number === 3 && $demoCustomerId
                ? Pelanggan::query()->where('user_id', $demoCustomerId)->first()
                : null;
            $customers[] = $linkedCustomer ?? Pelanggan::firstOrCreate(['kode_pelanggan' => sprintf('SAMPLE-CUST-%02d', $number)], [
                'nama' => $name, 'email' => sprintf('pelanggan.%02d@example.test', $number), 'telepon' => $phone,
                'alamat' => 'Jakarta', 'status' => 'AKTIF',
            ]);
        }
        return $customers;
    }

    /** @param list<Pelanggan> $customers */
    private function seedSales(User $cashier, array $customers): void
    {
        $preferred = [
            'DEMO-LTP-001','DEMO-LTP-003','DEMO-LTP-005','DEMO-LTP-007','DEMO-LTP-009',
            'DEMO-ACC-001','DEMO-ACC-004','DEMO-ACC-009','DEMO-SPR-001','DEMO-SPR-004',
        ];
        $methods = ['CASH','TRANSFER','QRIS','CASH','TRANSFER','CASH','QRIS','TRANSFER','CASH','CASH'];
        foreach ($preferred as $index => $code) {
            $sequence = sprintf('%04d', $index + 1);
            $invoice = sprintf('DEMO-INV-%s-%04d', now()->format('Ym'), $index + 1);
            if (Penjualan::query()->where('no_invoice', 'like', 'DEMO-INV-%-'.$sequence)->exists()) continue;
            $product = Produk::query()->with('kategori')->where('kode_produk', $code)->firstOrFail();
            if ((int) $product->stok < 1) {
                $product = Produk::query()->with('kategori')->where('kategori_id', $product->kategori_id)->where('stok', '>', 0)->orderBy('id')->firstOrFail();
            }
            $laptop = $product->kategori?->jenis === 'LAPTOP'
                ? Laptop::query()->where('produk_id', $product->id)->whereIn('status', ['READY','DISPLAY'])->orderBy('id')->first()
                : null;
            if ($product->kategori?->jenis === 'LAPTOP' && ! $laptop) continue;
            $quantity = in_array($index, [6, 7], true) ? 2 : 1;
            if ((int) $product->stok < $quantity) $quantity = 1;
            $price = (float) ($laptop?->harga_jual ?: $product->harga_jual);
            $cost = (float) ($laptop?->hpp ?: $product->harga_beli);
            $total = round($price * $quantity, 2);
            $saleDate = today()->subDays($index);
            $sale = Penjualan::create([
                'no_invoice' => $invoice, 'tanggal' => $saleDate, 'pelanggan_id' => $customers[$index]->id,
                'jenis_penjualan' => $product->kategori?->jenis === 'LAPTOP' ? 'LAPTOP' : ($product->kategori?->jenis === 'AKSESORIS' ? 'AKSESORIS' : 'SPAREPART'),
                'subtotal' => $total, 'diskon' => 0, 'pajak_persen' => 0, 'pajak' => 0,
                'biaya_tambahan' => 0, 'total' => $total, 'dibayar' => $total, 'kembalian' => 0,
                'status' => 'SELESAI', 'user_id' => $cashier->id, 'keterangan' => 'Transaksi sampel untuk demonstrasi.',
            ]);
            PenjualanDetail::create([
                'penjualan_id' => $sale->id, 'produk_id' => $product->id, 'laptop_id' => $laptop?->id,
                'qty' => $quantity, 'harga' => $price, 'diskon' => 0, 'hpp' => $cost,
                'laba' => round(($price - $cost) * $quantity, 2), 'subtotal' => $total,
            ]);
            $product->decrement('stok', $quantity);
            if ($laptop) $laptop->forceFill(['status' => 'SOLD', 'penjualan_id' => $sale->id])->save();
            StokMutasi::create([
                'tanggal' => now(), 'produk_id' => $product->id, 'jenis_mutasi' => 'PENJUALAN',
                'referensi_type' => 'penjualan', 'referensi_id' => $sale->id,
                'qty_masuk' => 0, 'qty_keluar' => $quantity, 'stok_saldo' => $product->fresh()->stok,
                'hpp' => $cost, 'keterangan' => 'Penjualan sampel '.$invoice, 'user_id' => $cashier->id,
                'created_at' => now(),
            ]);
            $incomeType = match ($sale->jenis_penjualan) {
                'LAPTOP' => 'PENJUALAN_LAPTOP',
                'AKSESORIS' => 'PENJUALAN_AKSESORIS',
                default => 'LAINNYA',
            };
            $this->recordReceipt($sale->id, $invoice, $total, $methods[$index], $cashier, 'PENJUALAN', $incomeType, $saleDate);
        }
    }

    /** @param list<Pelanggan> $customers */
    private function seedServices(User $cashier, User $technician, User $manager, array $customers): void
    {
        $samples = [
            ['DITERIMA',0,0,'Laptop tidak bisa menyala.'],
            ['DIAGNOSA',0,0,'Baterai tidak mengisi daya.'],
            ['MENUNGGU_PERSETUJUAN',550000,0,'Keyboard perlu diganti; menunggu persetujuan pelanggan.'],
            ['MENUNGGU_SPAREPART',690000,0,'Estimasi disetujui; menunggu kedatangan SSD.'],
            ['DALAM_PERBAIKAN',425000,0,'Penggantian kipas dan pembersihan sedang dikerjakan.'],
            ['TESTING',350000,0,'Perbaikan port USB selesai, perangkat sedang diuji.'],
            ['SELESAI',475000,475000,'Perangkat selesai dan siap diambil.'],
            ['SUDAH_DIAMBIL',650000,650000,'Perangkat sudah diserahkan kepada pelanggan.'],
            ['BATAL',0,0,'Pelanggan membatalkan pemeriksaan.'],
            ['DIAGNOSA',0,0,'Laptop lambat, pemeriksaan awal berjalan.'],
        ];
        $progresses = ['DITERIMA'=>0,'DIAGNOSA'=>20,'MENUNGGU_PERSETUJUAN'=>30,'MENUNGGU_SPAREPART'=>45,'DALAM_PERBAIKAN'=>60,'TESTING'=>80,'SELESAI'=>100,'SUDAH_DIAMBIL'=>100,'BATAL'=>0];
        foreach ($samples as $index => [$status, $total, $paid, $note]) {
            $number = $index + 1;
            $serviceNumber = sprintf('DEMO-SRV-%s-%04d', now()->format('Ym'), $number);
            if (Service::query()->where('no_service', 'like', 'DEMO-SRV-%-'.sprintf('%04d', $number))->exists()) continue;
            $fee = match ($status) {
                'MENUNGGU_PERSETUJUAN' => 505000,
                'MENUNGGU_SPAREPART' => 645000,
                default => $total,
            };
            $serviceDate = now()->subDays($index);
            $service = Service::create([
                'no_service' => $serviceNumber, 'pelanggan_id' => $customers[$index]->id,
                'tanggal_masuk' => $serviceDate, 'tanggal_selesai' => in_array($status, ['SELESAI','SUDAH_DIAMBIL'], true) ? $serviceDate->copy()->addHours(5) : null,
                'jenis_perangkat' => 'Laptop', 'merek' => ['ASUS','Lenovo','Acer','HP','Dell'][$index % 5],
                'model' => ['Vivobook 14','ThinkPad E14','Aspire 5','HP 14s','Inspiron 14'][$index % 5],
                'serial_number' => sprintf('SAMPLE-SN-%04d', $number),
                'keluhan' => $note, 'diagnosa' => in_array($status, ['DIAGNOSA','MENUNGGU_PERSETUJUAN','MENUNGGU_SPAREPART','DALAM_PERBAIKAN','TESTING','SELESAI','SUDAH_DIAMBIL'], true) ? $note : null,
                'teknisi_id' => $technician->id, 'status' => $status,
                'jasa' => $fee, 'diskon' => 0, 'pajak_persen' => 0, 'pajak' => 0,
                'biaya_tambahan' => 0, 'total' => $total, 'dibayar' => $paid,
                'keterangan' => 'Data service sampel untuk demo.',
            ]);
            ServiceProgress::create([
                'service_id' => $service->id, 'status' => $status, 'progress_percent' => $progresses[$status],
                'catatan' => $note, 'updated_by' => $technician->id, 'created_at' => $serviceDate,
            ]);

            if ($status === 'MENUNGGU_PERSETUJUAN') {
                $approval = ServiceApproval::create([
                    'service_id' => $service->id, 'status_sebelum' => 'DIAGNOSA', 'progress_sebelum' => 20,
                    'jasa_sebelum' => 0, 'diskon_sebelum' => 0, 'pajak_persen_sebelum' => 0, 'pajak_sebelum' => 0,
                    'biaya_tambahan_sebelum' => 0, 'total_sebelum' => 0, 'estimasi' => $total, 'status' => 'PENDING',
                    'requested_by' => $cashier->id, 'catatan' => $note,
                ]);
                $part = Produk::query()->where('kode_produk', 'DEMO-SPR-017')->first();
                if ($part) ServiceDetail::create([
                    'service_id' => $service->id, 'approval_id' => $approval->id, 'produk_id' => $part->id,
                    'qty' => 1, 'harga' => $part->harga_jual, 'hpp' => $part->harga_beli, 'subtotal' => $part->harga_jual,
                    'stock_deducted_at' => null,
                ]);
            }
            if ($status === 'MENUNGGU_SPAREPART') {
                $approval = ServiceApproval::create([
                    'service_id' => $service->id, 'status_sebelum' => 'DIAGNOSA', 'progress_sebelum' => 20,
                    'jasa_sebelum' => 0, 'diskon_sebelum' => 0, 'pajak_persen_sebelum' => 0, 'pajak_sebelum' => 0,
                    'biaya_tambahan_sebelum' => 0, 'total_sebelum' => 0, 'estimasi' => $total, 'nominal' => $total,
                    'status' => 'APPROVED', 'requested_by' => $cashier->id, 'approved_by' => $manager->id,
                    'approved_at' => $serviceDate, 'catatan' => $note,
                ]);
                $part = Produk::query()->where('kode_produk', 'DEMO-SPR-017')->first();
                if ($part) ServiceDetail::create([
                    'service_id' => $service->id, 'approval_id' => $approval->id, 'produk_id' => $part->id,
                    'qty' => 1, 'harga' => $part->harga_jual, 'hpp' => $part->harga_beli, 'subtotal' => $part->harga_jual,
                    'stock_deducted_at' => null,
                ]);
            }
            if ($paid > 0) {
                $method = $status === 'SELESAI' ? 'CASH' : 'TRANSFER';
                $this->recordReceipt($service->id, $serviceNumber, $paid, $method, $cashier, 'SERVICE', 'SERVICE', $serviceDate);
            }
        }
    }

    private function recordReceipt(int $referenceId, string $source, float $amount, string $method, User $user, string $referenceType, string $incomeType, mixed $date): void
    {
        $payment = Pembayaran::create([
            'tanggal' => $date, 'referensi_type' => $referenceType, 'referensi_id' => $referenceId,
            'metode' => $method, 'nominal' => $amount, 'status' => 'LUNAS',
            'keterangan' => 'Pembayaran sampel '.$source, 'user_id' => $user->id, 'created_at' => $date,
        ]);
        Pendapatan::create([
            'tanggal' => $date, 'kode_pendapatan' => 'SAMPLE-INC-'.$payment->id,
            'jenis_pendapatan' => $incomeType, 'sumber' => $source,
            'referensi_type' => $referenceType, 'referensi_id' => $referenceId,
            'nominal' => $amount, 'keterangan' => 'Penerimaan sampel '.$source, 'user_id' => $user->id,
        ]);
        if ($method === 'CASH') app(CashLedgerService::class)->receipt($amount, $user->id, strtolower($referenceType), $referenceId, 'Penerimaan sampel '.$source);
    }

    private function seedManualIncome(User $cashier): void
    {
        $samples = [
            ['SAMPLE-INC-PRINT','PRINTING','Cetak dokumen pelanggan','100000','CASH'],
            ['SAMPLE-INC-RENT','SEWA','Sewa proyektor harian','350000','TRANSFER'],
            ['SAMPLE-INC-OTHER','LAINNYA','Jasa instalasi software','150000','CASH'],
        ];
        foreach ($samples as [$code,$type,$source,$amount,$method]) {
            $income = Pendapatan::firstOrCreate(['kode_pendapatan' => $code], [
                'tanggal' => today(), 'jenis_pendapatan' => $type, 'sumber' => $source,
                'nominal' => $amount, 'keterangan' => 'Catatan sampel keuangan.', 'user_id' => $cashier->id,
            ]);
            if (Pembayaran::query()->where('referensi_type', 'PENDAPATAN')->where('referensi_id', $income->id)->exists()) continue;
            Pembayaran::create([
                'tanggal' => now(), 'referensi_type' => 'PENDAPATAN', 'referensi_id' => $income->id,
                'metode' => $method, 'nominal' => $amount, 'status' => 'LUNAS',
                'keterangan' => 'Penerimaan '.$code, 'user_id' => $cashier->id, 'created_at' => now(),
            ]);
            if ($method === 'CASH') app(CashLedgerService::class)->receipt((float) $amount, $cashier->id, 'pendapatan', $income->id, 'Pendapatan sampel '.$code);
        }
    }

    private function seedExpenses(User $cashier): void
    {
        $samples = [
            ['SAMPLE-EXP-01','LISTRIK','Tagihan listrik toko','1250000','CASH'],
            ['SAMPLE-EXP-02','INTERNET','Langganan internet toko','650000','TRANSFER'],
            ['SAMPLE-EXP-03','OPERASIONAL','Perlengkapan kebersihan toko','180000','CASH'],
        ];
        foreach ($samples as [$code,$category,$description,$amount,$method]) {
            $expense = \App\Models\Pengeluaran::firstOrCreate(['kode_beban' => $code], [
                'tanggal' => today(), 'kategori_beban' => $category, 'keterangan' => $description,
                'nominal' => $amount, 'metode_bayar' => $method, 'user_id' => $cashier->id,
            ]);
            if ($method === 'CASH' && ! KasMutasi::query()->where('referensi_type', 'pengeluaran')->where('referensi_id', $expense->id)->exists()) {
                app(CashLedgerService::class)->disbursement((float) $amount, $cashier->id, 'pengeluaran', $expense->id, 'Pengeluaran sampel '.$code);
            }
        }
    }
}
