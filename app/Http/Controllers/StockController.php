<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\StokMutasi;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $query = Produk::query()
            ->orderByRaw('CASE WHEN status = \'AKTIF\' AND stok <= stok_minimum THEN 0 ELSE 1 END')
            ->orderBy('nama_produk');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('kode_produk', 'like', "%{$search}%")
                    ->orWhere('nama_produk', 'like', "%{$search}%")
                    ->orWhere('merek', 'like', "%{$search}%");
            });
        }

        if ($request->query('stock') === 'low') {
            $query->where('status', 'AKTIF')->whereColumn('stok', '<=', 'stok_minimum');
        }

        $products = $query->paginate(15)->withQueryString();

        return view('inventory.index', [
            'products' => $products,
            'lowStockCount' => Produk::query()->where('status', 'AKTIF')->whereColumn('stok', '<=', 'stok_minimum')->count(),
            'skuCount' => Produk::query()->where('status', 'AKTIF')->count(),
        ]);
    }

    public function createAdjustment(): View
    {
        return view('inventory.adjustment', [
            'products' => Produk::query()->where('status', 'AKTIF')->orderBy('nama_produk')->get(['id', 'kode_produk', 'nama_produk', 'satuan', 'stok']),
        ]);
    }

    public function storeAdjustment(Request $request, StockService $stockService): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:produk,id'],
            'direction' => ['required', Rule::in(['MASUK', 'KELUAR'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $mutation = $data['direction'] === 'MASUK'
            ? $stockService->increase($data['product_id'], $data['quantity'], 'ADJUSTMENT', $request->user()->id, $data['note'])
            : $stockService->decrease($data['product_id'], $data['quantity'], 'ADJUSTMENT', $request->user()->id, $data['note']);

        return redirect()->route('stock.products.show', $mutation->produk_id)
            ->with('status', 'Penyesuaian stok berhasil dicatat.');
    }

    public function show(Produk $product): View
    {


        $mutations = StokMutasi::query()
            ->with('user:id,name')
            ->where('produk_id', $product->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20);

        return view('inventory.show', compact('product', 'mutations'));
    }
}
