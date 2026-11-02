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

    protected static function booted(): void
    {
        static::creating(function (Pelanggan $customer): void {
            if (blank($customer->kode_pelanggan)) {
                $customer->kode_pelanggan = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $highestNumber = static::query()
            ->where('kode_pelanggan', 'like', 'C-%')
            ->pluck('kode_pelanggan')
            ->map(fn (string $code): int => preg_match('/\AC-(\d+)\z/', $code, $matches) === 1 ? (int) $matches[1] : 0)
            ->max() ?? 0;

        do {
            $code = 'C-'.str_pad((string) ++$highestNumber, 5, '0', STR_PAD_LEFT);
        } while (static::query()->where('kode_pelanggan', $code)->exists());

        return $code;
    }

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
