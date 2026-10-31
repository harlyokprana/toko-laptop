<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasMutasi extends Model
{
    use HasFactory;

    protected $table = 'kas_mutasi';

    protected $fillable = [
        'tanggal',
        'jenis',
        'referensi_type',
        'referensi_id',
        'keterangan',
        'debit',
        'kredit',
        'saldo',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'referensi_id' => 'integer',
            'debit' => 'decimal:2',
            'kredit' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}