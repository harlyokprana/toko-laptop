<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pendapatan extends Model
{
    use HasFactory;

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
}