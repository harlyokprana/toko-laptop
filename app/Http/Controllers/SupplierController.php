<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $query = Supplier::query()->withCount(['pembelian', 'laptop'])->orderBy('nama');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('kode_supplier', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('status'), ['AKTIF', 'NONAKTIF'], true)) {
            $query->where('status', $request->query('status'));
        }

        return view('master-data.suppliers.index', [
            'suppliers' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.suppliers.form', ['supplier' => new Supplier(['status' => 'AKTIF'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validatedData($request));

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier berhasil ditambahkan.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->loadCount(['pembelian', 'laptop']);

        return view('master-data.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('master-data.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validatedData($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Data supplier berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'kode_supplier' => ['required', 'string', 'max:30', Rule::unique('supplier', 'kode_supplier')->ignore($supplier)],
            'nama' => ['required', 'string', 'max:255'],
            'kontak' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }
}
