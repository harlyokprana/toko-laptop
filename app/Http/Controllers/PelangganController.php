<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Services\PelangganAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return view('master-data.customers.form', [
            'customer' => new Pelanggan(['status' => 'AKTIF', 'kode_pelanggan' => Pelanggan::generateCode()]),
        ]);
    }

    public function store(Request $request, PelangganAccountService $accounts): RedirectResponse
    {
        $data = $this->validatedData($request);
        $customer = DB::transaction(function () use ($data, $accounts): Pelanggan {
            $customer = Pelanggan::create($data);
            $accounts->ensureFor($customer);

            return $customer;
        });

        return redirect()->route('customers.show', $customer)->with('status', 'Pelanggan berhasil ditambahkan.');
    }

    public function quickStore(Request $request, PelangganAccountService $accounts): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'telepon' => ['required', 'string', 'max:30', Rule::unique('pelanggan', 'telepon')],
            'email' => ['nullable', 'email', 'max:255'],
        ], ['telepon.unique' => 'Nomor telepon sudah digunakan pelanggan lain.']);

        $customer = DB::transaction(function () use ($data, $accounts): Pelanggan {
            $customer = Pelanggan::create([
                'nama' => $data['nama'],
                'telepon' => $data['telepon'],
                'email' => $data['email'] ?? null,
                'status' => 'AKTIF',
            ]);
            $accounts->ensureFor($customer);

            return $customer;
        });

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

    public function update(Request $request, Pelanggan $customer, PelangganAccountService $accounts): RedirectResponse
    {
        $data = $this->validatedData($request, $customer);

        DB::transaction(function () use ($customer, $data, $accounts): void {
            $customer->update($data);

            if (filled($customer->telepon) && ! $customer->user) {
                $accounts->ensureFor($customer);
            }
        });

        return redirect()->route('customers.show', $customer)->with('status', 'Data pelanggan berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Pelanggan $customer = null): array
    {
        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telepon' => [$customer ? 'nullable' : 'required', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ];

        $phoneRule = Rule::unique('pelanggan', 'telepon');
        if ($customer) {
            $phoneRule->ignore($customer);
            $rules['kode_pelanggan'] = ['required', 'string', 'max:30', Rule::unique('pelanggan', 'kode_pelanggan')->ignore($customer)];
        }
        $rules['telepon'][] = $phoneRule;

        $data = $request->validate($rules, [
            'telepon.required' => 'Nomor telepon wajib diisi untuk membuat akun pelanggan.',
            'telepon.unique' => 'Nomor telepon sudah digunakan pelanggan lain.',
        ]);

        if (! $customer) {
            $data['kode_pelanggan'] = Pelanggan::generateCode();
        }

        return $data;
    }
}
