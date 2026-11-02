<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_report_lists_all_products_and_summarizes_stock_value(): void
    {
        $role = Role::create(['name' => 'INVENTORY_REPORT_VIEWER']);
        foreach (['view_reports', 'view_hpp'] as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $category = Kategori::create(['nama' => 'Komponen Inventori', 'jenis' => 'SPAREPART', 'status' => 'AKTIF']);

        Produk::create([
            'kode_produk' => 'INV-001',
            'nama_produk' => 'SSD NVMe 512 GB',
            'jenis_produk' => 'SPAREPART',
            'kategori_id' => $category->id,
            'satuan' => 'PCS',
            'harga_beli' => 100000,
            'harga_jual' => 150000,
            'stok' => 4,
            'stok_minimum' => 5,
            'status' => 'AKTIF',
        ]);
        Produk::create([
            'kode_produk' => 'INV-002',
            'nama_produk' => 'RAM DDR4 16 GB',
            'jenis_produk' => 'SPAREPART',
            'kategori_id' => $category->id,
            'satuan' => 'PCS',
            'harga_beli' => 280000,
            'harga_jual' => 350000,
            'stok' => 2,
            'stok_minimum' => 1,
            'status' => 'NONAKTIF',
        ]);

        $this->actingAs($user)->get(route('reports.show', 'stok'))
            ->assertOk()
            ->assertSee('Inventori & Nilai Stok')
            ->assertSee('Produk terdaftar')
            ->assertSee('Total unit stok')
            ->assertSee('Stok menipis')
            ->assertSee('Nilai inventori')
            ->assertSee('Rp 960.000')
            ->assertSee('SSD NVMe 512 GB')
            ->assertSee('RAM DDR4 16 GB')
            ->assertSee('400.000')
            ->assertSee('560.000');

        $printResponse = $this->get(route('reports.show', ['report' => 'stok', 'print' => 1]))
            ->assertOk()
            ->assertSee('Resume Nilai Inventori')
            ->assertSee('SSD NVMe 512 GB')
            ->assertSee('RAM DDR4 16 GB')
            ->assertSee('Komponen Inventori');

        $document = new \DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        $document->loadHTML($printResponse->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);
        $reportText = (new \DOMXPath($document))->query('//*[@id="report-content"]')->item(0)?->textContent;

        $this->assertNotNull($reportText);
        $this->assertStringNotContainsString('INV-001', $reportText);
        $this->assertStringNotContainsString('INV-002', $reportText);
        $this->assertStringContainsString('Rp 960.000', $reportText);
    }
}
