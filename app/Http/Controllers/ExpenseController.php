<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\Pengeluaran;
use App\Models\User;
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

class ExpenseController extends Controller
{
    private const CATEGORIES = ['LISTRIK', 'INTERNET', 'TRANSPORT', 'MAKAN', 'ONGKIR', 'SEWA', 'PEMBELIAN_INVENTARIS', 'MAINTENANCE', 'OPERASIONAL', 'GAJI', 'LAINNYA', 'PENGAMBILAN_KAS', 'PENYESUAIAN_KAS'];
    private const METHODS = ['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'];

    public function index(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'kategori' => ['nullable', Rule::in(self::CATEGORIES)], 'metode' => ['nullable', Rule::in(self::METHODS)],
            'q' => ['nullable', 'string', 'max:150'], 'export' => ['nullable', Rule::in(['csv'])],
        ]);
        $query = Pengeluaran::query()->with('user:id,name')->latest('tanggal')->latest('id');
        $this->applyFilters($query, $filters);

        if (($filters['export'] ?? null) === 'csv') {
            $rows = (clone $query)->get();
            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Tanggal', 'Kode Beban', 'Kategori', 'Keterangan', 'Nominal', 'Metode Bayar', 'User']);
                foreach ($rows as $expense) {
                    fputcsv($output, array_map([CsvSanitizer::class, 'escapeFormula'], [
                        $expense->tanggal?->format('Y-m-d'), $expense->kode_beban, $expense->kategori_beban,
                        $expense->keterangan, $expense->nominal, $expense->metode_bayar, $expense->user?->name,
                    ]));
                }
                fclose($output);
            }, 'pengeluaran-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($request->query('print') === '1') {
            return view('expenses.print', ['expenses' => (clone $query)->get(), 'total' => (clone $query)->sum('nominal'), 'filters' => $filters]);
        }

        $totalsQuery = clone $query;
        return view('expenses.index', ['expenses' => $query->paginate(20)->withQueryString(), 'filters' => $filters, 'total' => $totalsQuery->sum('nominal'), 'categories' => self::CATEGORIES, 'methods' => self::METHODS, 'printMode' => false]);
    }

    public function create(): View
    {
        return view('expenses.form', ['expense' => new Pengeluaran(), 'categories' => self::CATEGORIES, 'methods' => self::METHODS, 'users' => User::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, CashLedgerService $cash): RedirectResponse
    {
        $data = $this->validated($request);
        $this->ensureShift($request, $data['metode_bayar']);
        $expense = DB::transaction(function () use ($data, $request, $cash): Pengeluaran {
            $expense = Pengeluaran::create([
                'tanggal' => $data['tanggal'], 'kode_beban' => 'EXP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'kategori_beban' => $data['kategori_beban'], 'keterangan' => $data['keterangan'],
                'nominal' => $data['nominal'], 'metode_bayar' => $data['metode_bayar'],
                'user_id' => $request->user()->id,
            ]);
            if ($expense->metode_bayar === 'CASH') {
                $cash->disbursement((float) $expense->nominal, $request->user()->id, 'pengeluaran', $expense->id, 'Pengeluaran '.$expense->kode_beban.' · '.$expense->kategori_beban);
            }
            return $expense;
        });

        return redirect()->route('expenses.index')->with('status', "Pengeluaran {$expense->kode_beban} berhasil dicatat.");
    }

    public function edit(Pengeluaran $expense): View
    {
        abort_if($expense->payroll()->exists(), 403, 'Pengeluaran payroll dikelola dari modul payroll.');
        return view('expenses.form', ['expense' => $expense, 'categories' => self::CATEGORIES, 'methods' => self::METHODS, 'users' => User::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, Pengeluaran $expense, CashLedgerService $cash): RedirectResponse
    {
        abort_if($expense->payroll()->exists(), 403, 'Pengeluaran payroll dikelola dari modul payroll.');
        $data = $this->validated($request);
        $this->ensureShift($request, $data['metode_bayar']);
        DB::transaction(function () use ($expense, $data, $request, $cash): void {
            $locked = Pengeluaran::query()->lockForUpdate()->findOrFail($expense->id);
            $oldCash = $locked->metode_bayar === 'CASH' ? (float) $locked->nominal : 0;
            $newCash = $data['metode_bayar'] === 'CASH' ? (float) $data['nominal'] : 0;
            if ($oldCash > 0 && ($newCash <= 0 || $oldCash !== $newCash)) {
                $cash->adjustment($oldCash, 0, $request->user()->id, 'PENGELUARAN_KOREKSI', $locked->id, 'Pembatalan kas atas perubahan '.$locked->kode_beban);
            }
            $locked->update([
                'tanggal' => $data['tanggal'], 'kategori_beban' => $data['kategori_beban'],
                'keterangan' => $data['keterangan'], 'nominal' => $data['nominal'], 'metode_bayar' => $data['metode_bayar'],
            ]);
            if ($newCash > 0 && ($oldCash <= 0 || $oldCash !== $newCash)) {
                $cash->disbursement($newCash, $request->user()->id, 'pengeluaran', $locked->id, 'Pengeluaran '.$locked->kode_beban.' (koreksi)');
            }
        });
        return redirect()->route('expenses.index')->with('status', 'Pengeluaran diperbarui dan mutasi kas dikoreksi bila diperlukan.');
    }

    public function destroy(Request $request, Pengeluaran $expense, CashLedgerService $cash): RedirectResponse
    {
        abort_if($expense->payroll()->exists(), 403, 'Pengeluaran payroll dikelola dari modul payroll.');
        DB::transaction(function () use ($request, $expense, $cash): void {
            $locked = Pengeluaran::query()->lockForUpdate()->findOrFail($expense->id);
            if ($locked->metode_bayar === 'CASH') {
                $cash->adjustment((float) $locked->nominal, 0, $request->user()->id, 'PENGELUARAN_KOREKSI', $locked->id, 'Pembatalan pengeluaran '.$locked->kode_beban);
            }
            $locked->delete();
        });
        return back()->with('status', 'Pengeluaran dihapus; koreksi kas tersimpan jika transaksi tunai.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'], 'kategori_beban' => ['required', Rule::in(self::CATEGORIES)],
            'keterangan' => ['required', 'string', 'max:2000'], 'nominal' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'metode_bayar' => ['required', Rule::in(self::METHODS)],
        ]);
    }

    private function ensureShift(Request $request, string $method): void
    {
        if ($method === 'CASH' && $request->user()->role?->name === 'KASIR'
            && ! KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift kasir terlebih dahulu sebelum pengeluaran tunai.']);
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) $query->whereDate('tanggal', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $query->whereDate('tanggal', '<=', $filters['date_to']);
        if (! empty($filters['kategori'])) $query->where('kategori_beban', $filters['kategori']);
        if (! empty($filters['metode'])) $query->where('metode_bayar', $filters['metode']);
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $builder) => $builder->where('kode_beban', 'like', "%{$search}%")
                ->orWhere('kategori_beban', 'like', "%{$search}%")->orWhere('keterangan', 'like', "%{$search}%"));
        }
    }
}
