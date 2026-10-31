<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';

    protected $fillable = [
        'no_invoice',
        'tanggal',
        'pelanggan_id',
        'jenis_penjualan',
        'subtotal',
        'diskon',
        'pajak_persen',
        'pajak',
        'biaya_tambahan',
        'total',
        'dibayar',
        'kembalian',
        'status',
        'user_id',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'subtotal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'pajak_persen' => 'decimal:2',
            'pajak' => 'decimal:2',
            'biaya_tambahan' => 'decimal:2',
            'total' => 'decimal:2',
            'dibayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(
            Pelanggan::class,
            'pelanggan_id'
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            PenjualanDetail::class,
            'penjualan_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}
