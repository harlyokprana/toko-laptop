<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianDetail extends Model
{
    use HasFactory;

    protected $table = 'pembelian_detail';

    protected $fillable = [
        'pembelian_id',
        'produk_id',
        'qty',
        'qty_diterima',
        'harga_beli',
        'diskon',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'qty_diterima' => 'decimal:2',
            'harga_beli' => 'decimal:2',
            'diskon' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(
            Pembelian::class,
            'pembelian_id'
        );
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(
            Produk::class,
            'produk_id'
        );
    }
}
