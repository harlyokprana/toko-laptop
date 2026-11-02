<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'supplier';

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            if (static::query()->count() >= 50) {
                throw ValidationException::withMessages([
                    'kode_supplier' => 'Jumlah supplier sudah mencapai batas maksimal 50.',
                ]);
            }

            if (blank($supplier->kode_supplier)) {
                if (($code = static::nextCode()) === null) {
                    throw ValidationException::withMessages([
                        'kode_supplier' => 'Jumlah supplier sudah mencapai batas maksimal 50.',
                    ]);
                }

                $supplier->kode_supplier = $code;
            }
        });
    }

    public static function nextCode(): ?string
    {
        for ($number = 1; $number <= 50; $number++) {
            $code = 'S'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            if (! static::query()->where('kode_supplier', $code)->exists()) {
                return $code;
            }
        }

        return null;
    }

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
