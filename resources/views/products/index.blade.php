<x-dashboard-layout title="Produk">
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">Master barang dan harga jual.</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Daftar Produk</h2>
        </div>
        <div class="flex gap-2">
            @if (auth()->user()->hasPermission('create_products'))
                <a href="{{ route('products.create') }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Tambah Produk</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('products.index') }}" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_160px_auto]">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode, nama, atau merek" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
        <select name="status" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Semua status</option>
            <option value="AKTIF" @selected(request('status') === 'AKTIF')>Aktif</option>
            <option value="NONAKTIF" @selected(request('status') === 'NONAKTIF')>Nonaktif</option>
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Produk</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Stok</th><th class="px-5 py-3">Harga Jual</th>@if (auth()->user()->hasPermission('view_profit_loss'))<th class="px-5 py-3">Harga Beli</th>@endif<th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4"><div class="font-semibold text-slate-900">{{ $product->nama_produk }}</div><div class="mt-0.5 text-xs text-slate-500">{{ $product->kode_produk }}{{ $product->spesifikasiRingkas() ? ' · '.$product->spesifikasiRingkas() : '' }}</div></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $product->jenis_produk }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span @class(['font-semibold', 'text-amber-700' => $product->stok <= $product->stok_minimum, 'text-slate-700' => $product->stok > $product->stok_minimum])>{{ number_format((float) $product->stok, 0, ',', '.') }} {{ $product->satuan }}</span><span class="block text-xs text-slate-400">Minimum {{ number_format((float) $product->stok_minimum, 0, ',', '.') }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-700">Rp {{ number_format((float) $product->harga_jual, 0, ',', '.') }}</td>
                            @if (auth()->user()->hasPermission('view_profit_loss'))<td class="whitespace-nowrap px-5 py-4 text-slate-600">Rp {{ number_format((float) $product->harga_beli, 0, ',', '.') }}</td>@endif
                            <td class="px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $product->status === 'AKTIF', 'bg-slate-100 text-slate-600' => $product->status !== 'AKTIF'])>{{ $product->status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                @if (auth()->user()->hasPermission('edit_products'))<a href="{{ route('products.edit', $product) }}" class="font-semibold text-blue-700 hover:text-blue-900">Edit</a>@endif
                                @if (auth()->user()->hasPermission('delete_products'))<form method="POST" action="{{ route('products.destroy', $product) }}" class="inline" onsubmit="return confirm('Hapus produk ini? Produk dengan stok atau riwayat transaksi akan ditolak.');">@csrf @method('DELETE')<button class="ml-3 font-semibold text-rose-700 hover:text-rose-900">Hapus</button></form>@endif
                                @unless (auth()->user()->hasAnyPermission(['edit_products', 'delete_products']))<span class="text-slate-400">—</span>@endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->hasPermission('view_profit_loss') ? 7 : 6 }}" class="px-5 py-14 text-center"><div class="font-semibold text-slate-800">Produk belum tersedia</div><p class="mt-1 text-sm text-slate-500">Ubah filter atau tambahkan produk pertama.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $products->links() }}</div>@endif
    </div>
</x-dashboard-layout>
