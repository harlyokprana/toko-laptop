<?php

namespace Tests\Feature;

use App\Models\Laptop;
use App\Models\Produk;
use App\Models\StokMutasi;
use Database\Seeders\ProductTypeSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTypeSampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_five_products_per_type_with_ten_units_and_is_idempotent(): void
    {
        $seeder = new ProductTypeSampleSeeder;

        $seeder->run();
        $seeder->run();

        foreach (['LAPTOP', 'AKSESORIS', 'SPAREPART', 'PC_RAKITAN'] as $type) {
            $products = Produk::query()->where('jenis_produk', $type)->get();

            $this->assertCount(5, $products);
            $this->assertSame([10, 10, 10, 10, 10], $products->pluck('stok')->map(fn ($stock) => (int) $stock)->sort()->values()->all());
        }

        $this->assertSame(50, Laptop::query()->whereHas('produk', fn ($query) => $query->where('jenis_produk', 'LAPTOP'))->count());
        $this->assertSame(20, StokMutasi::query()->where('referensi_type', 'SAMPLE_OPENING')->count());
    }
}
