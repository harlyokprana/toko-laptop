<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'periode', 'gaji_pokok', 'tunjangan', 'bonus', 'potongan', 'total',
        'tanggal_pembayaran', 'metode_pembayaran', 'status', 'pengeluaran_id', 'created_by', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'gaji_pokok' => 'decimal:2', 'tunjangan' => 'decimal:2', 'bonus' => 'decimal:2',
            'potongan' => 'decimal:2', 'total' => 'decimal:2', 'tanggal_pembayaran' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Pengeluaran::class, 'pengeluaran_id');
    }
}
