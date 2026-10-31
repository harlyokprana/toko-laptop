<x-dashboard-layout title="Kategori">
    <div class="mb-6"><a href="{{ route('products.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">← Kembali ke produk</a><h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-950">Kategori Produk</h2><p class="mt-1 text-sm text-slate-500">Kelola pengelompokan produk.</p></div>

    @if (session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif

    @if (auth()->user()->hasPermission('create_products'))
        <form method="POST" action="{{ route('categories.store') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-[1fr_200px_1fr_auto] sm:items-end">@csrf
            <label class="text-sm font-medium text-slate-700">Nama kategori<input name="nama" value="{{ old('nama') }}" maxlength="100" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"></label>
            <label class="text-sm font-medium text-slate-700">Jenis<select name="jenis" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">@foreach (['LAPTOP', 'AKSESORIS', 'SPAREPART', 'PC_RAKITAN', 'INVENTARIS', 'LAINNYA'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-slate-700">Deskripsi<input name="deskripsi" value="{{ old('deskripsi') }}" maxlength="2000" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"></label>
            <input type="hidden" name="status" value="AKTIF"><button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Tambah Kategori</button>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($categories as $category)
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                @if (auth()->user()->hasPermission('edit_products'))
                    <form method="POST" action="{{ route('categories.update', $category) }}" class="grid gap-4 sm:grid-cols-[1fr_180px_1fr_140px_auto] sm:items-end">@csrf @method('PUT')
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama<input name="nama" value="{{ $category->nama }}" maxlength="100" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-medium normal-case tracking-normal text-slate-800 focus:border-blue-500 focus:ring-blue-500"></label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis<select name="jenis" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-medium normal-case tracking-normal text-slate-800 focus:border-blue-500 focus:ring-blue-500">@foreach (['LAPTOP', 'AKSESORIS', 'SPAREPART', 'PC_RAKITAN', 'INVENTARIS', 'LAINNYA'] as $type)<option value="{{ $type }}" @selected($category->jenis === $type)>{{ $type }}</option>@endforeach</select></label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi<input name="deskripsi" value="{{ $category->deskripsi }}" maxlength="2000" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-normal normal-case tracking-normal text-slate-800 focus:border-blue-500 focus:ring-blue-500"></label>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status<select name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-medium normal-case tracking-normal text-slate-800 focus:border-blue-500 focus:ring-blue-500"><option value="AKTIF" @selected($category->status === 'AKTIF')>Aktif</option><option value="NONAKTIF" @selected($category->status === 'NONAKTIF')>Nonaktif</option></select></label>
                        <div class="flex items-center gap-3"><button class="text-sm font-semibold text-blue-700 hover:text-blue-900">Simpan</button>@if (auth()->user()->hasPermission('delete_products'))<button form="delete-category-{{ $category->id }}" class="text-sm font-semibold text-rose-700 hover:text-rose-900">Hapus</button>@endif</div>
                    </form>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><div class="font-semibold text-slate-900">{{ $category->nama }}</div><div class="mt-1 text-xs text-slate-500">{{ $category->jenis }} · {{ $category->produk_count }} produk</div></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $category->status }}</span></div>
                @endif
                @if (auth()->user()->hasPermission('delete_products'))
                    <form id="delete-category-{{ $category->id }}" method="POST" action="{{ route('categories.destroy', $category) }}" class="hidden">@csrf @method('DELETE')</form>
                @endif
                @if (auth()->user()->hasPermission('edit_products'))<p class="mt-3 text-xs text-slate-400">{{ $category->produk_count }} produk dalam kategori ini</p>@endif
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">Belum ada kategori produk.</div>
        @endforelse
    </div>
    @if ($categories->hasPages())<div class="mt-5">{{ $categories->links() }}</div>@endif
</x-dashboard-layout>
