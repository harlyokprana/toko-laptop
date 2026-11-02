<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pendapatan extends Model
{
    use HasFactory;

    public const NON_REVENUE_TYPES = ['PENAMBAHAN_KAS', 'PENYESUAIAN_KAS'];

    protected $table = 'pendapatan';

    protected $fillable = [
        'tanggal',
        'kode_pendapatan',
        'jenis_pendapatan',
        'sumber',
        'referensi_type',
        'referensi_id',
        'nominal',
        'keterangan',
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

    public function scopeForProfitLoss(Builder $query): Builder
    {
        return $query->whereNotIn('jenis_pendapatan', self::NON_REVENUE_TYPES);
    }
}
