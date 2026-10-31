<?php

namespace App\Services;

use App\Models\KasMutasi;
use Illuminate\Support\Facades\DB;

class CashLedgerService
{
    public function receipt(float $amount, int $userId, string $referenceType, ?int $referenceId, string $description, string $kind = 'PENDAPATAN'): KasMutasi
    {
        $this->assertPositiveAmount($amount);

        if (! in_array($kind, ['PENDAPATAN', 'PENAMBAHAN_KAS', 'ADJUSTMENT'], true)) {
            throw new \InvalidArgumentException('Jenis mutasi kas masuk tidak valid.');
        }
        return DB::transaction(function () use ($amount, $userId, $referenceType, $referenceId, $description, $kind): KasMutasi {
            $last = KasMutasi::query()->orderByDesc('id')->lockForUpdate()->first();
            $balance = round((float) ($last?->saldo ?? 0) + $amount, 2);

            return KasMutasi::create([
                'tanggal' => now(),
                'jenis' => $kind,
                'referensi_type' => $referenceType,
                'referensi_id' => $referenceId,
                'keterangan' => $description,
                'debit' => $amount,
                'kredit' => 0,
                'saldo' => $balance,
                'user_id' => $userId,
            ]);
        });
    }

    public function disbursement(
        float $amount,
        int $userId,
        string $referenceType,
        ?int $referenceId,
        string $description,
        string $kind = 'PENGELUARAN',
    ): KasMutasi {
        $this->assertPositiveAmount($amount);

        if (! in_array($kind, ['PENGELUARAN', 'PEMBAYARAN', 'PENGAMBILAN_KAS', 'ADJUSTMENT'], true)) {
            throw new \InvalidArgumentException('Jenis mutasi kas keluar tidak valid.');
        }

        return DB::transaction(function () use ($amount, $userId, $referenceType, $referenceId, $description, $kind): KasMutasi {
            $last = KasMutasi::query()->orderByDesc('id')->lockForUpdate()->first();
            $balance = round((float) ($last?->saldo ?? 0) - $amount, 2);

            return KasMutasi::create([
                'tanggal' => now(),
                'jenis' => $kind,
                'referensi_type' => $referenceType,
                'referensi_id' => $referenceId,
                'keterangan' => $description,
                'debit' => 0,
                'kredit' => $amount,
                'saldo' => $balance,
                'user_id' => $userId,
            ]);
        });
    }

    public function adjustment(
        float $debit,
        float $credit,
        int $userId,
        string $referenceType,
        ?int $referenceId,
        string $description,
    ): KasMutasi {
        if (! is_finite($debit) || ! is_finite($credit) || $debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
            throw new \InvalidArgumentException('Adjustment harus berupa kas masuk atau kas keluar dengan nilai positif.');
        }

        return DB::transaction(function () use ($debit, $credit, $userId, $referenceType, $referenceId, $description): KasMutasi {
            $last = KasMutasi::query()->orderByDesc('id')->lockForUpdate()->first();
            $balance = round((float) ($last?->saldo ?? 0) + $debit - $credit, 2);

            return KasMutasi::create([
                'tanggal' => now(), 'jenis' => 'ADJUSTMENT', 'referensi_type' => $referenceType,
                'referensi_id' => $referenceId, 'keterangan' => $description,
                'debit' => $debit, 'kredit' => $credit, 'saldo' => $balance, 'user_id' => $userId,
            ]);
        });
    }

    private function assertPositiveAmount(float $amount): void
    {
        if (! is_finite($amount) || $amount <= 0) {
            throw new \InvalidArgumentException('Nominal mutasi kas harus lebih dari nol.');
        }
    }
}
