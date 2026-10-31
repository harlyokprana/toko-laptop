<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'supplier';

    protected $fillable = [
        'kode_supplier',
        'nama',
        'kontak',
        'telepon',
        'email',
        'alamat',
        'status',
    ];

    /**
     * Supplier memiliki banyak pembelian.
     */
    public function pembelian(): HasMany
    {
        return $this->hasMany(Pembelian::class, 'supplier_id');
    }

    /**
     * Supplier dapat menjadi sumber laptop.
     */
    public function laptop(): HasMany
    {
        return $this->hasMany(Laptop::class, 'supplier_id');
    }
}