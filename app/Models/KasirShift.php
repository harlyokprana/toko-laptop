<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasirShift extends Model
{
    protected $table = 'kasir_shifts';

    protected $fillable = [
        'user_id', 'opened_at', 'opening_cash', 'opening_note', 'closed_at',
        'expected_cash', 'closing_cash', 'cash_difference', 'closing_note', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
