<x-dashboard-layout title="Transaksi Kasir">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-900 text-white"><x-nav-icon name="cart" class="h-6 w-6"/></span>
            <div><p class="text-xs font-medium text-slate-500">Kasir · POS</p><h1 class="text-xl font-bold tracking-tight text-slate-950">Transaksi Kasir</h1><p class="text-xs text-slate-500">Pilih produk, atur pembayaran, lalu simpan transaksi.</p></div>
        </div>
        <div class="flex items-center gap-2">
            @if($requiresShift && ! $openShift)
                <span class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">Shift belum dibuka</span>
                <a href="{{ route('cash.shifts.index') }}" class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white">Buka Shift</a>
            @elseif($openShift)
                <span class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">Shift aktif · {{ $openShift->opened_at->format('H:i') }}</span>
                <a href="{{ route('cash.shifts.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Shift & Kas</a>
            @endif
            <a href="{{ route('module.sales') }}" class="hidden rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 sm:inline-flex">Daftar Penjualan</a>
        </div>
    </div>

    @if(session('status'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><strong>Transaksi belum dapat disimpan.</strong> {{ $errors->first() }}</div>@endif

    <div id="pos-toast" role="status" aria-live="polite" class="pointer-events-none fixed right-5 top-24 z-[70] hidden rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-xl"></div>

    <div class="grid items-start gap-3 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.04fr)]">
        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-900/[0.04]">
            <div class="border-b border-slate-100 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="text-sm font-bold text-slate-900">Pilih Produk</h2><p class="mt-0.5 text-xs text-slate-500">Klik baris produk atau scan barcode untuk menambahkan ke keranjang.</p></div>
                    <label class="relative w-full sm:w-60"><span class="sr-only">Urutkan produk</span><x-nav-icon name="filter" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"/><select id="catalog-sort" class="h-9 w-full rounded-lg border-slate-200 bg-white pl-9 pr-2 text-xs text-slate-700 focus:border-blue-500 focus:ring-blue-500"><option value="name">Nama A–Z</option><option value="price-asc">Harga termurah</option><option value="price-desc">Harga tertinggi</option><option value="stock-desc">Stok terbanyak</option><option value="newest">Terbaru</option></select></label>
                </div>
                <div class="mt-3 flex gap-1.5 overflow-x-auto pb-1" role="group" aria-label="Filter kategori">
                    @foreach(['ALL' => 'Semua', 'LAPTOP' => 'Laptop', 'AKSESORIS' => 'Aksesoris', 'SPAREPART' => 'Komponen', 'SOFTWARE' => 'Software', 'SERVICE' => 'Service', 'OTHER' => 'Lainnya'] as $type => $label)
                        <button type="button" data-category="{{ $type }}" @class(['category-filter shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition', 'bg-amber-300 text-slate-950' => $type === 'ALL', 'bg-slate-100 text-slate-600 hover:bg-amber-50 hover:text-slate-950' => $type !== 'ALL'])>{{ $label }}</button>
                    @endforeach
                </div>
                <label class="relative mt-2 block"><span class="sr-only">Cari produk</span><x-nav-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"/><input id="catalog-search" type="search" autocomplete="off" placeholder="Cari produk, scan barcode, atau ketik kode… (F2)" class="h-11 w-full rounded-lg border-slate-200 bg-slate-50 pl-10 pr-12 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-blue-500"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><x-nav-icon name="barcode" class="h-5 w-5"/></span></label>
            </div>
            <div class="max-h-[calc(100vh-330px)] min-h-[360px] overflow-auto">
                <table class="min-w-[680px] w-full text-left text-xs sm:text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] font-semibold text-slate-600"><tr><th class="px-3 py-3">Kode</th><th class="px-3 py-3">Nama Produk</th><th class="px-3 py-3">Kategori</th><th class="px-3 py-3 text-right">Stok</th><th class="px-3 py-3 text-right">Harga Jual</th><th class="w-8 px-2"></th></tr></thead>
                    <tbody id="catalog-rows" class="divide-y divide-slate-100">
                        @forelse($catalog as $item)
                            @php $outOfStock = $item['stock'] < 1; $lowStock = ! $outOfStock && $item['stock'] <= $item['minimum_stock']; @endphp
                            <tr tabindex="0" role="button" aria-label="{{ $outOfStock ? 'Stok habis' : 'Pilih' }} {{ $item['display_name'] }} {{ $item['serial'] ? 'serial '.$item['serial'] : '' }}" class="catalog-row group transition {{ $outOfStock ? 'cursor-not-allowed bg-slate-50/70 text-slate-400' : 'cursor-pointer text-slate-800 hover:bg-blue-50 focus:bg-blue-50' }}" data-search="{{ strtolower($item['code'].' '.$item['display_name'].' '.$item['category'].' '.($item['serial'] ?? '')) }}" data-category="{{ $item['category_type'] }}" data-product-id="{{ $item['product_id'] }}" data-laptop-id="{{ $item['laptop_id'] ?? '' }}" data-code="{{ $item['code'] }}" data-name="{{ $item['display_name'] }}" data-category-name="{{ $item['category'] }}" data-price="{{ $item['price'] }}" data-stock="{{ $item['stock'] }}" data-minimum-stock="{{ $item['minimum_stock'] }}" data-serial="{{ $item['serial'] ?? '' }}" data-sort-name="{{ strtolower($item['display_name']) }}" data-sort-price="{{ $item['price'] }}" data-sort-stock="{{ $item['stock'] }}" data-sort-newest="{{ $item['product_id'] }}">
                                <td class="whitespace-nowrap px-3 py-3 font-medium">{{ $item['code'] }}@if($item['serial'])<span class="mt-0.5 block text-[10px] text-slate-400">SN {{ $item['serial'] }}</span>@endif</td>
                                <td class="min-w-44 px-3 py-3"><span class="font-medium {{ $outOfStock ? 'text-slate-400' : 'text-slate-900' }}">{{ $item['name'] }}</span>@if($item['specification'])<span class="mt-0.5 block text-[10px] leading-4 text-slate-500">{{ $item['specification'] }}</span>@endif @if($outOfStock)<span class="ml-2 rounded-full bg-slate-200 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">Stok Habis</span>@elseif($lowStock)<span class="ml-2 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-800">Menipis</span>@endif</td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $item['category'] }}</td><td class="px-3 py-3 text-right">{{ number_format($item['stock'], 0, ',', '.') }}</td><td class="whitespace-nowrap px-3 py-3 text-right font-medium">Rp {{ number_format($item['price'], 0, ',', '.') }}</td><td class="px-2 py-3 text-right text-blue-600"><span class="text-lg">›</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-14 text-center text-slate-500">Belum ada produk aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div id="catalog-empty" class="hidden px-4 py-10 text-center text-sm text-slate-500">Produk yang dicari tidak ditemukan.</div>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-[11px] text-slate-500"><span>Menampilkan <strong id="catalog-count">{{ count($catalog) }}</strong> produk</span><span>Klik baris untuk memilih · F2 untuk mencari</span></div>
        </section>

        <form id="pos-form" method="POST" action="{{ route('sales.store') }}" class="min-w-0 space-y-3 2xl:sticky 2xl:top-[76px] 2xl:max-h-[calc(100vh-88px)] 2xl:overflow-y-auto">@csrf
            <input type="hidden" name="intent" id="transaction-intent" value="sale">
            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-900/[0.04]">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3"><div class="flex items-center gap-2"><x-nav-icon name="cart" class="h-5 w-5 text-slate-900"/><h2 class="text-base font-bold text-slate-900">Keranjang Transaksi <span class="text-sm font-medium text-slate-500">(<span id="cart-count">0</span> item)</span></h2></div><button id="clear-cart" type="button" class="rounded-lg border border-rose-200 px-3 py-2 text-[11px] font-semibold text-rose-700 hover:bg-rose-50">Hapus Semua</button></div>
                <div class="max-h-[330px] overflow-auto">
                    <table class="min-w-[640px] w-full text-left text-xs"><thead class="sticky top-0 bg-slate-50 text-[10px] font-semibold text-slate-600"><tr><th class="px-2.5 py-2.5">No</th><th class="px-2.5 py-2.5">Produk</th><th class="px-2.5 py-2.5 text-right">Harga</th><th class="px-2.5 py-2.5">Qty</th><th class="px-2.5 py-2.5">Diskon</th><th class="px-2.5 py-2.5 text-right">Subtotal</th><th class="px-2 py-2.5">Aksi</th></tr></thead><tbody id="cart-rows" class="divide-y divide-slate-100"><tr id="cart-empty"><td colspan="7" class="px-4 py-12 text-center text-xs text-slate-400">Klik produk di sebelah kiri untuk memulai transaksi.</td></tr></tbody></table>
                </div>
                <div id="cart-hidden-inputs"></div>
                <div class="grid gap-3 border-t border-slate-100 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(210px,0.72fr)]">
                    <div class="space-y-2 rounded-xl bg-slate-50 p-3">
                        <div class="flex items-center justify-between gap-2"><label for="discount-type" class="flex items-center gap-2 text-xs text-slate-600"><span class="grid h-6 w-6 place-items-center rounded bg-violet-100 font-bold text-violet-700">%</span>Diskon Transaksi</label><div class="flex gap-1"><select id="discount-type" name="diskon_tipe" @disabled(! $canDiscount) class="h-8 rounded-md border-slate-200 py-1 text-[11px]"><option value="PERSEN">Persentase (%)</option><option value="NOMINAL">Nominal (Rp)</option></select><input id="discount-value" name="diskon_transaksi" type="number" min="0" step="0.01" value="0" @disabled(! $canDiscount) class="h-8 w-20 rounded-md border-slate-200 px-2 text-xs"></div></div>
                        @unless($canDiscount)<p class="text-[10px] text-slate-500">Akses diskon tidak diberikan untuk role ini.</p>@endunless
                        <div class="flex items-center justify-between gap-2"><label for="tax-rate" class="flex items-center gap-2 text-xs text-slate-600"><span class="grid h-6 w-6 place-items-center rounded bg-sky-100 font-bold text-sky-700">%</span>Pajak (PPN)</label><div class="flex gap-1"><select id="tax-preset" class="h-8 rounded-md border-slate-200 py-1 text-[11px]"><option value="0">Tanpa pajak</option><option value="11" selected>PPN 11%</option><option value="custom">Persentase khusus</option></select><input id="tax-rate" name="pajak_persen" type="number" min="0" max="100" step="0.01" value="11" class="h-8 w-16 rounded-md border-slate-200 px-2 text-xs"><input id="tax-enabled" name="pajak_aktif" type="checkbox" value="1" checked class="sr-only"></div></div>
                        <div class="flex items-center justify-between gap-2"><label for="extra-fee" class="flex items-center gap-2 text-xs text-slate-600"><span class="grid h-6 w-6 place-items-center rounded bg-blue-100 font-bold text-blue-700">+</span>Biaya Tambahan</label><div class="flex gap-1"><select id="fee-label-select" name="biaya_tambahan_jenis" class="h-8 rounded-md border-slate-200 py-1 text-[11px]"><option value="LAINNYA">Lainnya</option><option value="ONGKIR">Ongkir</option><option value="BIAYA_LAYANAN">Biaya layanan</option></select><input id="extra-fee" name="biaya_tambahan" type="number" min="0" step="0.01" value="0" class="h-8 w-24 rounded-md border-slate-200 px-2 text-xs"></div></div>
                    </div>
                    <div class="space-y-2 px-1 text-xs"><div class="flex justify-between text-slate-500"><span>Subtotal</span><strong id="subtotal-label" class="text-slate-900">Rp 0</strong></div><div class="flex justify-between text-slate-500"><span>Diskon</span><strong id="discount-label" class="text-slate-900">Rp 0</strong></div><div class="flex justify-between text-slate-500"><span id="tax-title">Pajak (PPN 11%)</span><strong id="tax-label" class="text-slate-900">Rp 0</strong></div><div class="flex justify-between text-slate-500"><span>Biaya Tambahan</span><strong id="fee-label" class="text-slate-900">Rp 0</strong></div></div>
                </div>
                <div id="discount-warning" class="hidden px-4 pb-2 text-xs text-rose-700">Diskon transaksi tidak boleh melebihi subtotal.</div>
                <div class="mx-3 mb-3 flex items-center justify-between rounded-xl bg-gradient-to-r from-sky-50 to-blue-50 px-4 py-3 text-lg font-bold text-blue-700"><span>Total</span><span id="total-label" class="text-2xl">Rp 0</span></div>
                <div class="grid gap-3 border-t border-slate-100 px-3 py-3 sm:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                    <div><label class="mb-1.5 block text-xs font-medium text-slate-600" for="customer-search">Pelanggan <span class="text-slate-400">(wajib untuk piutang)</span></label><div class="relative"><input id="customer-search" type="search" autocomplete="off" placeholder="Pelanggan umum · cari nama/kode/telepon…" class="h-9 w-full rounded-lg border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500"><input id="customer-id" type="hidden" name="pelanggan_id"><div id="customer-options" class="absolute left-0 right-0 top-10 z-20 hidden max-h-52 overflow-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl">@foreach($customers as $customer)<button type="button" data-id="{{ $customer->id }}" data-search="{{ strtolower($customer->kode_pelanggan.' '.$customer->nama.' '.$customer->telepon) }}" data-label="{{ $customer->nama }}{{ $customer->telepon ? ' · '.$customer->telepon : '' }}" class="customer-option block w-full rounded-md px-3 py-2 text-left text-xs text-slate-700 hover:bg-blue-50">{{ $customer->nama }} <span class="text-slate-400">{{ $customer->kode_pelanggan }}{{ $customer->telepon ? ' · '.$customer->telepon : '' }}</span></button>@endforeach</div></div><p id="customer-selected" class="mt-1 hidden text-[10px] text-emerald-700"></p></div>
                    <label class="block text-xs font-medium text-slate-600" for="transaction-note">Catatan transaksi (opsional)<textarea id="transaction-note" name="keterangan" rows="2" maxlength="2000" placeholder="Catatan untuk invoice…" class="mt-1 block w-full resize-none rounded-lg border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500"></textarea></label>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-3 shadow-sm shadow-slate-900/[0.04] sm:p-4">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2"><x-nav-icon name="cash" class="h-5 w-5 text-slate-900"/><h2 class="text-sm font-bold text-slate-900">Metode Pembayaran <span class="font-medium text-slate-500">(Split Payment)</span></h2></div><button id="add-payment" type="button" class="rounded-lg border border-blue-200 px-3 py-2 text-[11px] font-semibold text-blue-700 hover:bg-blue-50">＋ Tambah Pembayaran</button></div>
                <div class="overflow-x-auto"><table class="min-w-[580px] w-full text-left text-xs"><thead class="bg-slate-50 text-[10px] font-semibold text-slate-600"><tr><th class="px-2.5 py-2">No</th><th class="px-2.5 py-2">Metode Pembayaran</th><th class="px-2.5 py-2">Nominal</th><th class="px-2.5 py-2">Keterangan / Referensi</th><th class="w-10 px-2 py-2">Aksi</th></tr></thead><tbody id="payment-rows" class="divide-y divide-slate-100"></tbody></table></div>
                <div id="payment-warning" class="mt-2 hidden rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>
                <div class="mt-3 grid gap-2 rounded-xl bg-gradient-to-r from-emerald-50 to-blue-50 p-3 sm:grid-cols-3"><div><p class="text-[10px] text-slate-500">Total Bayar</p><strong id="paid-label" class="text-base text-emerald-700">Rp 0</strong></div><div><p class="text-[10px] text-slate-500">Sisa Bayar</p><strong id="due-label" class="text-base text-slate-900">Rp 0</strong></div><div><p class="text-[10px] text-slate-500">Kembalian</p><strong id="change-label" class="text-base text-blue-700">Rp 0</strong></div></div>
                <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_1.35fr]"><button type="submit" data-intent="draft" class="rounded-lg bg-slate-100 px-3 py-3 text-xs font-semibold text-slate-700 hover:bg-slate-200">Simpan Draft</button><button type="submit" data-intent="receivable" @disabled($requiresShift && ! $openShift) class="rounded-lg bg-amber-50 px-3 py-3 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">Buat Piutang</button><button id="save-sale" type="submit" data-intent="sale" @disabled($requiresShift && ! $openShift) class="rounded-lg bg-blue-700 px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300">Simpan Transaksi <span class="ml-1 text-[10px] font-medium text-blue-100">F4</span></button></div>
                @if($requiresShift && ! $openShift)<p class="mt-2 text-center text-[11px] text-amber-800">Buka shift sebelum menyimpan transaksi atau piutang. Draft tetap dapat disimpan.</p>@endif
            </section>
        </form>
    </div>

    <script>
        (() => {
            const cart = [];
            const payments = [{ method: 'CASH', amount: 0, reference: '' }];
            const cartRows = document.getElementById('cart-rows');
            const hiddenInputs = document.getElementById('cart-hidden-inputs');
            const paymentRows = document.getElementById('payment-rows');
            const form = document.getElementById('pos-form');
            const money = value => 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round((Number(value) || 0) * 100) / 100);
            const escapeText = value => String(value ?? '');
            const toast = message => { const el = document.getElementById('pos-toast'); el.textContent = message; el.classList.remove('hidden'); clearTimeout(toast.timer); toast.timer = setTimeout(() => el.classList.add('hidden'), 2200); };
            let activeCategory = 'ALL';
            let amountTouched = false;
            let total = 0;
            let paid = 0;
            let balance = 0;
            let change = 0;

            function addHidden(parent, name, value) {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = escapeText(value); parent.appendChild(input);
            }
            function button(label, className, attributes = {}) {
                const el = document.createElement('button'); el.type = 'button'; el.textContent = label; el.className = className;
                Object.entries(attributes).forEach(([key, value]) => el.dataset[key] = value);
                return el;
            }
            function calcTotals(renderPayments = true) {
                const subtotal = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
                const invalidLineDiscount = cart.some(item => item.discountType === 'PERSEN' ? item.discountValue > 100 : item.discountValue > item.price * item.qty);
                const lineDiscount = cart.reduce((sum, item) => sum + (item.discountType === 'PERSEN' ? item.price * item.qty * Math.min(100, item.discountValue) / 100 : Math.min(item.price * item.qty, item.discountValue)), 0);
                const afterLines = Math.max(0, subtotal - lineDiscount);
                const rawTransactionDiscount = Number(document.getElementById('discount-value').value || 0);
                const transactionDiscount = document.getElementById('discount-type').value === 'PERSEN' ? afterLines * rawTransactionDiscount / 100 : rawTransactionDiscount;
                const discountWarning = transactionDiscount > afterLines || invalidLineDiscount;
                const discount = lineDiscount + Math.min(afterLines, transactionDiscount);
                const taxable = Math.max(0, subtotal - discount);
                const taxRate = document.getElementById('tax-enabled').checked ? Number(document.getElementById('tax-rate').value || 0) : 0;
                const tax = Math.round(taxable * taxRate) / 100;
                const fee = Number(document.getElementById('extra-fee').value || 0);
                total = Math.round((taxable + tax + fee) * 100) / 100;

                if (payments.length === 1 && payments[0].method === 'CASH' && !amountTouched) payments[0].amount = total;
                const nonCredit = payments.filter(line => line.method !== 'PIUTANG');
                paid = nonCredit.reduce((sum, line) => sum + (Number(line.amount) || 0), 0);
                const dueLine = payments.find(line => line.method === 'PIUTANG');
                balance = Math.max(0, total - paid);
                if (dueLine) dueLine.amount = balance;
                const cashAmount = nonCredit.filter(line => line.method === 'CASH').reduce((sum, line) => sum + (Number(line.amount) || 0), 0);
                change = Math.min(Math.max(0, paid - total), cashAmount);

                document.getElementById('cart-count').textContent = cart.reduce((sum, item) => sum + item.qty, 0);
                document.getElementById('subtotal-label').textContent = money(subtotal);
                document.getElementById('discount-label').textContent = money(discount);
                document.getElementById('tax-title').textContent = taxRate > 0 ? `Pajak (PPN ${taxRate}%)` : 'Pajak';
                document.getElementById('tax-label').textContent = money(tax);
                document.getElementById('fee-label').textContent = money(fee);
                document.getElementById('total-label').textContent = money(total);
                document.getElementById('paid-label').textContent = money(paid);
                document.getElementById('due-label').textContent = money(balance);
                document.getElementById('due-label').className = `text-base ${balance > 0 ? 'text-amber-700' : 'text-slate-900'}`;
                document.getElementById('change-label').textContent = money(change);
                document.getElementById('discount-warning').classList.toggle('hidden', !discountWarning);
                const warning = document.getElementById('payment-warning');
                const invalidOverpay = paid > total && paid - total > cashAmount;
                warning.textContent = invalidOverpay ? 'Pembayaran non-tunai tidak boleh melebihi total. Kelebihan hanya dapat menjadi kembalian dari tunai.' : '';
                warning.classList.toggle('hidden', !invalidOverpay);

                if (renderPayments) renderPaymentRows();
                else {
                    payments.forEach((line, index) => {
                        const visible = paymentRows.querySelector(`[data-payment-amount="${index}"]`);
                        const hidden = paymentRows.querySelector(`[name="payments[${index}][amount]"]`);
                        if (visible && line.method === 'PIUTANG') visible.value = (window.formatMoney ? window.formatMoney(line.amount || 0) : String(line.amount || 0));
                        if (hidden) hidden.value = String(line.amount || 0);
                    });
                }
            }
            function renderCart() {
                cartRows.replaceChildren(); hiddenInputs.replaceChildren();
                if (!cart.length) {
                    const row = document.createElement('tr'); row.innerHTML = '<td colspan="7" class="px-4 py-12 text-center text-xs text-slate-400">Klik produk di sebelah kiri untuk memulai transaksi.</td>'; cartRows.appendChild(row);
                }
                cart.forEach((item, index) => {
                    const row = document.createElement('tr'); row.className = 'align-middle hover:bg-slate-50';
                    const number = document.createElement('td'); number.className = 'px-2.5 py-3 text-slate-500'; number.textContent = String(index + 1);
                    const product = document.createElement('td'); product.className = 'min-w-36 px-2.5 py-3';
                    const name = document.createElement('span'); name.className = 'block max-w-48 truncate font-medium text-slate-900'; name.textContent = item.name;
                    const code = document.createElement('span'); code.className = 'block text-[10px] text-slate-500'; code.textContent = item.code + (item.serial ? ` · SN ${item.serial}` : ''); product.append(name, code);
                    const price = document.createElement('td'); price.className = 'whitespace-nowrap px-2 py-3 text-right'; price.textContent = money(item.price);
                    const qtyCell = document.createElement('td'); qtyCell.className = 'px-2 py-3';
                    const qtyBox = document.createElement('div'); qtyBox.className = 'flex items-center';
                    const minus = button('−', 'h-8 w-7 rounded-l-md border border-slate-200 text-slate-600 hover:bg-slate-100', { qtyAction: 'minus', index: String(index) }); minus.disabled = Boolean(item.laptopId) || item.qty <= 1;
                    const qty = document.createElement('input'); qty.type = 'number'; qty.min = '1'; qty.max = String(item.stock); qty.value = String(item.qty); qty.disabled = Boolean(item.laptopId); qty.dataset.qtyIndex = String(index); qty.className = 'h-8 w-9 border-y border-slate-200 p-0 text-center text-xs focus:ring-0'; qty.title = `Stok tersedia ${item.stock}`;
                    const plus = button('+', 'h-8 w-7 rounded-r-md border border-slate-200 text-slate-600 hover:bg-slate-100', { qtyAction: 'plus', index: String(index) }); plus.disabled = Boolean(item.laptopId) || item.qty >= item.stock;
                    qtyBox.append(minus, qty, plus); qtyCell.appendChild(qtyBox);
                    const discountCell = document.createElement('td'); discountCell.className = 'px-2 py-3';
                    @if($canDiscount)
                    const discountBox = document.createElement('div'); discountBox.className = 'flex items-center';
                    const type = document.createElement('select'); type.dataset.discountType = String(index); type.className = 'h-8 w-14 rounded-l-md border-slate-200 p-1 text-[10px] focus:ring-0'; type.innerHTML = '<option value="PERSEN">%</option><option value="NOMINAL">Rp</option>'; type.value = item.discountType;
                    const discount = document.createElement('input'); discount.type = 'number'; discount.min = '0'; discount.max = item.discountType === 'PERSEN' ? '100' : String(item.price * item.qty); discount.step = '0.01'; discount.value = String(item.discountValue); discount.dataset.discountIndex = String(index); discount.dataset.money = 'true'; discount.className = 'h-8 w-[68px] rounded-r-md border-slate-200 p-1 text-right text-[10px] focus:ring-0'; discountBox.append(type, discount); discountCell.appendChild(discountBox);
                    @else
                    discountCell.textContent = '—';
                    @endif
                    const amount = item.discountType === 'PERSEN' ? item.price * item.qty * item.discountValue / 100 : Math.min(item.price * item.qty, item.discountValue);
                    const lineTotal = document.createElement('td'); lineTotal.className = 'whitespace-nowrap px-2 py-3 text-right font-semibold text-slate-900'; lineTotal.textContent = money(Math.max(0, item.price * item.qty - amount));
                    const removeCell = document.createElement('td'); removeCell.className = 'px-2 py-3 text-center'; const remove = button('×', 'h-7 w-7 rounded-md text-lg leading-none text-rose-600 hover:bg-rose-50', { removeIndex: String(index) }); remove.setAttribute('aria-label', 'Hapus item'); removeCell.appendChild(remove);
                    row.append(number, product, price, qtyCell, discountCell, lineTotal, removeCell); cartRows.appendChild(row);
                    addHidden(hiddenInputs, `items[${index}][produk_id]`, item.productId);
                    addHidden(hiddenInputs, `items[${index}][laptop_id]`, item.laptopId || '');
                    addHidden(hiddenInputs, `items[${index}][qty]`, item.qty);
                    @if($canDiscount)
                    addHidden(hiddenInputs, `items[${index}][diskon_tipe]`, item.discountType);
                    addHidden(hiddenInputs, `items[${index}][diskon_nilai]`, item.discountValue);
                    @endif
                });
                calcTotals();
            }
            function renderPaymentRows() {
                paymentRows.replaceChildren();
                payments.forEach((payment, index) => {
                    const row = document.createElement('tr');
                    const num = document.createElement('td'); num.className = 'px-2.5 py-2.5 text-slate-500'; num.textContent = String(index + 1);
                    const methodCell = document.createElement('td'); methodCell.className = 'px-2 py-2';
                    const method = document.createElement('select'); method.dataset.methodIndex = String(index); method.className = 'h-9 w-36 rounded-lg border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500'; method.innerHTML = '<option value="CASH">Tunai</option><option value="TRANSFER">Transfer</option><option value="QRIS">QRIS</option><option value="DEBIT">Debit</option><option value="KREDIT">Kredit</option><option value="PIUTANG">Piutang</option>'; method.value = payment.method; methodCell.appendChild(method);
                    const amountCell = document.createElement('td'); amountCell.className = 'px-2 py-2'; const amount = document.createElement('input'); amount.type = 'number'; amount.min = '0'; amount.step = '0.01'; amount.value = (window.formatMoney ? window.formatMoney(payment.amount || 0) : String(payment.amount || 0)); amount.dataset.paymentAmount = String(index); amount.dataset.money = 'true'; amount.readOnly = payment.method === 'PIUTANG'; amount.className = 'h-9 w-28 rounded-lg border-slate-200 px-2 text-xs focus:border-blue-500 focus:ring-blue-500'; amountCell.appendChild(amount);
                    const referenceCell = document.createElement('td'); referenceCell.className = 'px-2 py-2'; const reference = document.createElement('input'); reference.type = 'text'; reference.maxLength = 255; reference.placeholder = payment.method === 'CASH' || payment.method === 'PIUTANG' ? '—' : 'Bank / nama / nomor referensi'; reference.value = payment.reference; reference.dataset.paymentReference = String(index); reference.disabled = payment.method === 'CASH' || payment.method === 'PIUTANG'; reference.className = 'h-9 w-full min-w-36 rounded-lg border-slate-200 px-2 text-xs focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-50'; referenceCell.appendChild(reference);
                    const actionCell = document.createElement('td'); actionCell.className = 'px-2 py-2 text-center'; const remove = button('×', 'h-8 w-8 rounded-md text-lg text-rose-600 hover:bg-rose-50', { paymentRemove: String(index) }); remove.disabled = payments.length === 1; remove.setAttribute('aria-label', 'Hapus pembayaran'); actionCell.appendChild(remove);
                    row.append(num, methodCell, amountCell, referenceCell, actionCell); paymentRows.appendChild(row);
                    addHidden(paymentRows, `payments[${index}][method]`, payment.method);
                    addHidden(paymentRows, `payments[${index}][amount]`, payment.amount || 0);
                    addHidden(paymentRows, `payments[${index}][reference]`, payment.reference);
                });
            }
            function addProduct(row) {
                if (Number(row.dataset.stock) < 1) { toast('Stok produk habis.'); return; }
                const productId = Number(row.dataset.productId);
                const laptopId = row.dataset.laptopId ? Number(row.dataset.laptopId) : null;
                const key = `${productId}:${laptopId || 'generic'}`;
                const existing = cart.find(item => item.key === key);
                if (existing) {
                    if (existing.qty >= existing.stock) { toast('Stok tidak mencukupi.'); return; }
                    existing.qty++;
                } else {
                    cart.push({ key, productId, laptopId, code: row.dataset.code, name: row.dataset.name, category: row.dataset.categoryName, price: Number(row.dataset.price), stock: Number(row.dataset.stock), serial: row.dataset.serial, qty: 1, discountType: 'PERSEN', discountValue: 0 });
                }
                renderCart();
                toast(`${row.dataset.name} masuk ke keranjang.`);
            }

            document.querySelectorAll('.catalog-row').forEach(row => {
                row.addEventListener('click', () => addProduct(row));
                row.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); addProduct(row); } });
            });
            cartRows.addEventListener('click', event => {
                const target = event.target;
                if (target.dataset.removeIndex !== undefined) { cart.splice(Number(target.dataset.removeIndex), 1); renderCart(); return; }
                if (target.dataset.qtyAction !== undefined) {
                    const item = cart[Number(target.dataset.index)]; if (!item || item.laptopId) return;
                    const next = item.qty + (target.dataset.qtyAction === 'plus' ? 1 : -1);
                    if (next > item.stock) { toast('Stok tidak mencukupi.'); return; }
                    item.qty = Math.max(1, next); renderCart();
                }
            });
            cartRows.addEventListener('change', event => {
                const target = event.target;
                if (target.dataset.qtyIndex !== undefined) {
                    const item = cart[Number(target.dataset.qtyIndex)];
                    const wanted = Math.max(1, Math.floor(Number(target.value || 1)));
                    if (wanted > item.stock) toast('Stok tidak mencukupi.');
                    item.qty = Math.min(wanted, item.stock); renderCart(); return;
                }
                if (target.dataset.discountType !== undefined) { cart[Number(target.dataset.discountType)].discountType = target.value; renderCart(); return; }
                if (target.dataset.discountIndex !== undefined) { cart[Number(target.dataset.discountIndex)].discountValue = Math.max(0, Number(target.value || 0)); renderCart(); }
            });
            document.getElementById('clear-cart').addEventListener('click', () => { cart.splice(0); renderCart(); });
            document.getElementById('add-payment').addEventListener('click', () => {
                if (payments.length >= 10) { toast('Maksimal 10 baris pembayaran.'); return; }
                if (payments.length === 1 && payments[0].method === 'CASH' && !amountTouched) { payments[0].amount = 0; amountTouched = true; }
                payments.push({ method: 'TRANSFER', amount: 0, reference: '' }); renderCart();
            });
            paymentRows.addEventListener('change', event => {
                const target = event.target;
                if (target.dataset.methodIndex !== undefined) {
                    const index = Number(target.dataset.methodIndex); payments[index].method = target.value;
                    if (target.value === 'PIUTANG') payments[index].amount = balance;
                    else if (target.value === 'CASH' && payments[index].amount === 0 && payments.length === 1) payments[index].amount = Math.max(0, total - paid);
                    if (target.value === 'CASH' || target.value === 'PIUTANG') payments[index].reference = '';
                    renderCart(); return;
                }
            });
            paymentRows.addEventListener('input', event => {
                const target = event.target;
                if (target.dataset.paymentAmount !== undefined) { payments[Number(target.dataset.paymentAmount)].amount = Math.max(0, Number(target.value || 0)); amountTouched = true; calcTotals(false); return; }
                if (target.dataset.paymentReference !== undefined) {
                    const index = Number(target.dataset.paymentReference); payments[index].reference = target.value;
                    const hidden = paymentRows.querySelector(`[name="payments[${index}][reference]"]`); if (hidden) hidden.value = target.value;
                }
            });
            paymentRows.addEventListener('click', event => { if (event.target.dataset.paymentRemove !== undefined) { payments.splice(Number(event.target.dataset.paymentRemove), 1); renderCart(); } });

            const search = document.getElementById('catalog-search');
            function filterCatalog() {
                const term = search.value.trim().toLowerCase(); let count = 0;
                document.querySelectorAll('.catalog-row').forEach(row => {
                    const matchCategory = activeCategory === 'ALL'
                        || row.dataset.category === activeCategory
                        || (activeCategory === 'OTHER' && ['INVENTARIS', 'LAINNYA'].includes(row.dataset.category))
                        || (activeCategory === 'SERVICE' && row.dataset.categoryName.toLowerCase().includes('service'))
                        || (activeCategory === 'SOFTWARE' && row.dataset.categoryName.toLowerCase().includes('software'));
                    const match = row.dataset.search.includes(term) && matchCategory;
                    row.classList.toggle('hidden', !match); if (match) count++;
                });
                document.getElementById('catalog-empty').classList.toggle('hidden', count > 0);
                document.getElementById('catalog-count').textContent = String(count);
            }
            search.addEventListener('input', filterCatalog);
            search.addEventListener('keydown', event => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const match = [...document.querySelectorAll('.catalog-row')].find(row => !row.classList.contains('hidden'));
                if (!match) { toast('Produk tidak ditemukan.'); return; }
                addProduct(match); search.value = ''; filterCatalog();
            });
            document.querySelectorAll('.category-filter').forEach(buttonEl => buttonEl.addEventListener('click', () => {
                activeCategory = buttonEl.dataset.category;
                document.querySelectorAll('.category-filter').forEach(button => button.className = 'category-filter shrink-0 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-amber-50 hover:text-slate-950');
                buttonEl.className = 'category-filter shrink-0 rounded-lg bg-amber-300 px-3 py-2 text-xs font-semibold text-slate-950 transition'; filterCatalog();
            }));
            document.getElementById('catalog-sort').addEventListener('change', event => {
                const key = event.target.value; const body = document.getElementById('catalog-rows');
                const rows = [...body.querySelectorAll('.catalog-row')];
                rows.sort((a, b) => {
                    if (key === 'price-asc') return Number(a.dataset.sortPrice) - Number(b.dataset.sortPrice);
                    if (key === 'price-desc') return Number(b.dataset.sortPrice) - Number(a.dataset.sortPrice);
                    if (key === 'stock-desc') return Number(b.dataset.sortStock) - Number(a.dataset.sortStock);
                    if (key === 'newest') return Number(b.dataset.sortNewest) - Number(a.dataset.sortNewest);
                    return a.dataset.sortName.localeCompare(b.dataset.sortName, 'id');
                }); rows.forEach(row => body.appendChild(row));
            });

            const customerSearch = document.getElementById('customer-search');
            const customerOptions = document.getElementById('customer-options');
            customerSearch.addEventListener('focus', () => customerOptions.classList.remove('hidden'));
            customerSearch.addEventListener('input', () => {
                const term = customerSearch.value.trim().toLowerCase(); let shown = 0;
                document.querySelectorAll('.customer-option').forEach(option => { const match = option.dataset.search.includes(term); option.classList.toggle('hidden', !match); if (match) shown++; });
                customerOptions.classList.toggle('hidden', shown === 0);
                document.getElementById('customer-id').value = '';
                document.getElementById('customer-selected').classList.add('hidden');
            });
            customerOptions.addEventListener('click', event => {
                const option = event.target.closest('.customer-option'); if (!option) return;
                customerSearch.value = option.dataset.label; document.getElementById('customer-id').value = option.dataset.id;
                const selected = document.getElementById('customer-selected'); selected.textContent = 'Pelanggan dipilih'; selected.classList.remove('hidden'); customerOptions.classList.add('hidden');
            });
            document.addEventListener('click', event => { if (!event.target.closest('#customer-search') && !event.target.closest('#customer-options')) customerOptions.classList.add('hidden'); });

            document.getElementById('tax-preset').addEventListener('change', event => {
                const value = event.target.value; const rate = document.getElementById('tax-rate'); const active = document.getElementById('tax-enabled');
                if (value === 'custom') { rate.readOnly = false; active.checked = Number(rate.value) > 0; }
                else { rate.value = value; rate.readOnly = true; active.checked = Number(value) > 0; }
                calcTotals();
            });
            ['discount-value', 'discount-type', 'tax-rate', 'extra-fee'].forEach(id => document.getElementById(id).addEventListener('input', calcTotals));

            form.addEventListener('submit', event => {
                const intent = event.submitter?.dataset.intent || 'sale';
                document.getElementById('transaction-intent').value = intent;
                if (cart.length === 0) { event.preventDefault(); toast('Tambahkan minimal satu produk ke keranjang.'); return; }
                if (intent === 'receivable' && payments.length === 1 && payments[0].method === 'CASH' && !amountTouched) { payments[0].amount = 0; amountTouched = true; renderCart(); }
                if (intent !== 'draft' && paid > total && paid - total > payments.filter(line => line.method === 'CASH').reduce((sum, line) => sum + Number(line.amount || 0), 0)) { event.preventDefault(); toast('Pembayaran non-tunai melebihi total transaksi.'); return; }
                if (intent === 'receivable' || payments.some(line => line.method === 'PIUTANG') || balance > 0) {
                    if (!document.getElementById('customer-id').value) { event.preventDefault(); toast('Pilih pelanggan untuk transaksi piutang.'); customerSearch.focus(); return; }
                }
                if (intent === 'sale' && balance > 0 && !payments.some(line => line.method === 'PIUTANG')) { event.preventDefault(); toast('Pembayaran belum lunas. Pilih Piutang atau gunakan Buat Piutang.'); }
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'F2') { event.preventDefault(); search.focus(); }
                if (event.key === 'F4' && cart.length) { event.preventDefault(); const saveButton = document.getElementById('save-sale'); if (saveButton.disabled) { toast('Buka shift kasir sebelum menyimpan transaksi.'); return; } form.requestSubmit(saveButton); }
            });
            renderCart();
        })();
    </script>
    <script>
    (function(){
        setTimeout(function(){
        function csrfToken(){ var m=document.querySelector('meta[name="csrf-token"]'); if(m) return m.content; var i=document.querySelector('input[name="_token"]'); return i?i.value:''; }
        window.enhanceCustomerOptions = function(){
            document.querySelectorAll('.customer-option').forEach(function(opt){
                if(opt.dataset.enhanced) return;
                opt.dataset.enhanced='1';
                var label = opt.dataset.label || opt.textContent.trim();
                var phone = '';
                (opt.dataset.search||'').split(/\s+/).forEach(function(t){ if(/^\+?\d/.test(t) && !phone) phone=t; });
                var wrap = document.createElement('div'); wrap.className='flex flex-col';
                var name = document.createElement('span'); name.className='font-semibold text-slate-900'; name.textContent=label; wrap.appendChild(name);
                if(phone){ var p=document.createElement('span'); p.className='text-xs text-slate-500'; p.textContent=phone; wrap.appendChild(p); }
                opt.innerHTML=''; opt.appendChild(wrap);
            });
        };
        var searchInput = document.getElementById('customer-search');
        if(searchInput && !document.getElementById('btn-add-customer')){
            var btn = document.createElement('button');
            btn.type='button'; btn.id='btn-add-customer';
            btn.className='mt-1 inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100';
            btn.textContent='+ Pelanggan Baru';
            searchInput.insertAdjacentElement('afterend', btn);
            btn.addEventListener('click', function(){ if(window.openCustomerModal) window.openCustomerModal(searchInput.value.trim()); });
        }
        var modal = document.getElementById('customer-quick-modal');
        window.openCustomerModal = function(prefill){
            if(!modal) return;
            modal.classList.remove('hidden'); modal.classList.add('flex');
            var nm = modal.querySelector('#qc-nama'); if(nm){ nm.value = prefill||''; nm.focus(); }
        };
        window.closeCustomerModal = function(){ if(modal){ modal.classList.add('hidden'); modal.classList.remove('flex'); } };
        if(modal) modal.querySelectorAll('[data-close-modal]').forEach(function(el){ el.addEventListener('click', window.closeCustomerModal); });
        async function createCustomer(){
            var nama = modal.querySelector('#qc-nama').value.trim();
            var telepon = modal.querySelector('#qc-telepon').value.trim();
            var email = modal.querySelector('#qc-email').value.trim();
            if(!nama || !telepon){ alert('Nama dan nomor telepon pelanggan wajib diisi.'); return; }
            var res = await fetch("{{ route('customers.quick-store') }}", {
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept':'application/json' },
                body: JSON.stringify({ nama: nama, telepon: telepon, email: email })
            });
            var responseText = await res.text();
            var c;
            try { c = JSON.parse(responseText); }
            catch { alert('Server mengembalikan respons tidak valid (HTTP '+res.status+').'); return; }
            if(!res.ok){
                var message = c.errors ? Object.values(c.errors).flat()[0] : (c.message || 'Pelanggan tidak dapat disimpan.');
                alert(message);
                return;
            }
            var options = document.getElementById('customer-options');
            if(options){
                var opt = document.createElement('div');
                opt.className='customer-option cursor-pointer rounded-lg px-3 py-2 text-sm hover:bg-slate-100';
                opt.dataset.id = c.id;
                opt.dataset.label = c.nama;
                opt.dataset.search = (c.nama+' '+(c.telepon||'')+' '+(c.kode_pelanggan||'')).toLowerCase();
                opt.dataset.enhanced='1';
                var wrap=document.createElement('div'); wrap.className='flex flex-col';
                var n=document.createElement('span'); n.className='font-semibold text-slate-900'; n.textContent=c.nama; wrap.appendChild(n);
                if(c.telepon){ var p=document.createElement('span'); p.className='text-xs text-slate-500'; p.textContent=c.telepon; wrap.appendChild(p); }
                opt.appendChild(wrap);
                options.appendChild(opt);
                options.classList.remove('hidden');
            }
            var sid = document.getElementById('customer-id'); if(sid) sid.value = c.id;
            if(searchInput) searchInput.value = c.nama;
            var sel = document.getElementById('customer-selected'); if(sel){ sel.textContent='Pelanggan dipilih'; sel.classList.remove('hidden'); }
            var opts = document.getElementById('customer-options'); if(opts) opts.classList.add('hidden');
            window.closeCustomerModal();
        }
        var saveBtn = modal && modal.querySelector('#qc-save');
        if(saveBtn) saveBtn.addEventListener('click', createCustomer);
        if(typeof window.enhanceCustomerOptions === 'function') window.enhanceCustomerOptions();
        }, 0);
    })();
    </script>

    <script>
    (function(){
        function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
        var searchInput = document.getElementById('customer-search');
        var optsContainer = document.getElementById('customer-options');
        if(searchInput){
            searchInput.addEventListener('input', function(){
                setTimeout(function(){
                    var term = searchInput.value.trim().toLowerCase();
                    if(!optsContainer) return;
                    optsContainer.querySelectorAll('.customer-create-option').forEach(function(e){ e.remove(); });
                    var visible = Array.from(optsContainer.querySelectorAll('.customer-option:not(.customer-create-option)')).filter(function(o){ return !o.classList.contains('hidden'); });
                    if(term && visible.length===0){
                        var co = document.createElement('div');
                        co.className = 'customer-create-option cursor-pointer rounded-lg px-3 py-2 text-sm text-blue-700 hover:bg-blue-50';
                        co.textContent = '+ Tambah pelanggan baru: "'+term+'"';
                        co.addEventListener('click', function(){ if(window.openCustomerModal) window.openCustomerModal(searchInput.value.trim()); });
                        optsContainer.appendChild(co);
                        optsContainer.classList.remove('hidden');
                    }
                    if(window.enhanceCustomerOptions) window.enhanceCustomerOptions();
                },0);
            });
        }
        if(optsContainer){
            optsContainer.addEventListener('click', function(e){
                var co = e.target.closest('.customer-create-option');
                if(co){ e.stopPropagation(); e.preventDefault(); if(window.openCustomerModal) window.openCustomerModal(searchInput.value.trim()); }
            }, true);
        }
        if(window.enhanceCustomerOptions) window.enhanceCustomerOptions();
        if(optsContainer){
            var mo = new MutationObserver(function(){ if(window.enhanceCustomerOptions) window.enhanceCustomerOptions(); });
            mo.observe(optsContainer, { childList:true, subtree:true });
        }
    })();
    </script>

    </script>
    <div id="customer-quick-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">Tambah Pelanggan Baru</h3>
                <button type="button" data-close-modal class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100">✕</button>
            </div>
            <div class="grid gap-3">
                <label class="text-sm font-medium text-slate-700">Nama Lengkap<input id="qc-nama" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Telepon <span class="text-rose-600">*</span><input id="qc-telepon" maxlength="30" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Email<input type="email" id="qc-email" maxlength="255" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-close-modal class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600">Batal</button>
                <button type="button" id="qc-save" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Simpan</button>
            </div>
        </div>
    </div>

</x-dashboard-layout>
