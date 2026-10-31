<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    private const SYSTEM_ROLE_NAMES = ['PIMPINAN', 'MANAGER', 'ADMIN', 'KASIR', 'TEKNISI', 'PELANGGAN'];

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isSystemRole(): bool
    {
        return in_array($this->name, self::SYSTEM_ROLE_NAMES, true);
    }

    /**
     * Role memiliki banyak user.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /**
     * Role memiliki banyak permission.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        );
    }
}
