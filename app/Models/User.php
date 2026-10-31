<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role_id',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * User memiliki satu role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Check whether the user's role grants a permission.
     */
    public function hasPermission(string $permission): bool
    {
        $role = $this->role;

        return $role !== null && $role->is_active && $role->permissions->contains('name', $permission);
    }

    /**
     * Check whether the user's role grants at least one permission.
     *
     * @param  iterable<string>  $permissions
     */
    public function hasAnyPermission(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the user's role grants every listed permission.
     *
     * @param  iterable<string>  $permissions
     */
    public function hasAllPermissions(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * User dapat menjadi teknisi untuk banyak service.
     */
    public function servicesAsTechnician(): HasMany
    {
        return $this->hasMany(Service::class, 'teknisi_id');
    }

    /**
     * User dapat memiliki data pelanggan.
     */
    public function pelanggan(): HasOne
    {
        return $this->hasOne(Pelanggan::class, 'user_id');
    }

    /**
     * User membuat banyak transaksi penjualan.
     */
    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'user_id');
    }

    /**
     * User membuat banyak transaksi pembelian.
     */
    public function pembelian(): HasMany
    {
        return $this->hasMany(Pembelian::class, 'user_id');
    }

    /**
     * User membuat banyak mutasi stok.
     */
    public function stokMutasi(): HasMany
    {
        return $this->hasMany(StokMutasi::class, 'user_id');
    }

    /**
     * User membuat banyak pendapatan.
     */
    public function pendapatan(): HasMany
    {
        return $this->hasMany(Pendapatan::class, 'user_id');
    }

    /**
     * User membuat banyak pengeluaran.
     */
    public function pengeluaran(): HasMany
    {
        return $this->hasMany(Pengeluaran::class, 'user_id');
    }

    /**
     * User membuat banyak mutasi kas.
     */
    public function kasMutasi(): HasMany
    {
        return $this->hasMany(KasMutasi::class, 'user_id');
    }

    /**
     * User membuat banyak pembayaran.
     */
    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'user_id');
    }
}
