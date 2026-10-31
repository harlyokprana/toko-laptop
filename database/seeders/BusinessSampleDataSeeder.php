<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Laptop;
use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'LAPTOP' => Kategori::firstOrCreate(['nama' => 'Laptop Demo'], ['jenis' => 'LAPTOP', 'deskripsi' => 'Laptop untuk demonstrasi POS', 'status' => 'AKTIF']),
            'AKSESORIS' => Kategori::firstOrCreate(['nama' => 'Aksesori Demo'], ['jenis' => 'AKSESORIS', 'deskripsi' => 'Aksesori komputer dan laptop', 'status' => 'AKTIF']),
            'SPAREPART' => Kategori::firstOrCreate(['nama' => 'Sparepart Demo'], ['jenis' => 'SPAREPART', 'deskripsi' => 'Komponen pengganti dan servis laptop', 'status' => 'AKTIF']),
        ];
        $supplier = Supplier::firstOrCreate(['kode_supplier' => 'SUP-DEMO-01'], [
            'nama' => 'PT Sumber Laptop Nusantara', 'kontak' => 'Tim Penjualan', 'telepon' => '0215550101',
            'email' => 'sales@sumberlaptop.example', 'alamat' => 'Jakarta', 'status' => 'AKTIF',
        ]);

        $catalog = $this->catalog();
        DB::transaction(function () use ($catalog, $categories, $supplier): void {
            foreach ($catalog as $item) {
                [$code, $name, $kind, $brand, $model, $description, $cost, $price] = $item;
                $product = Produk::query()->where('kode_produk', $code)->first();
                $new = ! $product;
                if ($new) {
                    $product = Produk::create([
                        'kode_produk' => $code, 'nama_produk' => $name, 'kategori_id' => $categories[$kind]->id,
                        'merek' => $brand, 'tipe' => $model, 'satuan' => $kind === 'LAPTOP' ? 'UNIT' : 'PCS',
                        'harga_beli' => $cost, 'harga_jual' => $price, 'stok' => 10,
                        'stok_minimum' => 2, 'status' => 'AKTIF',
                    ]);
                }
                $hasHistory = ! $new && (
                    $product->penjualanDetail()->exists() || $product->pembelianDetail()->exists()
                    || $product->serviceDetail()->exists() || $product->stokMutasi()->exists()
                );
                $stockBefore = $new ? 0 : (int) $product->stok;
                if (! $new && ! $hasHistory && $stockBefore < 10) {
                    $product->forceFill(['stok' => 10])->save();
                }

                if (! $hasHistory && $stockBefore < 10) {
                    StokMutasi::create([
                        'tanggal' => now(), 'produk_id' => $product->id, 'jenis_mutasi' => 'ADJUSTMENT',
                        'referensi_type' => 'SAMPLE_OPENING', 'referensi_id' => null,
                        'qty_masuk' => 10 - $stockBefore, 'qty_keluar' => 0, 'stok_saldo' => 10,
                        'hpp' => $cost, 'keterangan' => 'Saldo awal sampel 10 unit.',
                        'user_id' => null, 'created_at' => now(),
                    ]);
                }

                if ($kind === 'LAPTOP' && ! $hasHistory) {
                    $readyCount = Laptop::query()->where('produk_id', $product->id)->whereIn('status', ['READY', 'DISPLAY'])->count();
                    $unitNumber = 1;
                    while ($readyCount < 10) {
                        $serial = sprintf('SN-%s-%02d', $code, $unitNumber);
                        $unitCode = 'UNIT-'.$code.'-'.sprintf('%02d', $unitNumber);
                        $unit = Laptop::query()->where('produk_id', $product->id)->where('serial_number', $serial)->first();
                        if (! $unit) {
                            $unit = Laptop::create([
                                'produk_id' => $product->id, 'supplier_id' => $supplier->id,
                                'kode_laptop' => $unitCode, 'serial_number' => $serial, 'tanggal_perolehan' => '2026-10-01',
                                'merek' => $brand, 'model_seri' => $model, 'spesifikasi' => $description,
                                'hpp' => $cost, 'harga_jual' => $price, 'biaya_tambahan' => 0,
                                'status' => 'READY', 'keterangan' => 'Unit sampel demo.',
                            ]);
                        }
                        if (in_array($unit->status, ['READY', 'DISPLAY'], true)) $readyCount++;
                        $unitNumber++;
                    }
                }
            }
        });

        $this->command?->info('Katalog demo tersedia: 50 SKU (12 laptop, 20 aksesori, 18 sparepart), stok awal 10 untuk data baru tanpa riwayat transaksi.');
    }

    /** @return list<array{string,string,string,string,string,string,int,int}> */
    private function catalog(): array
    {
        return [
            ['DEMO-LTP-001','Lenovo ThinkPad E14 Gen 5','LAPTOP','Lenovo','ThinkPad E14 Gen 5','Core i5-1335U, RAM 16GB, SSD 512GB',10500000,12500000],
            ['DEMO-LTP-002','Lenovo IdeaPad Slim 3','LAPTOP','Lenovo','IdeaPad Slim 3 14IRH8','Core i5-13420H, RAM 8GB, SSD 512GB',7600000,8990000],
            ['DEMO-LTP-003','ASUS Vivobook 14','LAPTOP','ASUS','Vivobook 14 A1405ZA','Core i5-1235U, RAM 8GB, SSD 512GB',8200000,9690000],
            ['DEMO-LTP-004','ASUS TUF Gaming A15','LAPTOP','ASUS','TUF Gaming A15 FA507N','Ryzen 7 7735HS, RAM 16GB, RTX 4050',15800000,17990000],
            ['DEMO-LTP-005','Acer Aspire 5','LAPTOP','Acer','Aspire 5 A514-56M','Core i5-1335U, RAM 8GB, SSD 512GB',8500000,9990000],
            ['DEMO-LTP-006','Acer Nitro V 15','LAPTOP','Acer','Nitro V 15 ANV15-51','Core i5-13420H, RAM 16GB, RTX 4050',14200000,16490000],
            ['DEMO-LTP-007','HP 14s','LAPTOP','HP','14s-dq','Core i3-1215U, RAM 8GB, SSD 512GB',6200000,7490000],
            ['DEMO-LTP-008','HP Pavilion x360','LAPTOP','HP','Pavilion x360 14-ek','Core i5-1235U, RAM 8GB, SSD 512GB touchscreen',10500000,12490000],
            ['DEMO-LTP-009','Dell Inspiron 14','LAPTOP','Dell','Inspiron 14 5430','Core i5-1335U, RAM 16GB, SSD 512GB',11200000,13290000],
            ['DEMO-LTP-010','MacBook Air 13 M2','LAPTOP','Apple','MacBook Air 13 M2','Apple M2, RAM 8GB, SSD 256GB',14500000,16499000],
            ['DEMO-LTP-011','ASUS ExpertBook B1','LAPTOP','ASUS','ExpertBook B1402CVA','Core i5-1335U, RAM 8GB, SSD 512GB',9200000,10990000],
            ['DEMO-LTP-012','MSI Modern 14','LAPTOP','MSI','Modern 14 C13M','Core i7-1355U, RAM 16GB, SSD 512GB',11900000,13990000],
            ['DEMO-ACC-001','Logitech M331 Silent Plus','AKSESORIS','Logitech','M331','Mouse wireless silent',185000,249000],
            ['DEMO-ACC-002','Logitech K120 USB Keyboard','AKSESORIS','Logitech','K120','Keyboard USB full-size',125000,169000],
            ['DEMO-ACC-003','Targus Laptop Sleeve 14 inch','AKSESORIS','Targus','CityLite Sleeve','Sleeve laptop 14 inci',180000,259000],
            ['DEMO-ACC-004','UGREEN USB-C Hub 6-in-1','AKSESORIS','UGREEN','CM512','Hub USB-C HDMI dan USB-A',425000,549000],
            ['DEMO-ACC-005','Baseus USB-C Charger 65W','AKSESORIS','Baseus','GaN5 Pro 65W','Charger USB-C PD 65W',390000,525000],
            ['DEMO-ACC-006','NYK Nemesis Gaming Mouse','AKSESORIS','NYK','A90','Mouse gaming RGB',155000,219000],
            ['DEMO-ACC-007','Fantech Office Keyboard','AKSESORIS','Fantech','WK896','Keyboard wireless',220000,299000],
            ['DEMO-ACC-008','Vention HDMI Cable 2m','AKSESORIS','Vention','AANBI','Kabel HDMI 2 meter',65000,95000],
            ['DEMO-ACC-009','Sandisk Ultra Flair 64GB','AKSESORIS','SanDisk','Ultra Flair','USB flash drive 64GB',85000,119000],
            ['DEMO-ACC-010','TP-Link Archer T2U Nano','AKSESORIS','TP-Link','Archer T2U Nano','USB Wi-Fi adapter AC600',145000,199000],
            ['DEMO-ACC-011','Orico Laptop Stand Aluminium','AKSESORIS','ORICO','LSA1','Stand laptop aluminium',210000,289000],
            ['DEMO-ACC-012','Logitech C270 HD Webcam','AKSESORIS','Logitech','C270','Webcam HD 720p',285000,379000],
            ['DEMO-ACC-013','JBL Tune 520BT Headset','AKSESORIS','JBL','Tune 520BT','Headphone bluetooth',620000,799000],
            ['DEMO-ACC-014','Vention USB-C to HDMI Adapter','AKSESORIS','Vention','TDAHB','Adapter USB-C HDMI 4K',135000,189000],
            ['DEMO-ACC-015','Robot RT-G1 Laptop Cooling Pad','AKSESORIS','Robot','RT-G1','Cooling pad laptop',175000,239000],
            ['DEMO-ACC-016','Bavin Laptop Backpack 15.6 inch','AKSESORIS','Bavin','BAV-150','Tas laptop 15,6 inci',245000,329000],
            ['DEMO-ACC-017','Vention CAT6 Ethernet Cable 3m','AKSESORIS','Vention','IBEBG','Kabel LAN CAT6 3 meter',35000,59000],
            ['DEMO-ACC-018','Prolink 10000mAh Power Bank','AKSESORIS','Prolink','PPB1001','Power bank USB-C 10000mAh',235000,319000],
            ['DEMO-ACC-019','Logitech R500s Presenter','AKSESORIS','Logitech','R500s','Wireless presentation remote',480000,629000],
            ['DEMO-ACC-020','Sandisk Portable SSD 1TB','AKSESORIS','SanDisk','Extreme Portable','SSD eksternal USB-C 1TB',1420000,1699000],
            ['DEMO-SPR-001','Kingston NV2 NVMe SSD 500GB','SPAREPART','Kingston','SNV2S/500G','SSD NVMe M.2 500GB',625000,799000],
            ['DEMO-SPR-002','Kingston Fury DDR4 8GB','SPAREPART','Kingston','KF432S20IB/8','RAM DDR4 SODIMM 8GB',295000,389000],
            ['DEMO-SPR-003','Samsung 990 EVO NVMe 1TB','SPAREPART','Samsung','MZ-V9E1T0','SSD NVMe M.2 1TB',1180000,1450000],
            ['DEMO-SPR-004','Crucial DDR4 SODIMM 16GB','SPAREPART','Crucial','CT16G4SFRA32A','RAM DDR4 SODIMM 16GB',520000,679000],
            ['DEMO-SPR-005','ASUS VivoBook 14 Cooling Fan','SPAREPART','ASUS','FAN-X1404','Kipas pendingin VivoBook 14',285000,395000],
            ['DEMO-SPR-006','Laptop Battery HP HT03XL','SPAREPART','HP','HT03XL','Baterai kompatibel HP 14s',475000,625000],
            ['DEMO-SPR-007','ASUS VivoBook 14 Keyboard','SPAREPART','ASUS','KB-X1404','Keyboard replacement VivoBook',385000,525000],
            ['DEMO-SPR-008','LCD Panel 14 inch FHD IPS','SPAREPART','Generic','LCD-14-FHD','Panel LCD 14 inci FHD IPS',920000,1190000],
            ['DEMO-SPR-009','Laptop DC Power Jack','SPAREPART','Generic','DCJ-5525','Konektor DC jack laptop',75000,135000],
            ['DEMO-SPR-010','Thermal Paste Arctic MX-4','SPAREPART','Arctic','MX-4-4G','Thermal paste 4 gram',65000,105000],
            ['DEMO-SPR-011','CMOS Battery CR2032 Cable','SPAREPART','Generic','CMOS-2032','Baterai CMOS berkabel',25000,55000],
            ['DEMO-SPR-012','Laptop Hinge Pair 14 inch','SPAREPART','Generic','HINGE-14','Sepasang engsel laptop 14 inci',145000,245000],
            ['DEMO-SPR-013','USB-C Charging Port Board','SPAREPART','Generic','USB-C-PORT','Board port pengisian USB-C',165000,285000],
            ['DEMO-SPR-014','Laptop Speaker Pair','SPAREPART','Generic','SPK-PAIR','Speaker internal kiri dan kanan',95000,175000],
            ['DEMO-SPR-015','Laptop Touchpad Module','SPAREPART','Generic','TPAD-14','Modul touchpad laptop',185000,295000],
            ['DEMO-SPR-016','Laptop LCD eDP Cable','SPAREPART','Generic','EDP-30P','Kabel fleksibel LCD eDP 30-pin',115000,195000],
            ['DEMO-SPR-017','Laptop Rubber Foot Set','SPAREPART','Generic','FOOT-SET','Set karet kaki laptop',18000,45000],
            ['DEMO-SPR-018','Laptop Fan Cleaning Kit','SPAREPART','Generic','CLEAN-KIT','Brush dan blower pembersih fan',45000,85000],
        ];
    }
}
