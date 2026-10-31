<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PembelianPenerimaan extends Model
{
    protected $table = 'pembelian_penerimaan';

    protected $fillable = ['pembelian_id', 'nomor_penerimaan', 'tanggal_terima', 'catatan', 'user_id'];

    protected function casts(): array
    {
        return ['tanggal_terima' => 'datetime'];
    }

    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(Pembelian::class, 'pembelian_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PembelianPenerimaanDetail::class, 'penerimaan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
