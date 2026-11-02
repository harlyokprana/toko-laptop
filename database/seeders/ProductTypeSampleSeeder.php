<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Laptop;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductTypeSampleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $categoryTypes = [
                'LAPTOP' => 'LAPTOP',
                'AKSESORIS' => 'AKSESORIS',
                'SPAREPART' => 'SPAREPART',
                'PC_RAKITAN' => 'LAINNYA',
            ];
            $categories = [];

            foreach ($categoryTypes as $type => $categoryType) {
                $categories[$type] = Kategori::query()->firstOrCreate(
                    ['nama' => 'Sampel '.str_replace('_', ' ', $type)],
                    [
                        'jenis' => $categoryType,
                        'deskripsi' => 'Kategori untuk data produk sampel.',
                        'status' => 'AKTIF',
                    ],
                );
            }

            $supplier = Supplier::query()->firstOrCreate(
                ['kode_supplier' => 'SUP-SAMPLE-01'],
                [
                    'nama' => 'Supplier Produk Sampel',
                    'kontak' => 'Tim Penjualan',
                    'telepon' => '0215550199',
                    'email' => 'supplier-sample@example.test',
                    'alamat' => 'Jakarta',
                    'status' => 'AKTIF',
                ],
            );

            foreach ($this->catalog() as $item) {
                [$code, $name, $type, $brand, $model, $cost, $price, $attributes] = $item;
                $product = Produk::query()->firstOrCreate(
                    ['kode_produk' => $code],
                    [
                        'nama_produk' => $name,
                        'jenis_produk' => $type,
                        'kategori_id' => $categories[$type]->id,
                        'merek' => $brand,
                        'tipe' => $model,
                        'atribut' => $attributes,
                        'satuan' => $type === 'LAPTOP' || $type === 'PC_RAKITAN' ? 'UNIT' : 'PCS',
                        'harga_beli' => $cost,
                        'harga_jual' => $price,
                        'stok' => 10,
                        'stok_minimum' => 2,
                        'status' => 'AKTIF',
                    ],
                );

                if (! $product->wasRecentlyCreated) {
                    continue;
                }

                StokMutasi::query()->create([
                    'tanggal' => now(),
                    'produk_id' => $product->id,
                    'jenis_mutasi' => 'ADJUSTMENT',
                    'referensi_type' => 'SAMPLE_OPENING',
                    'qty_masuk' => 10,
                    'qty_keluar' => 0,
                    'stok_saldo' => 10,
                    'hpp' => $cost,
                    'keterangan' => 'Saldo awal produk sampel 10 unit.',
                    'user_id' => null,
                    'created_at' => now(),
                ]);

                if ($type !== 'LAPTOP') {
                    continue;
                }

                for ($unitNumber = 1; $unitNumber <= 10; $unitNumber++) {
                    Laptop::query()->create([
                        'produk_id' => $product->id,
                        'supplier_id' => $supplier->id,
                        'sku' => sprintf('UNIT-%s-%02d', $code, $unitNumber),
                        'serial_number' => sprintf('SN-%s-%02d', $code, $unitNumber),
                        'tanggal_perolehan' => today(),
                        'merek' => $brand,
                        'model_seri' => $model,
                        'spesifikasi' => $attributes['spesifikasi_singkat'],
                        'hpp' => $cost,
                        'harga_jual' => $price,
                        'status' => 'READY',
                        'keterangan' => 'Unit laptop sampel.',
                    ]);
                }
            }
        });

        $this->command?->info('Produk sampel tersedia: 5 laptop, 5 aksesori, 5 sparepart, dan 5 PC rakitan; stok awal masing-masing 10.');
    }

    /**
     * @return list<array{string,string,string,string,string,int,int,array<string,string>}>
     */
    private function catalog(): array
    {
        return [
            ['SAMPLE-LAP-001', 'Lenovo ThinkPad E14 Gen 5', 'LAPTOP', 'Lenovo', 'ThinkPad E14 Gen 5', 10500000, 12500000, [
                'processor' => 'Intel Core i5-1335U', 'ram' => '16 GB', 'storage' => 'SSD NVMe 512 GB',
                'ukuran_layar' => '14 inch', 'kondisi' => 'Baru',
                'spesifikasi_singkat' => 'Layar WUXGA, Windows 11 Pro',
            ]],
            ['SAMPLE-LAP-002', 'ASUS Vivobook 14', 'LAPTOP', 'ASUS', 'Vivobook 14 A1405ZA', 8200000, 9690000, [
                'processor' => 'Intel Core i5-1235U', 'ram' => '8 GB', 'storage' => 'SSD NVMe 512 GB',
                'ukuran_layar' => '14 inch', 'kondisi' => 'Baru',
                'spesifikasi_singkat' => 'Layar FHD, Windows 11 Home',
            ]],
            ['SAMPLE-LAP-003', 'Acer Aspire 5', 'LAPTOP', 'Acer', 'Aspire 5 A514-56M', 8500000, 9990000, [
                'processor' => 'Intel Core i5-1335U', 'ram' => '8 GB', 'storage' => 'SSD NVMe 512 GB',
                'ukuran_layar' => '14 inch', 'kondisi' => 'Baru',
                'spesifikasi_singkat' => 'Layar IPS FHD, backlit keyboard',
            ]],
            ['SAMPLE-LAP-004', 'HP Pavilion x360', 'LAPTOP', 'HP', 'Pavilion x360 14-ek', 10500000, 12490000, [
                'processor' => 'Intel Core i5-1235U', 'ram' => '8 GB', 'storage' => 'SSD NVMe 512 GB',
                'ukuran_layar' => '14 inch touchscreen', 'kondisi' => 'Baru',
                'spesifikasi_singkat' => 'Convertible 2-in-1, layar FHD',
            ]],
            ['SAMPLE-LAP-005', 'MacBook Air 13 M2', 'LAPTOP', 'Apple', 'MacBook Air 13 M2', 14500000, 16499000, [
                'processor' => 'Apple M2', 'ram' => '8 GB', 'storage' => 'SSD 256 GB',
                'ukuran_layar' => '13.6 inch', 'kondisi' => 'Baru',
                'spesifikasi_singkat' => 'Liquid Retina, macOS',
            ]],
            ['SAMPLE-ACC-001', 'Logitech M331 Silent Plus', 'AKSESORIS', 'Logitech', 'M331', 185000, 249000, [
                'jenis_aksesori' => 'Mouse', 'spesifikasi_singkat' => 'Wireless silent, sensor optik',
            ]],
            ['SAMPLE-ACC-002', 'Logitech K120 USB Keyboard', 'AKSESORIS', 'Logitech', 'K120', 125000, 169000, [
                'jenis_aksesori' => 'Keyboard', 'spesifikasi_singkat' => 'Keyboard USB full-size',
            ]],
            ['SAMPLE-ACC-003', 'UGREEN USB-C Hub 6-in-1', 'AKSESORIS', 'UGREEN', 'CM512', 425000, 549000, [
                'jenis_aksesori' => 'USB Hub', 'spesifikasi_singkat' => 'HDMI 4K, USB-A, USB-C PD',
            ]],
            ['SAMPLE-ACC-004', 'Baseus USB-C Charger 65W', 'AKSESORIS', 'Baseus', 'GaN5 Pro 65W', 390000, 525000, [
                'jenis_aksesori' => 'Charger', 'spesifikasi_singkat' => 'GaN, USB-C PD 65W',
            ]],
            ['SAMPLE-ACC-005', 'SanDisk Ultra Flair 64GB', 'AKSESORIS', 'SanDisk', 'Ultra Flair', 85000, 119000, [
                'jenis_aksesori' => 'Flashdisk', 'spesifikasi_singkat' => 'USB 3.0, kapasitas 64 GB',
            ]],
            ['SAMPLE-SPR-001', 'Kingston NV2 NVMe SSD 500GB', 'SPAREPART', 'Kingston', 'SNV2S/500G', 625000, 799000, [
                'spesifikasi_singkat' => 'SSD M.2 2280, NVMe PCIe 4.0',
            ]],
            ['SAMPLE-SPR-002', 'Kingston Fury DDR4 SODIMM 8GB', 'SPAREPART', 'Kingston', 'KF432S20IB/8', 295000, 389000, [
                'spesifikasi_singkat' => 'RAM laptop DDR4 3200 MHz, 8 GB',
            ]],
            ['SAMPLE-SPR-003', 'Samsung 990 EVO NVMe 1TB', 'SPAREPART', 'Samsung', 'MZ-V9E1T0', 1180000, 1450000, [
                'spesifikasi_singkat' => 'SSD M.2, NVMe PCIe 4.0, 1 TB',
            ]],
            ['SAMPLE-SPR-004', 'ASUS VivoBook 14 Cooling Fan', 'SPAREPART', 'ASUS', 'FAN-X1404', 285000, 395000, [
                'spesifikasi_singkat' => 'Kipas pendingin pengganti VivoBook 14',
            ]],
            ['SAMPLE-SPR-005', 'Baterai Laptop HP HT03XL', 'SPAREPART', 'HP', 'HT03XL', 475000, 625000, [
                'spesifikasi_singkat' => 'Baterai pengganti kompatibel HP 14s',
            ]],
            ['SAMPLE-PCR-001', 'PC Rakitan Office Intel Core i3', 'PC_RAKITAN', 'Intel', 'Office i3 Gen 12', 5200000, 6290000, [
                'motherboard' => 'H610M', 'spesifikasi_singkat' => 'Core i3, RAM 8 GB, SSD 512 GB',
            ]],
            ['SAMPLE-PCR-002', 'PC Rakitan Office AMD Ryzen 5', 'PC_RAKITAN', 'AMD', 'Office Ryzen 5 5600G', 5800000, 6990000, [
                'motherboard' => 'A520M', 'spesifikasi_singkat' => 'Ryzen 5 5600G, RAM 16 GB, SSD 512 GB',
            ]],
            ['SAMPLE-PCR-003', 'PC Rakitan Gaming Intel Core i5', 'PC_RAKITAN', 'Intel', 'Gaming i5 Gen 12', 11200000, 12990000, [
                'motherboard' => 'B760M', 'spesifikasi_singkat' => 'Core i5, RAM 16 GB, RTX 4060, SSD 1 TB',
            ]],
            ['SAMPLE-PCR-004', 'PC Rakitan Gaming AMD Ryzen 5', 'PC_RAKITAN', 'AMD', 'Gaming Ryzen 5 5600', 9900000, 11500000, [
                'motherboard' => 'B550M', 'spesifikasi_singkat' => 'Ryzen 5, RAM 16 GB, RTX 4060, SSD 1 TB',
            ]],
            ['SAMPLE-PCR-005', 'PC Rakitan Creator Intel Core i7', 'PC_RAKITAN', 'Intel', 'Creator i7 Gen 13', 16800000, 19490000, [
                'motherboard' => 'B760M WiFi', 'spesifikasi_singkat' => 'Core i7, RAM 32 GB, RTX 4060, SSD 1 TB',
            ]],
        ];
    }
}
