<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\Payroll;
use App\Models\Pengeluaran;
use App\Models\User;
use App\Services\CashLedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'periode' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['DRAFT', 'PAID', 'CANCELLED'])],
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $query = Payroll::query()->with(['employee:id,name', 'expense:id,kode_beban'])->latest('periode')->latest('id');
        if (! empty($filters['periode'])) $query->where('periode', $filters['periode']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $builder) => $builder->whereHas('employee', fn (Builder $employee) => $employee->where('name', 'like', "%{$search}%"))
                ->orWhere('periode', 'like', "%{$search}%"));
        }
        return view('payroll.index', ['payrolls' => $query->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('payroll.create', [
            'employees' => User::query()->where('status', 'AKTIF')->whereDoesntHave('role', fn (Builder $role) => $role->where('name', 'PELANGGAN'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'periode' => ['required', 'date_format:Y-m', Rule::unique('payrolls', 'periode')->where(fn ($query) => $query->where('employee_id', $request->input('employee_id')))],
            'gaji_pokok' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'tunjangan' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'bonus' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'potongan' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! User::query()->whereKey($data['employee_id'])->where('status', 'AKTIF')->whereDoesntHave('role', fn (Builder $role) => $role->where('name', 'PELANGGAN'))->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Pilih akun karyawan aktif, bukan akun pelanggan.']);
        }
        $gross = (float) $data['gaji_pokok'] + (float) ($data['tunjangan'] ?? 0) + (float) ($data['bonus'] ?? 0);
        $total = round($gross - (float) ($data['potongan'] ?? 0), 2);
        if ($total <= 0) throw ValidationException::withMessages(['potongan' => 'Total gaji setelah potongan harus lebih dari nol.']);

        Payroll::create([
            'employee_id' => $data['employee_id'], 'periode' => $data['periode'],
            'gaji_pokok' => $data['gaji_pokok'], 'tunjangan' => $data['tunjangan'] ?? 0,
            'bonus' => $data['bonus'] ?? 0, 'potongan' => $data['potongan'] ?? 0,
            'total' => $total, 'status' => 'DRAFT', 'created_by' => $request->user()->id,
            'catatan' => $data['catatan'] ?? null,
        ]);
        return redirect()->route('payroll.index')->with('status', 'Draft payroll dibuat. Catatan unik per karyawan dan periode untuk mencegah pembayaran ganda.');
    }

    public function pay(Request $request, Payroll $payroll, CashLedgerService $cash): RedirectResponse
    {
        $data = $request->validate(['metode_pembayaran' => ['required', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])]]);
        if ($data['metode_pembayaran'] === 'CASH' && $request->user()->role?->name === 'KASIR'
            && ! KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift sebelum membayar gaji secara tunai.']);
        }
        DB::transaction(function () use ($request, $payroll, $data, $cash): void {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            if ($locked->status !== 'DRAFT' || $locked->pengeluaran_id) {
                throw ValidationException::withMessages(['status' => 'Payroll ini sudah dibayar atau dibatalkan.']);
            }
            $employee = User::query()->findOrFail($locked->employee_id);
            $expense = Pengeluaran::create([
                'tanggal' => now(), 'kode_beban' => 'PAY-'.Str::upper(Str::random(10)),
                'kategori_beban' => 'GAJI', 'keterangan' => 'Gaji '.$employee->name.' periode '.$locked->periode,
                'nominal' => $locked->total, 'metode_bayar' => $data['metode_pembayaran'],
                'referensi_type' => 'PAYROLL', 'referensi_id' => $locked->id, 'user_id' => $request->user()->id,
            ]);
            if ($data['metode_pembayaran'] === 'CASH') {
                $cash->disbursement((float) $locked->total, $request->user()->id, 'payroll', $locked->id, 'Pembayaran gaji '.$employee->name.' · '.$locked->periode, 'PEMBAYARAN');
            }
            $locked->forceFill([
                'tanggal_pembayaran' => now(), 'metode_pembayaran' => $data['metode_pembayaran'],
                'status' => 'PAID', 'pengeluaran_id' => $expense->id,
            ])->save();
        });
        return back()->with('status', 'Payroll dibayar dan dicatat sebagai beban. Pembayaran tunai juga masuk ke kas keluar.');
    }

    public function cancel(Payroll $payroll): RedirectResponse
    {
        DB::transaction(function () use ($payroll): void {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'DRAFT', 409, 'Payroll yang telah dibayar tidak dapat dibatalkan dari halaman ini.');
            $locked->forceFill(['status' => 'CANCELLED'])->save();
        });

        return back()->with('status', 'Draft payroll dibatalkan.');
    }
}
