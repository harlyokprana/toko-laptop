<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\KasMutasi;
use App\Models\Pengeluaran;
use App\Services\CashLedgerService;
use App\Support\CsvSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashController extends Controller
{
    public function storeMovement(Request $request, CashLedgerService $cash): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['PENAMBAHAN_KAS', 'PENGAMBILAN_KAS', 'ADJUSTMENT'])],
            'arah' => ['nullable', Rule::in(['MASUK', 'KELUAR'])],
            'nominal' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'keterangan' => ['required', 'string', 'max:1000'],
        ]);
        if ($data['jenis'] === 'ADJUSTMENT' && empty($data['arah'])) {
            throw ValidationException::withMessages(['arah' => 'Pilih arah adjustment kas.']);
        }
        if ($request->user()->role?->name === 'KASIR'
            && ! KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift sebelum mencatat mutasi kas manual.']);
        }

        $amount = (float) $data['nominal'];
        $reference = 'Kas manual: '.$data['keterangan'];

        DB::transaction(function () use ($data, $request, $cash, $amount, $reference): void {
            $mutation = match ($data['jenis']) {
                'PENAMBAHAN_KAS' => $cash->receipt($amount, $request->user()->id, 'MANUAL_KAS', null, $reference, 'PENAMBAHAN_KAS'),
                'PENGAMBILAN_KAS' => $cash->disbursement($amount, $request->user()->id, 'MANUAL_KAS', null, $reference, 'PENGAMBILAN_KAS'),
                default => $cash->adjustment(
                    ($data['arah'] ?? '') === 'MASUK' ? $amount : 0,
                    ($data['arah'] ?? '') === 'KELUAR' ? $amount : 0,
                    $request->user()->id, 'MANUAL_KAS', null, $reference,
                ),
            };

            if ($data['jenis'] === 'PENGAMBILAN_KAS') {
                $expense = Pengeluaran::create([
                    'tanggal' => now(),
                    'kode_beban' => 'EXP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'kategori_beban' => 'PENGAMBILAN_KAS',
                    'keterangan' => $data['keterangan'],
                    'nominal' => $amount,
                    'metode_bayar' => 'CASH',
                    'user_id' => $request->user()->id,
                    'referensi_type' => 'MANUAL_KAS',
                    'referensi_id' => $mutation->id,
                ]);
                $mutation->update(['referensi_id' => $expense->id]);
            }
        });

        return back()->with('status', 'Mutasi kas berhasil dicatat.');
    }

    public function index(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'jenis' => ['nullable', Rule::in(['PENDAPATAN', 'PENGELUARAN', 'PEMBAYARAN', 'PENAMBAHAN_KAS', 'PENGAMBILAN_KAS', 'ADJUSTMENT'])],
            'q' => ['nullable', 'string', 'max:150'], 'export' => ['nullable', Rule::in(['csv'])],
        ]);
        $query = KasMutasi::query()->with('user:id,name')->orderByDesc('tanggal')->orderByDesc('id');
        $this->applyFilters($query, $filters);

        if (($filters['export'] ?? null) === 'csv') {
            $rows = (clone $query)->get();

            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Tanggal', 'Jenis', 'Referensi', 'Keterangan', 'Cash In', 'Cash Out', 'Saldo', 'User']);
                foreach ($rows as $row) {
                    fputcsv($output, array_map([CsvSanitizer::class, 'escapeFormula'], [
                        $row->tanggal?->format('Y-m-d'), $row->jenis,
                        $row->referensi_type.($row->referensi_id ? '#'.$row->referensi_id : ''),
                        $row->keterangan, $row->debit, $row->kredit, $row->saldo, $row->user?->name,
                    ]));
                }
                fclose($output);
            }, 'kas-mutasi-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $period = KasMutasi::query();
        if (! empty($filters['date_from'])) {
            $period->whereDate('tanggal', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $period->whereDate('tanggal', '<=', $filters['date_to']);
        }
        $cashIn = (clone $period)->sum('debit');
        $cashOut = (clone $period)->sum('kredit');
        $balanceQuery = KasMutasi::query();
        if (! empty($filters['date_to'])) {
            $balanceQuery->whereDate('tanggal', '<=', $filters['date_to']);
        }
        $balance = $balanceQuery->orderByDesc('tanggal')->orderByDesc('id')->value('saldo') ?? 0;
        $filtered = clone $query;

        return view('cash.index', [
            'mutations' => $query->paginate(25)->withQueryString(), 'filters' => $filters,
            'cashIn' => $cashIn, 'cashOut' => $cashOut, 'balance' => $balance,
            'filteredIn' => (clone $filtered)->sum('debit'),
            'filteredOut' => (clone $filtered)->sum('kredit'),
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('tanggal', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('tanggal', '<=', $filters['date_to']);
        }
        if (! empty($filters['jenis'])) {
            $query->where('jenis', $filters['jenis']);
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $builder) => $builder->where('referensi_type', 'like', "%{$search}%")->orWhere('keterangan', 'like', "%{$search}%"));
        }
    }
}
