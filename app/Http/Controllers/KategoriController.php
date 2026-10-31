<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Kategori::query()->withCount('produk')->orderBy('nama')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Kategori::create($this->validatedData($request));

        return redirect()->route('categories.index')->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Kategori $category): RedirectResponse
    {
        $category->update($this->validatedData($request, $category));

        return redirect()->route('categories.index')->with('status', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Kategori $category): RedirectResponse
    {
        if ($category->produk()->exists()) {
            return back()->withErrors([
                'category' => 'Kategori masih digunakan oleh produk dan tidak dapat dihapus.',
            ]);
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Kategori berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Kategori $category = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('kategori', 'nama')->ignore($category)],
            'jenis' => ['required', Rule::in(['LAPTOP', 'AKSESORIS', 'SPAREPART', 'PC_RAKITAN', 'INVENTARIS', 'LAINNYA'])],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);
    }
}
