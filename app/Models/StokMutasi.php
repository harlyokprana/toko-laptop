<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokMutasi extends Model
{
    use HasFactory;

    protected $table = 'stok_mutasi';

    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'produk_id',
        'jenis_mutasi',
        'referensi_type',
        'referensi_id',
        'qty_masuk',
        'qty_keluar',
        'stok_saldo',
        'hpp',
        'keterangan',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'qty_masuk' => 'decimal:2',
            'qty_keluar' => 'decimal:2',
            'stok_saldo' => 'decimal:2',
            'hpp' => 'decimal:2',
            'referensi_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(
            Produk::class,
            'produk_id'
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