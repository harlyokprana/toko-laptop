<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\PembelianHistory;
use App\Models\PembelianPenerimaan;
use App\Models\Pembayaran;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\KasirShift;
use App\Services\StockService;
use App\Services\CashLedgerService;
use App\Services\BusinessNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    private const STATUSES = ['DRAFT', 'MENUNGGU_APPROVAL', 'DISETUJUI', 'DALAM_PROSES', 'SEBAGIAN_DITERIMA', 'SELESAI', 'SEBAGIAN', 'BATAL'];
    private const TERMS = ['CASH', 'COD', 'NET 7', 'NET 14', 'NET 30', 'NET 60'];

    public function index(Request $request): View
    {
        return $this->listing($request, false);
    }

    public function receivingIndex(Request $request): View
    {
        return $this->listing($request, true);
    }

    private function listing(Request $request, bool $receivingOnly): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'supplier_id' => ['nullable', 'integer', 'exists:supplier,id'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $query = Pembelian::query()->with(['supplier:id,nama', 'user:id,name', 'details:id,pembelian_id,qty,qty_diterima'])
            ->where('jenis_pembelian', 'PEMBELIAN')->latest('tanggal')->latest('id');
        $this->filterPurchases($query, $filters);
        if ($receivingOnly) $query->whereIn('status', ['DISETUJUI', 'DALAM_PROSES', 'SEBAGIAN_DITERIMA']);
        $period = Pembelian::query()->where('jenis_pembelian', 'PEMBELIAN');
        if (! empty($filters['date_from'])) $period->whereDate('tanggal', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $period->whereDate('tanggal', '<=', $filters['date_to']);
        $stats = [
            ['label' => 'Total PO', 'value' => (clone $period)->count()],
            ['label' => 'Menunggu Approval', 'value' => (clone $period)->where('status', 'MENUNGGU_APPROVAL')->count()],
            ['label' => 'Dalam Proses', 'value' => (clone $period)->whereIn('status', ['DISETUJUI', 'DALAM_PROSES'])->count()],
            ['label' => 'Sebagian Diterima', 'value' => (clone $period)->where('status', 'SEBAGIAN_DITERIMA')->count()],
            ['label' => 'Selesai', 'value' => (clone $period)->where('status', 'SELESAI')->count()],
        ];

        return view('purchase-orders.index', [
            'purchaseOrders' => $query->paginate(15)->withQueryString(),
            'suppliers' => Supplier::query()->where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama']),
            'filters' => $filters,
            'stats' => $stats,
            'receivingOnly' => $receivingOnly,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $term = trim($data['q']);
        $products = Produk::query()->where('status', 'AKTIF')

            ->where(fn (Builder $query) => $query->where('kode_produk', 'like', "%{$term}%")
                ->orWhere('nama_produk', 'like', "%{$term}%")
                ->orWhere('merek', 'like', "%{$term}%")
                ->orWhere('tipe', 'like', "%{$term}%"))
            ->orderBy('nama_produk')->paginate(10, ['id', 'kode_produk', 'nama_produk', 'merek', 'harga_beli', 'harga_jual', 'stok', 'jenis_produk'])
            ->withQueryString();

        return response()->json([
            'data' => $products->getCollection()->map(fn (Produk $product) => [
                'id' => $product->id, 'code' => $product->kode_produk, 'name' => $product->nama_produk,
                'brand' => $product->merek, 'kind' => $product->jenis_produk,
                'price' => (float) $product->harga_beli, 'sale_price' => (float) $product->harga_jual,
                'stock' => (float) $product->stok, 'unit' => $product->satuan,
            ]),
            'current_page' => $products->currentPage(), 'last_page' => $products->lastPage(),
        ]);
    }

    public function create(): View
    {
        return view('purchase-orders.form', [
            'purchaseOrder' => null,
            'suppliers' => Supplier::query()->where('status', 'AKTIF')->orderBy('nama')->get(),
            'initialItems' => [],
        ]);
    }

    public function store(Request $request, BusinessNotificationService $notifications): RedirectResponse
    {
        $data = $this->validatedOrder($request);
        $purchaseOrder = DB::transaction(function () use ($request, $data): Pembelian {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
            if ($supplier->status !== 'AKTIF') throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak aktif.']);
            $lines = $this->priceLines($data['items']);
            $summary = $this->calculateTotals($lines, (float) ($data['ongkir'] ?? 0), (float) ($data['pajak_persen'] ?? 0));
            $purchase = Pembelian::create([
                'no_invoice' => $this->nextNumber(), 'tanggal' => $data['tanggal'], 'estimasi_tiba' => $data['estimasi_tiba'] ?? null,
                'supplier_id' => $supplier->id, 'jenis_pembelian' => 'PEMBELIAN', 'subtotal' => $summary['subtotal'],
                'diskon' => $summary['diskon'], 'ongkir' => $summary['ongkir'], 'pajak_persen' => $summary['pajak_persen'],
                'pajak' => $summary['pajak'], 'total' => $summary['total'], 'dibayar' => 0,
                'status' => 'MENUNGGU_APPROVAL', 'mata_uang' => 'IDR', 'termin_pembayaran' => $data['termin_pembayaran'] ?? null,
                'user_id' => $request->user()->id, 'keterangan' => $data['keterangan'] ?? null,
            ]);
            foreach ($lines as $line) $purchase->details()->create($line);
            $this->history($purchase, 'DIBUAT', null, 'MENUNGGU_APPROVAL', 'Purchase order dibuat.', $request->user()->id);
            return $purchase;
        });

        $notifications->notifyUsersWithPermission(
            'edit_purchases',
            $request->user(),
            'Purchase order menunggu persetujuan',
            "PO {$purchaseOrder->no_invoice} menunggu persetujuan.",
            route('purchase-orders.show', $purchaseOrder, false),
        );

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase order dibuat. Stok belum berubah sampai barang diterima.');
    }

    public function show(Pembelian $purchaseOrder): View
    {
        $this->assertPurchase($purchaseOrder);
        $purchaseOrder->load([
            'supplier', 'user:id,name', 'approver:id,name', 'details.produk',
            'receipts.user:id,name', 'receipts.details.purchaseDetail.produk:id,kode_produk,nama_produk', 'history.user:id,name',
        ]);
        $payments = Pembayaran::query()->with('user:id,name')->where('referensi_type', 'PEMBELIAN')
            ->where('referensi_id', $purchaseOrder->id)->orderBy('id')->get();
        $ordered = $purchaseOrder->details->sum(fn (PembelianDetail $detail) => (float) $detail->qty);
        $received = $purchaseOrder->details->sum(fn (PembelianDetail $detail) => (float) $detail->qty_diterima);
        $canReceive = in_array($purchaseOrder->status, ['DISETUJUI', 'DALAM_PROSES', 'SEBAGIAN_DITERIMA'], true);

        return view('purchase-orders.show', compact('purchaseOrder', 'ordered', 'received', 'canReceive', 'payments'));
    }

    public function collectPayment(Request $request, Pembelian $purchaseOrder, CashLedgerService $cash): RedirectResponse
    {
        $this->assertPurchase($purchaseOrder);
        $data = $request->validate([
            'nominal' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'metode' => ['required', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($request->user()->role?->name === 'KASIR' && $data['metode'] === 'CASH'
            && ! KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift kasir sebelum mencatat pembayaran tunai supplier.']);
        }

        DB::transaction(function () use ($request, $purchaseOrder, $data, $cash): void {
            $locked = Pembelian::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($locked->status === 'BATAL') throw ValidationException::withMessages(['status' => 'PO yang dibatalkan tidak dapat menerima pembayaran.']);
            $remaining = round((float) $locked->total - (float) $locked->dibayar, 2);
            if ((float) $data['nominal'] > $remaining) throw ValidationException::withMessages(['nominal' => 'Pembayaran melebihi sisa tagihan supplier.']);
            $payment = Pembayaran::create([
                'tanggal' => now(), 'referensi_type' => 'PEMBELIAN', 'referensi_id' => $locked->id,
                'metode' => $data['metode'], 'nominal' => $data['nominal'], 'status' => 'LUNAS',
                'keterangan' => $data['keterangan'] ?? 'Pembayaran supplier '.$locked->no_invoice,
                'user_id' => $request->user()->id, 'created_at' => now(),
            ]);
            $locked->increment('dibayar', (float) $data['nominal']);
            if ($data['metode'] === 'CASH') {
                $cash->disbursement((float) $data['nominal'], $request->user()->id, 'pembayaran', $payment->id,
                    'Pembayaran supplier '.$locked->no_invoice, 'PEMBAYARAN');
            }
            $this->history($locked, 'PEMBAYARAN', $locked->status, $locked->status,
                $data['metode'].' · Rp '.number_format((float) $data['nominal'], 0, ',', '.'), $request->user()->id);
        });

        return back()->with('status', 'Pembayaran supplier berhasil dicatat terpisah dari penerimaan barang.');
    }

    public function edit(Pembelian $purchaseOrder): View
    {
        $this->assertPurchase($purchaseOrder);
        abort_unless(in_array($purchaseOrder->status, ['DRAFT', 'MENUNGGU_APPROVAL'], true) && $purchaseOrder->details()->where('qty_diterima', '>', 0)->doesntExist(), 403);
        $purchaseOrder->load('details.produk.kategori');
        $initialItems = $purchaseOrder->details->map(fn (PembelianDetail $detail) => [
            'id' => $detail->produk_id, 'code' => $detail->produk->kode_produk, 'name' => $detail->produk->nama_produk,
            'kind' => $detail->produk->kategori?->jenis, 'price' => (float) $detail->harga_beli,
            'qty' => (int) $detail->qty, 'discount' => (float) $detail->diskon,
        ])->values()->all();

        return view('purchase-orders.form', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::query()->where('status', 'AKTIF')->orderBy('nama')->get(),
            'initialItems' => $initialItems,
        ]);
    }

    public function update(Request $request, Pembelian $purchaseOrder): RedirectResponse
    {
        $this->assertPurchase($purchaseOrder);
        abort_unless(in_array($purchaseOrder->status, ['DRAFT', 'MENUNGGU_APPROVAL'], true) && $purchaseOrder->details()->where('qty_diterima', '>', 0)->doesntExist(), 403);
        $data = $this->validatedOrder($request);
        DB::transaction(function () use ($request, $data, $purchaseOrder): void {
            $locked = Pembelian::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            $before = $locked->status;
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
            if ($supplier->status !== 'AKTIF') throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak aktif.']);
            $lines = $this->priceLines($data['items']);
            $summary = $this->calculateTotals($lines, (float) ($data['ongkir'] ?? 0), (float) ($data['pajak_persen'] ?? 0));
            $locked->update([
                'tanggal' => $data['tanggal'], 'estimasi_tiba' => $data['estimasi_tiba'] ?? null, 'supplier_id' => $supplier->id,
                'subtotal' => $summary['subtotal'], 'diskon' => $summary['diskon'], 'ongkir' => $summary['ongkir'],
                'pajak_persen' => $summary['pajak_persen'], 'pajak' => $summary['pajak'], 'total' => $summary['total'],
                'termin_pembayaran' => $data['termin_pembayaran'] ?? null, 'keterangan' => $data['keterangan'] ?? null,
                'status' => 'MENUNGGU_APPROVAL',
            ]);
            $locked->details()->delete();
            foreach ($lines as $line) $locked->details()->create($line);
            $this->history($locked, 'DIUBAH', $before, 'MENUNGGU_APPROVAL', 'Purchase order diperbarui.', $request->user()->id);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase order berhasil diperbarui.');
    }

    public function approve(Request $request, Pembelian $purchaseOrder, BusinessNotificationService $notifications): RedirectResponse
    {
        $this->assertPurchase($purchaseOrder);
        DB::transaction(function () use ($request, $purchaseOrder): void {
            $locked = Pembelian::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($locked->status !== 'MENUNGGU_APPROVAL') throw ValidationException::withMessages(['status' => 'Hanya PO yang menunggu approval yang dapat disetujui.']);
            $locked->update(['status' => 'DISETUJUI', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $this->history($locked, 'DISETUJUI', 'MENUNGGU_APPROVAL', 'DISETUJUI', 'PO disetujui.', $request->user()->id);
        });

        $notifications->notifyUsers(
            [$purchaseOrder->user],
            $request->user(),
            'Purchase order disetujui',
            "PO {$purchaseOrder->no_invoice} telah disetujui dan siap diproses.",
            route('purchase-orders.show', $purchaseOrder, false),
        );

        return back()->with('status', 'Purchase order disetujui dan siap diproses/diterima.');
    }

    public function startProcessing(Request $request, Pembelian $purchaseOrder, BusinessNotificationService $notifications): RedirectResponse
    {
        $this->assertPurchase($purchaseOrder);
        DB::transaction(function () use ($request, $purchaseOrder): void {
            $locked = Pembelian::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($locked->status !== 'DISETUJUI') throw ValidationException::withMessages(['status' => 'Hanya PO yang sudah disetujui yang dapat diproses.']);
            $locked->update(['status' => 'DALAM_PROSES']);
            $this->history($locked, 'MULAI_DIPROSES', 'DISETUJUI', 'DALAM_PROSES', 'PO mulai diproses ke supplier.', $request->user()->id);
        });

        $notifications->notifyUsers(
            [$purchaseOrder->user],
            $request->user(),
            'Purchase order diproses',
            "PO {$purchaseOrder->no_invoice} mulai diproses ke supplier.",
            route('purchase-orders.show', $purchaseOrder, false),
        );

        return back()->with('status', 'Purchase order mulai diproses.');
    }

    public function cancel(Request $request, Pembelian $purchaseOrder, BusinessNotificationService $notifications): RedirectResponse
    {
        $this->assertPurchase($purchaseOrder);
        DB::transaction(function () use ($request, $purchaseOrder): void {
            $locked = Pembelian::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if (in_array($locked->status, ['SELESAI', 'BATAL'], true) || $locked->details()->where('qty_diterima', '>', 0)->exists()) {
                throw ValidationException::withMessages(['status' => 'PO yang sudah diterima atau ditutup tidak dapat dibatalkan.']);
            }
            $before = $locked->status;
            $locked->update(['status' => 'BATAL']);
            $this->history($locked, 'DIBATALKAN', $before, 'BATAL', 'Purchase order dibatalkan.', $request->user()->id);
        });

        $notifications->notifyUsers(
            [$purchaseOrder->user],
            $request->user(),
            'Purchase order dibatalkan',
            "PO {$purchaseOrder->no_invoice} telah dibatalkan.",
            route('purchase-orders.show', $purchaseOrder, false),
        );

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase order dibatalkan.');
    }

    public function receiving(Request $request, Pembelian $purchaseOrder): View
    {
        $this->assertPurchase($purchaseOrder);
        abort_unless(in_array($purchaseOrder->status, ['DISETUJUI', 'DALAM_PROSES', 'SEBAGIAN_DITERIMA'], true), 422, 'PO harus disetujui dan belum selesai untuk menerima barang.');
        $purchaseOrder->load('supplier', 'details.produk.kategori');
        return view('purchase-orders.receiving', compact('purchaseOrder'));
    }

    public function storeReceiving(Request $request, Pembelian $purchaseOrder, StockService $stock, BusinessNotificationService $notifications): RedirectResponse
    {
        $this->assertPurchase($purchaseOrder);
        $data = $request->validate([
            'tanggal_terima' => ['required', 'date'], 'catatan' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.detail_id' => ['required', 'integer', 'distinct', 'exists:pembelian_detail,id'],
            'items.*.qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.kondisi' => ['required', Rule::in(['BAIK', 'RUSAK', 'TIDAK_SESUAI'])],
            'items.*.catatan' => ['nullable', 'string', 'max:1000'],
            'items.*.serial_numbers' => ['nullable', 'array'],
            'items.*.serial_numbers.*' => ['nullable', 'string', 'max:100', 'distinct', 'unique:laptop,serial_number'],
        ]);
        $selected = collect($data['items'])->filter(fn (array $item) => (int) ($item['qty'] ?? 0) > 0);
        if ($selected->isEmpty()) throw ValidationException::withMessages(['items' => 'Masukkan jumlah minimal satu produk yang diterima.']);

        DB::transaction(function () use ($request, $purchaseOrder, $stock, $data, $selected): void {
            $locked = Pembelian::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if (! in_array($locked->status, ['DISETUJUI', 'DALAM_PROSES', 'SEBAGIAN_DITERIMA'], true)) {
                throw ValidationException::withMessages(['status' => 'PO batal, selesai, atau belum disetujui tidak dapat menerima barang.']);
            }
            $receipt = PembelianPenerimaan::create([
                'pembelian_id' => $locked->id, 'nomor_penerimaan' => $this->nextReceiptNumber(),
                'tanggal_terima' => $data['tanggal_terima'], 'catatan' => $data['catatan'] ?? null, 'user_id' => $request->user()->id,
            ]);
            $receivedLabels = [];
            foreach ($selected as $item) {
                $detail = PembelianDetail::query()->where('pembelian_id', $locked->id)->lockForUpdate()->findOrFail($item['detail_id']);
                $quantity = (int) ($item['qty'] ?? 0);
                $remaining = (int) $detail->qty - (int) $detail->qty_diterima;
                if ($quantity > $remaining) throw ValidationException::withMessages(['items' => "Qty penerimaan {$detail->produk->nama_produk} melebihi sisa PO ({$remaining})."]);
                $product = Produk::query()->with('kategori')->lockForUpdate()->findOrFail($detail->produk_id);
                $effectiveCost = max(0, (float) $detail->harga_beli - ((float) $detail->diskon / max(1, (float) $detail->qty)));
                if ($product->kategori?->jenis === 'LAPTOP') {
                    $serials = array_values(array_filter($item['serial_numbers'] ?? [], fn ($serial) => trim((string) $serial) !== ''));
                    if (count($serials) !== $quantity) throw ValidationException::withMessages(['items' => "Masukkan satu serial number unik untuk setiap laptop {$product->nama_produk} yang diterima."]);
                } else {
                    $serials = [];
                }
                $receipt->details()->create([
                    'pembelian_detail_id' => $detail->id, 'qty' => $quantity,
                    'kondisi' => $item['kondisi'], 'catatan' => $item['catatan'] ?? null,
                ]);

                // Product HPP follows the weighted average of units physically received.
                $oldQty = (float) $product->stok;
                $newQty = $oldQty + $quantity;
                $newCost = $newQty > 0 ? (($oldQty * (float) $product->harga_beli) + ($quantity * $effectiveCost)) / $newQty : $effectiveCost;
                $product->forceFill(['harga_beli' => round($newCost, 2)])->save();
                $detail->increment('qty_diterima', $quantity);
                $stock->increase($product->id, $quantity, 'PEMBELIAN', $request->user()->id,
                    'Penerimaan '.$receipt->nomor_penerimaan.' untuk PO '.$locked->no_invoice,
                    'pembelian_penerimaan', $receipt->id);

                foreach ($serials as $serial) {
                    Laptop::create([
                        'produk_id' => $product->id, 'supplier_id' => $locked->supplier_id,
                        'sku' => $this->nextLaptopCode(), 'serial_number' => trim($serial),
                        'tanggal_perolehan' => $data['tanggal_terima'], 'merek' => $product->merek,
                        'model_seri' => $product->tipe, 'hpp' => $effectiveCost,
                        'harga_jual' => $product->harga_jual, 'status' => $item['kondisi'] === 'BAIK' ? 'READY' : 'REJECT',
                        'keterangan' => 'Diterima melalui '.$receipt->nomor_penerimaan,
                    ]);
                }
                $receivedLabels[] = $product->nama_produk.' × '.$quantity;
            }
            $locked->load('details');
            $complete = $locked->details->every(fn (PembelianDetail $detail) => (float) $detail->qty_diterima >= (float) $detail->qty);
            $before = $locked->status;
            $after = $complete ? 'SELESAI' : 'SEBAGIAN_DITERIMA';
            $locked->update(['status' => $after]);
            $summary = implode(', ', $receivedLabels);
            $this->history($locked, 'PENERIMAAN', $before, $after, $receipt->nomor_penerimaan.': '.$summary, $request->user()->id);
        });

        $notifications->notifyUsers(
            [$purchaseOrder->user],
            $request->user(),
            'Penerimaan purchase order tercatat',
            "Penerimaan barang untuk PO {$purchaseOrder->no_invoice} telah dicatat.",
            route('purchase-orders.show', $purchaseOrder, false),
        );

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Penerimaan tersimpan, stok dan mutasi stok berhasil diperbarui.');
    }

    private function validatedOrder(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'tanggal' => ['required', 'date'], 'estimasi_tiba' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'termin_pembayaran' => ['nullable', Rule::in(self::TERMS)],
            'ongkir' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'pajak_persen' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.discount_type' => ['required', Rule::in(['PERCENT', 'NOMINAL'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
    }

    private function priceLines(array $items): array
    {
        $products = Produk::query()->whereIn('id', collect($items)->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
        $lines = [];
        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            if (! $product || $product->status !== 'AKTIF') throw ValidationException::withMessages(['items' => 'Semua produk harus aktif.']);
            $quantity = (int) $item['qty'];
            $gross = round($quantity * (float) $item['price'], 2);
            $value = (float) ($item['discount_value'] ?? 0);
            if ($item['discount_type'] === 'PERCENT' && $value > 100) throw ValidationException::withMessages(['items' => 'Diskon persen maksimal 100%.']);
            $discount = $item['discount_type'] === 'PERCENT' ? round($gross * $value / 100, 2) : round($value, 2);
            if ($discount > $gross) throw ValidationException::withMessages(['items' => "Diskon {$product->nama_produk} tidak boleh melebihi nilai item."]);
            $lines[] = [
                'produk_id' => $product->id, 'qty' => $quantity, 'qty_diterima' => 0,
                'harga_beli' => round((float) $item['price'], 2), 'diskon' => $discount,
                'subtotal' => round($gross - $discount, 2),
            ];
        }
        return $lines;
    }

    private function calculateTotals(array $lines, float $shipping, float $taxRate): array
    {
        $subtotal = round(array_sum(array_map(fn (array $line) => $line['qty'] * $line['harga_beli'], $lines)), 2);
        $discount = round(array_sum(array_column($lines, 'diskon')), 2);
        $tax = round(max(0, $subtotal - $discount + $shipping) * $taxRate / 100, 2);
        return [
            'subtotal' => $subtotal, 'diskon' => $discount, 'ongkir' => round($shipping, 2),
            'pajak_persen' => round($taxRate, 2), 'pajak' => $tax,
            'total' => round($subtotal - $discount + $shipping + $tax, 2),
        ];
    }

    private function nextNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ymd').'-';
        $last = Pembelian::query()->where('no_invoice', 'like', $prefix.'%')->orderByDesc('id')->value('no_invoice');
        $sequence = $last ? ((int) Str::afterLast($last, '-') + 1) : 1;
        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nextReceiptNumber(): string
    {
        return 'RCV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function nextLaptopCode(): string
    {
        do { $code = 'LTP-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)); }
        while (Laptop::query()->where('sku', $code)->exists());
        return $code;
    }

    private function history(Pembelian $purchase, string $event, ?string $before, ?string $after, ?string $note, ?int $userId): void
    {
        PembelianHistory::create([
            'pembelian_id' => $purchase->id, 'event' => $event, 'status_sebelum' => $before,
            'status_sesudah' => $after, 'catatan' => $note, 'user_id' => $userId, 'created_at' => now(),
        ]);
    }

    private function filterPurchases(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) $query->whereDate('tanggal', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $query->whereDate('tanggal', '<=', $filters['date_to']);
        if (! empty($filters['supplier_id'])) $query->where('supplier_id', $filters['supplier_id']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $builder) => $builder->where('no_invoice', 'like', "%{$search}%")
                ->orWhere('keterangan', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('nama', 'like', "%{$search}%")));
        }
    }

    private function assertPurchase(Pembelian $purchase): void
    {
        abort_unless($purchase->jenis_pembelian === 'PEMBELIAN', 404);
    }
}
