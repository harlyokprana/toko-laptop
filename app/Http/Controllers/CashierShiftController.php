<?php

namespace App\Http\Controllers;

use App\Models\KasMutasi;
use App\Models\KasirShift;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierShiftController extends Controller
{
    public function index(Request $request): View
    {
        $activeShift = KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->latest('opened_at')->first();
        $expectedCash = $activeShift ? $this->expectedCash($activeShift) : null;

        return view('cash.shifts.index', [
            'activeShift' => $activeShift,
            'expectedCash' => $expectedCash,
            'shifts' => KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'CLOSED')->latest('closed_at')->paginate(10),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            if (KasirShift::query()->where('user_id', $user->id)->where('status', 'OPEN')->exists()) {
                throw ValidationException::withMessages(['shift' => 'Anda sudah memiliki shift yang masih terbuka.']);
            }

            KasirShift::create([
                'user_id' => $user->id, 'opened_at' => now(), 'opening_cash' => $data['opening_cash'],
                'opening_note' => $data['opening_note'] ?? null, 'status' => 'OPEN',
            ]);
        });

        return back()->with('status', 'Shift berhasil dibuka.');
    }

    public function close(Request $request, KasirShift $shift): RedirectResponse
    {
        abort_unless($shift->user_id === $request->user()->id, 404);
        abort_unless($shift->status === 'OPEN', 409, 'Shift ini sudah ditutup.');

        $data = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($shift, $request, $data): void {
            $locked = KasirShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($locked->status !== 'OPEN') {
                throw ValidationException::withMessages(['shift' => 'Shift ini sudah ditutup.']);
            }
            $expected = $this->expectedCash($locked);
            $closing = (float) $data['closing_cash'];
            $locked->forceFill([
                'closed_at' => now(), 'expected_cash' => $expected, 'closing_cash' => $closing,
                'cash_difference' => round($closing - $expected, 2),
                'closing_note' => $data['closing_note'] ?? null, 'status' => 'CLOSED',
            ])->save();
        });

        return back()->with('status', 'Shift ditutup dan selisih kas tersimpan.');
    }

    private function expectedCash(KasirShift $shift): float
    {
        $movement = KasMutasi::query()->where('user_id', $shift->user_id)
            ->where('created_at', '>=', $shift->opened_at)
            ->selectRaw('COALESCE(SUM(debit - kredit), 0) as total')->value('total');

        return round((float) $shift->opening_cash + (float) $movement, 2);
    }
}
