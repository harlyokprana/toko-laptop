<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PelangganController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pelanggan::query()->withCount(['penjualan', 'service'])->orderBy('nama');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('kode_pelanggan', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('status'), ['AKTIF', 'NONAKTIF'], true)) {
            $query->where('status', $request->query('status'));
        }

        return view('master-data.customers.index', [
            'customers' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.customers.form', ['customer' => new Pelanggan(['status' => 'AKTIF'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Pelanggan::create($this->validatedData($request));

        return redirect()->route('customers.show', $customer)->with('status', 'Pelanggan berhasil ditambahkan.');
    }
    public function quickStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $customer = Pelanggan::create([
            'kode_pelanggan' => 'C-'.str_pad((int) (Pelanggan::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT),
            'nama' => $data['nama'],
            'telepon' => $data['telepon'] ?? null,
            'email' => $data['email'] ?? null,
            'status' => 'AKTIF',
        ]);

        return response()->json([
            'id' => $customer->id,
            'kode_pelanggan' => $customer->kode_pelanggan,
            'nama' => $customer->nama,
            'telepon' => $customer->telepon,
        ]);
    }



    public function show(Pelanggan $customer): View
    {
        $customer->loadCount(['penjualan', 'service']);

        return view('master-data.customers.show', compact('customer'));
    }

    public function edit(Pelanggan $customer): View
    {
        return view('master-data.customers.form', compact('customer'));
    }

    public function update(Request $request, Pelanggan $customer): RedirectResponse
    {
        $customer->update($this->validatedData($request, $customer));

        return redirect()->route('customers.show', $customer)->with('status', 'Data pelanggan berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Pelanggan $customer = null): array
    {
        return $request->validate([
            'kode_pelanggan' => ['required', 'string', 'max:30', Rule::unique('pelanggan', 'kode_pelanggan')->ignore($customer)],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }
}
