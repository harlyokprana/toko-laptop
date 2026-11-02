<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pengeluaran extends Model
{
    use HasFactory;

    public const CASH_BALANCE_CATEGORIES = ['PENGAMBILAN_KAS', 'PENYESUAIAN_KAS'];

    public const NON_PROFIT_LOSS_CATEGORIES = [...self::CASH_BALANCE_CATEGORIES, 'PEMBELIAN_INVENTARIS'];

    protected $table = 'pengeluaran';

    protected $fillable = [
        'tanggal',
        'kode_beban',
        'kategori_beban',
        'keterangan',
        'nominal',
        'metode_bayar',
        'referensi_type',
        'referensi_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'referensi_id' => 'integer',
            'nominal' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function payroll(): HasOne
    {
        return $this->hasOne(Payroll::class, 'pengeluaran_id');
    }

    public function scopeForProfitLoss(Builder $query): Builder
    {
        return $query->whereNotIn('kategori_beban', self::NON_PROFIT_LOSS_CATEGORIES);
    }
}
