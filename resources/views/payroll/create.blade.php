<x-dashboard-layout title="Buat Payroll">
    <div class="mx-auto max-w-2xl"><a href="{{ route('payroll.index') }}" class="text-xs font-semibold text-blue-700">← Payroll</a><h1 class="mt-3 text-2xl font-bold">Buat Draft Payroll</h1><p class="mt-1 text-sm text-slate-500">Total dihitung server: gaji pokok + tunjangan + bonus − potongan. Pembayaran dicatat sebagai pengeluaran.</p>
        @if($errors->any())<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('payroll.store') }}" class="mt-5 space-y-4 rounded-2xl border bg-white p-5">@csrf
            <label class="block text-sm font-medium">Karyawan<select name="employee_id" required class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Pilih karyawan</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></label>
            <label class="block text-sm font-medium">Periode<input type="month" name="periode" required value="{{ old('periode', now()->format('Y-m')) }}" class="mt-1 block w-full rounded-lg border-slate-300"></label>
            <div class="grid gap-3 sm:grid-cols-2">@foreach(['gaji_pokok' => 'Gaji Pokok', 'tunjangan' => 'Tunjangan', 'bonus' => 'Bonus', 'potongan' => 'Potongan'] as $name => $label)<label class="block text-sm font-medium">{{ $label }} (Rp)<input type="number" name="{{ $name }}" min="0" step="0.01" @required($name === 'gaji_pokok') value="{{ old($name, 0) }}" class="mt-1 block w-full rounded-lg border-slate-300"></label>@endforeach</div>
            <label class="block text-sm font-medium">Catatan<textarea name="catatan" rows="2" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('catatan') }}</textarea></label>
            <div class="flex justify-end"><button class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Simpan Draft</button></div>
        </form>
    </div>
</x-dashboard-layout>
