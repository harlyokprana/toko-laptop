<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $table = 'service';

    protected $fillable = [
        'no_service',
        'pelanggan_id',
        'tanggal_masuk',
        'tanggal_selesai',
        'jenis_perangkat',
        'merek',
        'model',
        'serial_number',
        'keluhan',
        'diagnosa',
        'teknisi_id',
        'status',
        'jasa',
        'diskon',
        'pajak_persen',
        'pajak',
        'biaya_tambahan',
        'total',
        'dibayar',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'datetime',
            'tanggal_selesai' => 'datetime',
            'jasa' => 'decimal:2',
            'diskon' => 'decimal:2',
            'pajak_persen' => 'decimal:2',
            'pajak' => 'decimal:2',
            'biaya_tambahan' => 'decimal:2',
            'total' => 'decimal:2',
            'dibayar' => 'decimal:2',
        ];
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(
            Pelanggan::class,
            'pelanggan_id'
        );
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'teknisi_id'
        );
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            ServiceDetail::class,
            'service_id'
        );
    }

    public function progress(): HasMany
    {
        return $this->hasMany(
            ServiceProgress::class,
            'service_id'
        );
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ServiceApproval::class, 'service_id');
    }
}
