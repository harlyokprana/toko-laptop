<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenjualanDetail extends Model
{
    use HasFactory;

    protected $table = 'penjualan_detail';

    protected $fillable = [
        'penjualan_id',
        'produk_id',
        'laptop_id',
        'qty',
        'harga',
        'diskon',
        'hpp',
        'laba',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga' => 'decimal:2',
            'diskon' => 'decimal:2',
            'hpp' => 'decimal:2',
            'laba' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(
            Penjualan::class,
            'penjualan_id'
        );
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(
            Produk::class,
            'produk_id'
        );
    }

    public function laptop(): BelongsTo
    {
        return $this->belongsTo(
            Laptop::class,
            'laptop_id'
        );
    }
}