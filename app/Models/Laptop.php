<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Laptop extends Model
{
    use HasFactory;

    protected $table = 'laptop';

    protected $fillable = [
        'produk_id',
        'supplier_id',
        'sku',
        'serial_number',
        'tanggal_perolehan',
        'merek',
        'model_seri',
        'kondisi',
        'processor',
        'ram',
        'storage_jenis',
        'storage_kapasitas',
        'gpu',
        'ukuran_layar',
        'resolusi_layar',
        'spesifikasi',
        'hpp',
        'harga_jual',
        'biaya_tambahan',
        'status',
        'penjualan_id',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_perolehan' => 'date',
            'hpp' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'biaya_tambahan' => 'decimal:2',
        ];
    }

    public function spesifikasiRingkas(): ?string
    {
        $specifications = array_filter([
            $this->merek,
            $this->model_seri,
            $this->processor,
            $this->ram ? 'RAM '.$this->ram : null,
            trim(($this->storage_jenis ?? '').' '.($this->storage_kapasitas ?? '')) !== ''
                ? 'Storage '.trim(($this->storage_jenis ?? '').' '.($this->storage_kapasitas ?? ''))
                : null,
            $this->gpu ? 'GPU '.$this->gpu : null,
            $this->ukuran_layar
                ? 'Layar '.$this->ukuran_layar.($this->resolusi_layar ? ' ('.$this->resolusi_layar.')' : '')
                : ($this->resolusi_layar ? 'Resolusi '.$this->resolusi_layar : null),
            $this->kondisi ? 'Kondisi '.$this->kondisi : null,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return $specifications === [] ? null : implode(' · ', $specifications);
    }

    /**
     * Laptop berasal dari satu produk.
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    /**
     * Laptop berasal dari satu supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Laptop dapat terjual melalui satu detail penjualan.
     *
     * Relasi:
     * penjualan_detail.laptop_id → laptop.id
     */
    public function penjualanDetail(): HasOne
    {
        return $this->hasOne(
            PenjualanDetail::class,
            'laptop_id',
            'id'
        );
    }
}
