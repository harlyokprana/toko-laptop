<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianPenerimaanDetail extends Model
{
    protected $table = 'pembelian_penerimaan_detail';

    protected $fillable = ['penerimaan_id', 'pembelian_detail_id', 'qty', 'kondisi', 'catatan'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PembelianPenerimaan::class, 'penerimaan_id');
    }

    public function purchaseDetail(): BelongsTo
    {
        return $this->belongsTo(PembelianDetail::class, 'pembelian_detail_id');
    }
}
