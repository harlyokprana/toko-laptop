<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\Produk;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaptopController extends Controller
{
    private const EDITABLE_STATUSES = ['READY', 'REPAIR', 'REJECT', 'DISPLAY', 'DIPAKAI_INTERNAL'];

    public function index(Request $request): View
    {
        $query = Laptop::query()->with(['supplier:id,nama'])->latest('tanggal_perolehan');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('sku', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('merek', 'like', "%{$search}%")
                    ->orWhere('model_seri', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('status'), ['READY', 'SOLD', 'REPAIR', 'REJECT', 'DISPLAY', 'DIPAKAI_INTERNAL'], true)) {
            $query->where('status', $request->query('status'));
        }

        return view('master-data.laptops.index', [
            'laptops' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.laptops.form', [
            'laptop' => new Laptop(['status' => 'READY']),
            'suppliers' => Supplier::query()->where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama']),
            'products' => Produk::query()->where('jenis_produk', 'LAPTOP')->orderBy('nama_produk')->get(['id', 'kode_produk', 'nama_produk']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $laptop = Laptop::create($this->validatedData($request));

        return redirect()->route('laptops.show', $laptop)->with('status', 'Data laptop berhasil ditambahkan.');
    }

    public function show(Laptop $laptop): View
    {
        $laptop->load(['supplier:id,nama']);

        return view('master-data.laptops.show', compact('laptop'));
    }

    public function edit(Laptop $laptop): View
    {
        abort_if($laptop->status === 'SOLD', 403, 'Laptop yang sudah terjual tidak dapat diedit dari master laptop.');

        return view('master-data.laptops.form', [
            'laptop' => $laptop,
            'suppliers' => Supplier::query()->orderBy('nama')->get(['id', 'nama']),
            'products' => Produk::query()
                ->where('jenis_produk', 'LAPTOP')
                ->when($laptop->produk_id, fn ($query) => $query->orWhereKey($laptop->produk_id))
                ->orderBy('nama_produk')
                ->get(['id', 'kode_produk', 'nama_produk']),
        ]);
    }

    public function update(Request $request, Laptop $laptop): RedirectResponse
    {
        abort_if($laptop->status === 'SOLD', 403, 'Laptop yang sudah terjual tidak dapat diedit dari master laptop.');

        $laptop->update($this->validatedData($request, $laptop));

        return redirect()->route('laptops.show', $laptop)->with('status', 'Data laptop berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Laptop $laptop = null): array
    {
        $rules = [
            'produk_id' => ['nullable', 'integer', 'exists:produk,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:supplier,id'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('laptop', 'sku')->ignore($laptop)],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('laptop', 'serial_number')->ignore($laptop)],
            'tanggal_perolehan' => ['nullable', 'date'],
            'merek' => ['nullable', 'string', 'max:255'],
            'model_seri' => ['nullable', 'string', 'max:255'],
            'kondisi' => ['required', Rule::in(['Baru', 'Seken'])],
            'processor' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:20'],
            'storage_jenis' => ['nullable', Rule::in(['HDD', 'SSD SATA', 'SSD NVMe', 'eMMC'])],
            'storage_kapasitas' => ['nullable', 'string', 'max:30'],
            'gpu' => ['nullable', 'string', 'max:255'],
            'ukuran_layar' => ['nullable', 'string', 'max:20'],
            'resolusi_layar' => ['nullable', 'string', 'max:30'],
            'spesifikasi' => ['nullable', 'string', 'max:10000'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'status' => ['required', Rule::in(self::EDITABLE_STATUSES)],
        ];

        $canManageCosts = $request->user()->hasPermission('view_profit_loss');
        if ($canManageCosts) {
            $rules['hpp'] = ['nullable', 'numeric', 'min:0', 'decimal:0,2'];
            $rules['biaya_tambahan'] = ['nullable', 'numeric', 'min:0', 'decimal:0,2'];
        }

        $data = $request->validate($rules);

        // Assemble a human-readable spesifikasi from structured fields (backward compat)
        $parts = [];
        if (! empty($data['kondisi'])) {
            $parts[] = 'Kondisi: '.$data['kondisi'];
        }
        if (! empty($data['processor'])) {
            $parts[] = 'Processor: '.$data['processor'];
        }
        if (! empty($data['ram'])) {
            $parts[] = 'RAM: '.$data['ram'];
        }
        if (! empty($data['storage_jenis']) || ! empty($data['storage_kapasitas'])) {
            $parts[] = 'Storage: '.trim(($data['storage_jenis'] ?? '').' '.($data['storage_kapasitas'] ?? ''));
        }
        if (! empty($data['gpu'])) {
            $parts[] = 'GPU: '.$data['gpu'];
        }
        if (! empty($data['ukuran_layar'])) {
            $parts[] = 'Layar: '.$data['ukuran_layar'].(! empty($data['resolusi_layar']) ? ' ('.$data['resolusi_layar'].')' : '');
        } elseif (! empty($data['resolusi_layar'])) {
            $parts[] = 'Resolusi: '.$data['resolusi_layar'];
        }
        $data['spesifikasi'] = trim(implode("\n", $parts));

        if ($canManageCosts) {
            $data['hpp'] = $data['hpp'] ?? $laptop?->hpp ?? 0;
            $data['biaya_tambahan'] = $data['biaya_tambahan'] ?? $laptop?->biaya_tambahan ?? 0;
        }

        return $data;
    }
}
