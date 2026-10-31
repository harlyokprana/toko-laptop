<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'referensi_type',
        'referensi_id',
        'metode',
        'nominal',
        'status',
        'keterangan',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'referensi_id' => 'integer',
            'nominal' => 'decimal:2',
            'created_at' => 'datetime',
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