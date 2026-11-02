<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\Pembayaran;
use App\Models\Pendapatan;
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

class IncomeController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'jenis' => ['nullable', Rule::in(['SERVICE', 'PENJUALAN_AKSESORIS', 'PRINTING', 'SEWA', 'PENJUALAN_LAPTOP', 'LAINNYA'])],
            'q' => ['nullable', 'string', 'max:150'], 'export' => ['nullable', Rule::in(['csv'])],
        ]);
        $query = Pendapatan::query()->forProfitLoss()->with('user:id,name')->latest('tanggal')->latest('id');
        $this->applyFilters($query, $filters);

        if (($filters['export'] ?? null) === 'csv') {
            $rows = (clone $query)->get();

            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Tanggal', 'Kode', 'Jenis', 'Sumber', 'Keterangan', 'Nominal', 'User']);
                foreach ($rows as $income) {
                    fputcsv($output, array_map([CsvSanitizer::class, 'escapeFormula'], [
                        $income->tanggal?->format('Y-m-d'), $income->kode_pendapatan, $income->jenis_pendapatan,
                        $income->sumber, $income->keterangan, $income->nominal, $income->user?->name,
                    ]));
                }
                fclose($output);
            }, 'pendapatan-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('income.index', [
            'incomes' => $query->paginate(20)->withQueryString(), 'filters' => $filters,
            'total' => (clone $query)->sum('nominal'), 'count' => (clone $query)->count(),
        ]);
    }

    public function create(): View
    {
        return view('income.create');
    }

    public function store(Request $request, CashLedgerService $cash): RedirectResponse
    {
        $data = $request->validate([
            'jenis_pendapatan' => ['required', Rule::in(['PRINTING', 'SEWA', 'LAINNYA'])],
            'sumber' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'metode' => ['required', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($request->user()->role?->name === 'KASIR' && ! KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift kasir terlebih dahulu sebelum mencatat pendapatan.']);
        }

        $income = DB::transaction(function () use ($data, $request, $cash): Pendapatan {
            $income = Pendapatan::create([
                'tanggal' => now(), 'kode_pendapatan' => 'INC-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'jenis_pendapatan' => $data['jenis_pendapatan'], 'sumber' => $data['sumber'],
                'nominal' => $data['nominal'], 'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $request->user()->id,
            ]);
            Pembayaran::create([
                'tanggal' => now(), 'referensi_type' => 'PENDAPATAN', 'referensi_id' => $income->id,
                'metode' => $data['metode'], 'nominal' => $income->nominal, 'status' => 'LUNAS',
                'keterangan' => 'Penerimaan '.$income->kode_pendapatan, 'user_id' => $request->user()->id, 'created_at' => now(),
            ]);
            if ($data['metode'] === 'CASH') {
                $cash->receipt((float) $income->nominal, $request->user()->id, 'pendapatan', $income->id, 'Setoran '.$income->kode_pendapatan.' · '.$income->sumber);
            }

            return $income;
        });

        return redirect()->route('module.income')->with('status', "Pendapatan {$income->kode_pendapatan} berhasil dicatat.");
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
            $query->where('jenis_pendapatan', $filters['jenis']);
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $builder) => $builder->where('kode_pendapatan', 'like', "%{$search}%")
                ->orWhere('sumber', 'like', "%{$search}%")->orWhere('keterangan', 'like', "%{$search}%"));
        }
    }
}
