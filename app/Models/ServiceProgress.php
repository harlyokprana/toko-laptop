<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceProgress extends Model
{
    use HasFactory;

    protected $table = 'service_progress';

    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'status',
        'progress_percent',
        'catatan',
        'updated_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'service_id'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}