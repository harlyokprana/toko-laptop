<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProdukController extends Controller
{
    public function index(Request $request): View
    {
        $query = Produk::query()->orderBy('nama_produk');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('kode_produk', 'like', "%{$search}%")
                    ->orWhere('nama_produk', 'like', "%{$search}%")
                    ->orWhere('merek', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenis_produk')) {
            $query->where('jenis_produk', $request->string('jenis_produk'));
        }

        if (in_array($request->query('status'), ['AKTIF', 'NONAKTIF'], true)) {
            $query->where('status', $request->query('status'));
        }

        return view('products.index', [
            'products' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('products.form-new', [
            'product' => new Produk(['satuan' => 'PCS', 'status' => 'AKTIF']),
            'brands' => $this->brands(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Produk::create($this->validatedData($request));

        return redirect()->route('products.index')->with('status', 'Produk berhasil ditambahkan. Stok awal produk adalah 0.');
    }

    public function edit(Produk $product): View
    {
        return view('products.form-new', [
            'product' => $product,
            'brands' => $this->brands(),
        ]);
    }

    /** @return array<int, string> */
    private function brands(): array
    {
        return collect(['Acer', 'Asus', 'Dell', 'HP', 'Lenovo', 'MSI', 'Apple', 'Toshiba', 'Samsung'])
            ->merge(
                Produk::query()
                    ->whereNotNull('merek')
                    ->where('merek', '!=', '')
                    ->distinct()
                    ->orderBy('merek')
                    ->pluck('merek')
            )
            ->map(fn (string $brand) => trim($brand))
            ->filter()
            ->unique(fn (string $brand) => mb_strtolower($brand))
            ->sort()
            ->values()
            ->all();
    }

    public function update(Request $request, Produk $product): RedirectResponse
    {
        $product->update($this->validatedData($request, $product));

        return redirect()->route('products.index')->with('status', 'Perubahan produk berhasil disimpan.');
    }

    public function destroy(Produk $product): RedirectResponse
    {
        $hasHistory = $product->laptop()->exists()
            || $product->pembelianDetail()->exists()
            || $product->penjualanDetail()->exists()
            || $product->serviceDetail()->exists()
            || $product->stokMutasi()->exists();

        if ($hasHistory || (float) $product->stok !== 0.0) {
            return back()->withErrors([
                'product' => 'Produk dengan stok atau riwayat transaksi tidak dapat dihapus. Ubah statusnya menjadi nonaktif.',
            ]);
        }

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Produk berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Produk $product = null): array
    {
        $jenis = (string) $request->input('jenis_produk');
        $validJenis = ['LAPTOP', 'AKSESORIS', 'SPAREPART', 'PC_RAKITAN'];

        $rules = [
            'jenis_produk' => ['required', Rule::in($validJenis)],
            'kode_produk' => ['nullable', 'string', 'max:50'],
            'nama_produk' => ['required', 'string', 'max:255'],
            'merek' => ['nullable', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:30'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
            'atribut' => ['nullable', 'array'],
            'atribut.processor' => ['nullable', 'string', 'max:255'],
            'atribut.ram' => ['nullable', 'string', 'max:50'],
            'atribut.storage' => ['nullable', 'string', 'max:100'],
            'atribut.ukuran_layar' => ['nullable', 'string', 'max:50'],
            'atribut.kondisi' => ['nullable', Rule::in(['Baru', 'Bekas'])],
            'atribut.jenis_aksesori' => ['nullable', 'string', 'max:100'],
            'atribut.jenis_aksesori_custom' => ['nullable', 'string', 'max:100'],
            'atribut.spesifikasi_singkat' => ['nullable', 'string', 'max:2000'],
            'atribut.motherboard' => ['nullable', 'string', 'max:255'],
            'atribut.ukuran_lcd' => ['nullable', 'string', 'max:50'],
        ];

        if ($jenis === 'LAPTOP') {
            $rules['merek'] = ['required', 'string', 'max:255'];
            $rules['tipe'] = ['required', 'string', 'max:255'];
        } elseif ($jenis === 'AKSESORIS') {
            $rules['atribut.jenis_aksesori'] = ['required', 'string', 'max:100'];
        } elseif ($jenis === 'SPAREPART') {
            $rules['merek'] = ['required', 'string', 'max:255'];
            $rules['tipe'] = ['required', 'string', 'max:255'];
        } elseif ($jenis === 'PC_RAKITAN') {
            $rules['merek'] = ['required', 'string', 'max:255'];
            $rules['atribut.motherboard'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $atribut = is_array($data['atribut'] ?? null) ? $data['atribut'] : [];
        if (($atribut['jenis_aksesori'] ?? '') === 'Lainnya' && ! empty($atribut['jenis_aksesori_custom'])) {
            $atribut['jenis_aksesori'] = $atribut['jenis_aksesori_custom'];
        }
        unset($atribut['jenis_aksesori_custom']);
        $data['atribut'] = $atribut ?: null;

        if (! $product) {
            $data['kode_produk'] = $this->nextSku($jenis);
            $data['kategori_id'] = $this->categoryFor($jenis)->id;
            $data['stok'] = 0;
        } else {
            unset($data['kode_produk']);
        }

        return $data;
    }

    private function categoryFor(string $jenis): Kategori
    {
        $categoryType = $jenis === 'PC_RAKITAN' ? 'LAINNYA' : $jenis;
        $category = Kategori::query()
            ->where('jenis', $categoryType)
            ->where('status', 'AKTIF')
            ->first();

        if ($category) {
            return $category;
        }

        $categoryNames = [
            'LAPTOP' => 'Laptop',
            'AKSESORIS' => 'Aksesori',
            'SPAREPART' => 'Sparepart',
            'PC_RAKITAN' => 'PC Rakitan',
        ];

        return Kategori::query()->firstOrCreate(
            ['nama' => $categoryNames[$jenis]],
            ['jenis' => $categoryType, 'status' => 'AKTIF'],
        );
    }

    private function nextSku(string $jenis): string
    {
        $prefix = match ($jenis) {
            'LAPTOP' => 'LAP',
            'AKSESORIS' => 'ACC',
            'SPAREPART' => 'SPR',
            'PC_RAKITAN' => 'PCR',
            default => 'PRD',
        };

        $sequence = 1;
        $last = Produk::query()
            ->where('jenis_produk', $jenis)
            ->where('kode_produk', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('kode_produk');

        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $code = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Produk::query()->where('kode_produk', $code)->exists());

        return $code;
    }
}
