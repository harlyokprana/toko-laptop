<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDetail extends Model
{
    use HasFactory;

    protected $table = 'service_detail';

    protected $fillable = [
        'service_id',
        'produk_id',
        'qty',
        'harga',
        'hpp',
        'subtotal',
        'approval_id',
        'stock_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'harga' => 'decimal:2',
            'hpp' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'stock_deducted_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'service_id'
        );
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(
            Produk::class,
            'produk_id'
        );
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ServiceApproval::class, 'approval_id');
    }
}
