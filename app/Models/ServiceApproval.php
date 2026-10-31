<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceApproval extends Model
{
    use HasFactory;

    protected $table = 'service_approvals';

    protected $fillable = [
        'service_id',
        'status_sebelum',
        'progress_sebelum',
        'jasa_sebelum',
        'diskon_sebelum',
        'pajak_persen_sebelum',
        'pajak_sebelum',
        'biaya_tambahan_sebelum',
        'total_sebelum',
        'estimasi',
        'nominal',
        'status',
        'approved_at',
        'approved_by',
        'requested_by',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'progress_sebelum' => 'integer',
            'jasa_sebelum' => 'decimal:2',
            'diskon_sebelum' => 'decimal:2',
            'pajak_persen_sebelum' => 'decimal:2',
            'pajak_sebelum' => 'decimal:2',
            'biaya_tambahan_sebelum' => 'decimal:2',
            'total_sebelum' => 'decimal:2',
            'estimasi' => 'decimal:2',
            'nominal' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ServiceDetail::class, 'approval_id');
    }
}
