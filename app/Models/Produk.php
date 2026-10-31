<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'produk';

    protected $fillable = [
        'kode_produk',
        'nama_produk',
        'jenis_produk',
        'kategori_id',
        'merek',
        'tipe',
        'atribut',
        'satuan',
        'harga_beli',
        'harga_jual',
        'stok',
        'stok_minimum',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'atribut' => 'array',
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'stok' => 'decimal:2',
            'stok_minimum' => 'decimal:2',
        ];
    }

    public function spesifikasiRingkas(?Laptop $unit = null): ?string
    {
        $attributes = $this->atribut ?? [];
        $ram = $unit?->ram ?: ($attributes['ram'] ?? null);
        $productStorage = $attributes['storage'] ?? null;
        $condition = $unit?->kondisi ?: ($attributes['kondisi'] ?? null);
        $storage = trim(implode(' ', array_filter([
            $unit?->storage_jenis,
            $unit?->storage_kapasitas,
        ])));
        $screen = $unit?->ukuran_layar ?: ($attributes['ukuran_layar'] ?? null);
        $resolution = $unit?->resolusi_layar;
        $specifications = array_filter([
            $unit?->merek ?: $this->merek,
            $unit?->model_seri ?: $this->tipe,
            match ($this->jenis_produk) {
                'LAPTOP' => $unit?->processor ?: ($attributes['processor'] ?? null),
                'PC_RAKITAN' => $attributes['motherboard'] ?? null,
                default => null,
            },
            $ram ? 'RAM '.$ram : null,
            $storage !== '' ? 'Storage '.$storage : ($productStorage ? 'Storage '.$productStorage : null),
            $screen ? 'Layar '.$screen.($resolution ? ' ('.$resolution.')' : '') : ($resolution ? 'Resolusi '.$resolution : null),
            isset($attributes['ukuran_lcd']) ? 'LCD '.$attributes['ukuran_lcd'] : null,
            $condition ? 'Kondisi '.$condition : null,
            $unit?->gpu ? 'GPU '.$unit->gpu : null,
            $attributes['jenis_aksesori'] ?? null,
            $attributes['spesifikasi_singkat'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return $specifications === [] ? null : implode(' · ', $specifications);
    }

    /**
     * Produk memiliki satu kategori (legacy, tetap untuk kompatibilitas).
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    /**
     * Produk dapat memiliki banyak unit laptop.
     */
    public function laptop(): HasMany
    {
        return $this->hasMany(Laptop::class, 'produk_id');
    }

    /**
     * Produk muncul pada banyak detail pembelian.
     */
    public function pembelianDetail(): HasMany
    {
        return $this->hasMany(PembelianDetail::class, 'produk_id');
    }

    /**
     * Produk muncul pada banyak detail penjualan.
     */
    public function penjualanDetail(): HasMany
    {
        return $this->hasMany(PenjualanDetail::class, 'produk_id');
    }

    /**
     * Produk memiliki banyak mutasi stok.
     */
    public function stokMutasi(): HasMany
    {
        return $this->hasMany(StokMutasi::class, 'produk_id');
    }

    /**
     * Produk dapat digunakan pada banyak detail service.
     */
    public function serviceDetail(): HasMany
    {
        return $this->hasMany(ServiceDetail::class, 'produk_id');
    }
}
