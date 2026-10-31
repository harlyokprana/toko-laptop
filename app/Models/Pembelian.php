<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembelian extends Model
{
    use HasFactory;

    protected $table = 'pembelian';

    protected $fillable = [
        'no_invoice',
        'tanggal',
        'supplier_id',
        'jenis_pembelian',
        'subtotal',
        'diskon',
        'ongkir',
        'total',
        'dibayar',
        'status',
        'user_id',
        'keterangan',
        'estimasi_tiba',
        'mata_uang',
        'termin_pembayaran',
        'pajak_persen',
        'pajak',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'estimasi_tiba' => 'date',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'diskon' => 'decimal:2',
            'ongkir' => 'decimal:2',
            'total' => 'decimal:2',
            'dibayar' => 'decimal:2',
            'pajak_persen' => 'decimal:2',
            'pajak' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            PembelianDetail::class,
            'pembelian_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PembelianPenerimaan::class, 'pembelian_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(PembelianHistory::class, 'pembelian_id')->orderBy('created_at')->orderBy('id');
    }
}
