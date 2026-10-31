<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    use HasFactory;

    protected $table = 'pelanggan';

    protected $fillable = [
        'user_id',
        'kode_pelanggan',
        'nama',
        'email',
        'telepon',
        'alamat',
        'status',
    ];

    /**
     * Data pelanggan dapat terhubung ke akun User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Pelanggan memiliki banyak transaksi penjualan.
     */
    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'pelanggan_id');
    }

    /**
     * Pelanggan memiliki banyak service.
     */
    public function service(): HasMany
    {
        return $this->hasMany(Service::class, 'pelanggan_id');
    }
}