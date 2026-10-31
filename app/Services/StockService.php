<?php

namespace App\Services;

use App\Models\Produk;
use App\Models\StokMutasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function increase(
        int $productId,
        int $quantity,
        string $mutationType,
        int $userId,
        string $note,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StokMutasi {
        return $this->apply($productId, $quantity, true, $mutationType, $userId, $note, $referenceType, $referenceId);
    }

    public function decrease(
        int $productId,
        int $quantity,
        string $mutationType,
        int $userId,
        string $note,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StokMutasi {
        return $this->apply($productId, $quantity, false, $mutationType, $userId, $note, $referenceType, $referenceId);
    }

    private function apply(
        int $productId,
        int $quantity,
        bool $increase,
        string $mutationType,
        int $userId,
        string $note,
        ?string $referenceType,
        ?int $referenceId,
    ): StokMutasi {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Jumlah mutasi stok harus lebih dari nol.');
        }

        return DB::transaction(function () use ($productId, $quantity, $increase, $mutationType, $userId, $note, $referenceType, $referenceId) {
            $product = Produk::query()->lockForUpdate()->findOrFail($productId);
            $currentStock = (int) $product->stok;

            if (! $increase && $quantity > $currentStock) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$product->nama_produk} saat ini hanya {$currentStock}.",
                ]);
            }

            $newStock = $increase ? $currentStock + $quantity : $currentStock - $quantity;

            $product->forceFill(['stok' => $newStock])->save();

            return StokMutasi::create([
                'tanggal' => now(),
                'produk_id' => $product->id,
                'jenis_mutasi' => $mutationType,
                'referensi_type' => $referenceType,
                'referensi_id' => $referenceId,
                'qty_masuk' => $increase ? $quantity : 0,
                'qty_keluar' => $increase ? 0 : $quantity,
                'stok_saldo' => $newStock,
                'hpp' => $product->harga_beli,
                'keterangan' => $note,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }
}
