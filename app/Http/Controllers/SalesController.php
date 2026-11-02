<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\Laptop;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pendapatan;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Services\CashLedgerService;
use App\Services\BusinessNotificationService;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function create(Request $request): View
    {
        $products = Produk::query()->with([
            'kategori:id,nama,jenis',
            'laptop' => fn ($query) => $query->whereIn('status', ['READY', 'DISPLAY'])->orderBy('id'),
        ])->where('status', 'AKTIF')->orderBy('nama_produk')->get();

        $catalog = [];
        foreach ($products as $product) {
            $serialUnits = $product->laptop->take((int) $product->stok);
            foreach ($serialUnits as $unit) {
                /** @var Laptop $unit */
                $specification = $product->spesifikasiRingkas($unit);
                $catalog[] = [
                    'product_id' => $product->id,
                    'laptop_id' => $unit->id,
                    'code' => $unit->sku,
                    'name' => $product->nama_produk,
                    'specification' => $specification,
                    'display_name' => implode(' · ', array_filter([$product->nama_produk, $specification])),
                    'category' => $product->kategori?->nama ?? 'Lainnya',
                    'category_type' => $product->kategori?->jenis ?? 'LAINNYA',
                    'stock' => 1,
                    'minimum_stock' => (int) $product->stok_minimum,
                    'price' => (float) ($unit->harga_jual ?: $product->harga_jual),
                    'serial' => $unit->serial_number,
                ];
            }

            $genericStock = max(0, (int) $product->stok - $serialUnits->count());
            if ($genericStock > 0 || $serialUnits->isEmpty()) {
                $catalog[] = [
                    'product_id' => $product->id,
                    'laptop_id' => null,
                    'code' => $product->kode_produk,
                    'name' => $product->nama_produk,
                    'specification' => $product->spesifikasiRingkas(),
                    'display_name' => implode(' · ', array_filter([$product->nama_produk, $product->spesifikasiRingkas()])),
                    'category' => $product->kategori?->nama ?? 'Lainnya',
                    'category_type' => $product->kategori?->jenis ?? 'LAINNYA',
                    'stock' => $genericStock ?: (int) $product->stok,
                    'minimum_stock' => (int) $product->stok_minimum,
                    'price' => (float) $product->harga_jual,
                    'serial' => null,
                ];
            }
        }

        return view('sales.create', [
            'catalog' => $catalog,
            'customers' => Pelanggan::query()->where('status', 'AKTIF')->orderBy('nama')->get(['id', 'kode_pelanggan', 'nama', 'telepon']),
            'recentSales' => Penjualan::query()->with('pelanggan:id,nama')->latest('id')->limit(5)->get(),
            'openShift' => KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->latest('opened_at')->first(),
            'requiresShift' => $request->user()->role?->name === 'KASIR',
            'canDiscount' => $request->user()->hasPermission('discount_sales'),
        ]);
    }

    public function store(Request $request, StockService $stock, CashLedgerService $cash, BusinessNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'pelanggan_id' => ['nullable', 'integer', 'exists:pelanggan,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'integer', 'exists:produk,id'],
            'items.*.laptop_id' => ['nullable', 'integer', 'exists:laptop,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.diskon_persen' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'items.*.diskon_tipe' => ['nullable', Rule::in(['PERSEN', 'NOMINAL'])],
            'items.*.diskon_nilai' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'diskon' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'diskon_tipe' => ['nullable', Rule::in(['NOMINAL', 'PERSEN'])],
            'diskon_transaksi' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'pajak_aktif' => ['nullable', 'boolean'],
            'pajak_persen' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'biaya_tambahan' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'biaya_tambahan_jenis' => ['nullable', Rule::in(['LAINNYA', 'ONGKIR', 'BIAYA_LAYANAN'])],
            'intent' => ['nullable', Rule::in(['sale', 'draft', 'receivable'])],
            'payments' => ['sometimes', 'array', 'max:10'],
            'payments.*.method' => ['required_with:payments', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT', 'PIUTANG'])],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'metode' => ['nullable', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT', 'PIUTANG'])],
            'dibayar' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasDiscount = (float) ($data['diskon_transaksi'] ?? $data['diskon'] ?? 0) > 0
            || collect($data['items'])->contains(fn (array $item) => (float) ($item['diskon_nilai'] ?? $item['diskon_persen'] ?? 0) > 0);
        if ($hasDiscount && ! $request->user()->hasPermission('discount_sales')) {
            throw ValidationException::withMessages(['diskon_transaksi' => 'Role Anda tidak memiliki akses untuk memberikan diskon.']);
        }

        $intent = $data['intent'] ?? 'sale';
        if ($intent !== 'draft') {
            $this->ensureOpenShift($request);
        }
        $selectedLaptopIds = collect($data['items'])->pluck('laptop_id')->filter()->map(fn ($id) => (int) $id);
        if ($selectedLaptopIds->count() !== $selectedLaptopIds->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Unit dengan nomor serial yang sama tidak dapat dimasukkan dua kali.']);
        }
        if (! empty($data['pelanggan_id']) && ! Pelanggan::query()->whereKey($data['pelanggan_id'])->where('status', 'AKTIF')->exists()) {
            throw ValidationException::withMessages(['pelanggan_id' => 'Pelanggan tersebut tidak aktif.']);
        }

        $sale = DB::transaction(function () use ($data, $request, $stock, $cash, $intent): Penjualan {
            $productIds = collect($data['items'])->pluck('produk_id')->map(fn ($id) => (int) $id)->unique()->values();
            $products = Produk::query()->with('kategori:id,nama,jenis')->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $requestedByProduct = collect($data['items'])->groupBy(fn ($item) => (int) $item['produk_id']);
            $lines = [];
            $grossSubtotal = 0.0;
            $lineDiscountTotal = 0.0;
            $types = [];

            foreach ($requestedByProduct as $productId => $items) {
                $product = $products->get((int) $productId);
                abort_unless($product && $product->status === 'AKTIF', 422, 'Produk tidak aktif.');
                $quantity = $items->sum(fn ($item) => (int) $item['qty']);
                if ($quantity > (int) $product->stok) {
                    throw ValidationException::withMessages(['items' => "Stok {$product->nama_produk} tidak mencukupi."]);
                }

                $readyUnits = Laptop::query()->where('produk_id', $product->id)->whereIn('status', ['READY', 'DISPLAY'])->lockForUpdate()->get();
                $genericCapacity = max(0, (int) $product->stok - min((int) $product->stok, $readyUnits->count()));
                $genericRequested = $items->filter(fn ($item) => empty($item['laptop_id']))->sum(fn ($item) => (int) $item['qty']);
                if ($genericRequested > $genericCapacity) {
                    throw ValidationException::withMessages(['items' => "Pilih unit dan nomor serial untuk {$product->nama_produk}."]);
                }

                foreach ($items as $item) {
                    $qty = (int) $item['qty'];
                    $laptop = null;
                    if (! empty($item['laptop_id'])) {
                        if ($qty !== 1) {
                            throw ValidationException::withMessages(['items' => 'Setiap laptop bernomor serial harus dimasukkan sebagai satu unit.']);
                        }
                        $laptop = $readyUnits->firstWhere('id', (int) $item['laptop_id']);
                        if (! $laptop || (int) $laptop->produk_id !== (int) $product->id) {
                            throw ValidationException::withMessages(['items' => 'Nomor serial tidak tersedia untuk produk tersebut.']);
                        }
                    }

                    $unitPrice = (float) ($laptop?->harga_jual ?: $product->harga_jual);
                    $unitCost = (float) ($laptop?->hpp ?: $product->harga_beli);
                    $gross = round($unitPrice * $qty, 2);
                    $lineDiscountType = $item['diskon_tipe'] ?? 'PERSEN';
                    $lineDiscountValue = (float) ($item['diskon_nilai'] ?? $item['diskon_persen'] ?? 0);
                    $lineDiscount = $lineDiscountType === 'PERSEN'
                        ? round($gross * $lineDiscountValue / 100, 2)
                        : round($lineDiscountValue, 2);
                    if ($lineDiscount > $gross) {
                        throw ValidationException::withMessages(['items' => "Diskon {$product->nama_produk} tidak boleh melebihi subtotal item."]);
                    }
                    $grossSubtotal = round($grossSubtotal + $gross, 2);
                    $lineDiscountTotal = round($lineDiscountTotal + $lineDiscount, 2);
                    $types[] = $product->kategori?->jenis;
                    $lines[] = [
                        'product' => $product, 'laptop' => $laptop, 'qty' => $qty,
                        'unit_price' => $unitPrice, 'unit_cost' => $unitCost,
                        'gross' => $gross, 'discount' => $lineDiscount,
                    ];
                }
            }

            $baseAfterLineDiscount = round($grossSubtotal - $lineDiscountTotal, 2);
            $discountValue = (float) ($data['diskon_transaksi'] ?? $data['diskon'] ?? 0);
            $discountType = $data['diskon_tipe'] ?? 'NOMINAL';
            $transactionDiscount = $discountType === 'PERSEN'
                ? round($baseAfterLineDiscount * $discountValue / 100, 2)
                : $discountValue;
            if ($transactionDiscount > $baseAfterLineDiscount) {
                throw ValidationException::withMessages(['diskon_transaksi' => 'Diskon transaksi tidak boleh melebihi subtotal.']);
            }

            $discountTotal = round($lineDiscountTotal + $transactionDiscount, 2);
            $taxRate = ! empty($data['pajak_aktif']) ? (float) ($data['pajak_persen'] ?? 11) : 0;
            $tax = round(($baseAfterLineDiscount - $transactionDiscount) * $taxRate / 100, 2);
            $additionalFee = (float) ($data['biaya_tambahan'] ?? 0);
            $total = round($grossSubtotal - $discountTotal + $tax + $additionalFee, 2);
            $note = trim((string) ($data['keterangan'] ?? ''));
            if ($additionalFee > 0) {
                $feeLabel = match ($data['biaya_tambahan_jenis'] ?? 'LAINNYA') {
                    'ONGKIR' => 'Ongkir',
                    'BIAYA_LAYANAN' => 'Biaya layanan',
                    default => 'Biaya lainnya',
                };
                $note = trim($note."\nBiaya tambahan: {$feeLabel}");
            }
            $paymentLines = collect($data['payments'] ?? [])->map(fn (array $payment) => [
                'method' => $payment['method'],
                'amount' => (float) ($payment['amount'] ?? 0),
                'reference' => trim((string) ($payment['reference'] ?? '')),
            ])->values()->all();
            if (! isset($data['payments']) && isset($data['metode'])) {
                $paymentLines = [['method' => $data['metode'], 'amount' => (float) ($data['dibayar'] ?? 0), 'reference' => '']];
            }

            if ($intent === 'draft') {
                $paymentLines = [];
                $paidTotal = 0.0;
                $appliedPayment = 0.0;
                $change = 0.0;
                $outstanding = 0.0;
            } else {
                if ($total > 0 && $paymentLines === [] && $intent !== 'receivable') {
                    throw ValidationException::withMessages(['payments' => 'Tambahkan metode pembayaran atau pilih Buat Piutang.']);
                }

                $hasReceivableLine = collect($paymentLines)->contains(fn (array $line) => $line['method'] === 'PIUTANG');
                $requestedReceivable = collect($paymentLines)->where('method', 'PIUTANG')->sum('amount');
                $paidTotal = collect($paymentLines)->where('method', '!=', 'PIUTANG')->sum('amount');
                $cashTotal = collect($paymentLines)->where('method', 'CASH')->sum('amount');
                $change = max(0, round($paidTotal - $total, 2));

                if ($change > 0) {
                    if ($cashTotal < $change) {
                        throw ValidationException::withMessages(['payments' => 'Pembayaran non-tunai tidak boleh melebihi total transaksi.']);
                    }

                    $remainingChange = $change;
                    for ($index = count($paymentLines) - 1; $index >= 0 && $remainingChange > 0; $index--) {
                        if ($paymentLines[$index]['method'] !== 'CASH') {
                            continue;
                        }
                        $deduction = min($paymentLines[$index]['amount'], $remainingChange);
                        $paymentLines[$index]['amount'] = round($paymentLines[$index]['amount'] - $deduction, 2);
                        $remainingChange = round($remainingChange - $deduction, 2);
                    }
                }

                $appliedPayment = min($paidTotal, $total);
                $outstanding = round($total - $appliedPayment, 2);
                $createReceivable = $outstanding > 0 && ($intent === 'receivable' || $hasReceivableLine);

                if ($hasReceivableLine && $requestedReceivable > 0 && abs($requestedReceivable - $outstanding) > 0.01) {
                    throw ValidationException::withMessages(['payments' => 'Nominal piutang harus sama dengan sisa pembayaran.']);
                }
                if ($outstanding > 0 && ! $createReceivable) {
                    throw ValidationException::withMessages(['payments' => 'Pembayaran belum lunas. Pilih Piutang atau gunakan tombol Buat Piutang.']);
                }
                if ($outstanding > 0 && empty($data['pelanggan_id'])) {
                    throw ValidationException::withMessages(['pelanggan_id' => 'Pilih pelanggan untuk transaksi yang memiliki sisa tagihan.']);
                }
            }
            $typeValues = array_values(array_unique(array_filter($types)));
            $saleType = count($typeValues) === 1 && in_array($typeValues[0], ['LAPTOP', 'AKSESORIS', 'SPAREPART'], true)
                ? $typeValues[0]
                : 'LAINNYA';
            $sale = Penjualan::create([
                'no_invoice' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'tanggal' => today(), 'pelanggan_id' => $data['pelanggan_id'] ?? null,
                'jenis_penjualan' => $saleType, 'subtotal' => $grossSubtotal, 'diskon' => $discountTotal,
                'pajak_persen' => $taxRate, 'pajak' => $tax, 'biaya_tambahan' => $additionalFee,
                'total' => $total, 'dibayar' => $appliedPayment,
                'kembalian' => $change,
                'status' => $intent === 'draft' ? 'DRAFT' : ($outstanding <= 0 ? 'SELESAI' : ($appliedPayment > 0 ? 'SEBAGIAN' : 'PIUTANG')),
                'user_id' => $request->user()->id, 'keterangan' => $note !== '' ? $note : null,
            ]);

            $remainingGlobalDiscount = $transactionDiscount;
            $baseForAllocation = max(0, $baseAfterLineDiscount);
            foreach ($lines as $index => $line) {
                $lineBase = round($line['gross'] - $line['discount'], 2);
                $globalDiscountShare = $index === array_key_last($lines)
                    ? $remainingGlobalDiscount
                    : ($baseForAllocation > 0 ? round($transactionDiscount * $lineBase / $baseForAllocation, 2) : 0);
                $remainingGlobalDiscount = round($remainingGlobalDiscount - $globalDiscountShare, 2);
                $detailDiscount = round($line['discount'] + $globalDiscountShare, 2);
                $net = round($line['gross'] - $detailDiscount, 2);

                PenjualanDetail::create([
                    'penjualan_id' => $sale->id, 'produk_id' => $line['product']->id,
                    'laptop_id' => $line['laptop']?->id, 'qty' => $line['qty'],
                    'harga' => $line['unit_price'], 'diskon' => $detailDiscount,
                    'hpp' => $line['unit_cost'], 'laba' => round($net - $line['unit_cost'] * $line['qty'], 2),
                    'subtotal' => $net,
                ]);

                if ($line['laptop'] && $intent !== 'draft') {
                    $line['laptop']->forceFill(['status' => 'SOLD', 'penjualan_id' => $sale->id])->save();
                }
            }

            if ($intent !== 'draft') {
                foreach ($requestedByProduct as $productId => $items) {
                    $quantity = $items->sum(fn ($item) => (int) $item['qty']);
                    $stock->decrease((int) $productId, $quantity, 'PENJUALAN', $request->user()->id, 'Penjualan '.$sale->no_invoice, 'penjualan', $sale->id);
                }

                foreach ($paymentLines as $payment) {
                    if ($payment['method'] !== 'PIUTANG' && $payment['amount'] > 0) {
                        $this->recordPaymentAndIncome($sale, $payment['amount'], $payment['method'], $request, $cash, $payment['reference']);
                    }
                }
            }
            if ($intent !== 'draft' && $outstanding > 0) {
                Pembayaran::create([
                    'tanggal' => now(), 'referensi_type' => 'PENJUALAN', 'referensi_id' => $sale->id,
                    'metode' => 'PIUTANG', 'nominal' => $outstanding, 'status' => 'PENDING',
                    'keterangan' => 'Sisa tagihan '.$sale->no_invoice, 'user_id' => $request->user()->id, 'created_at' => now(),
                ]);
            }

            return $sale;
        });

        if ($intent !== 'draft') {
            $sale->loadMissing('pelanggan.user');
            $notifications->notifyUsers(
                [$sale->pelanggan?->user],
                $request->user(),
                'Transaksi penjualan tercatat',
                "Transaksi {$sale->no_invoice} berhasil dicatat.",
                route('sales.show', $sale, false),
            );
        }

        return redirect()->route('sales.show', $sale)->with('status', "Penjualan {$sale->no_invoice} berhasil disimpan.");
    }

    public function show(Penjualan $sale): View
    {
        $sale->load([
            'pelanggan:id,nama,telepon',
            'details.produk:id,kode_produk,nama_produk,jenis_produk,merek,tipe,atribut',
            'details.laptop:id,kode_laptop,serial_number,merek,model_seri,kondisi,processor,ram,storage_jenis,storage_kapasitas,gpu,ukuran_layar,resolusi_layar',
            'user:id,name',
        ]);
        $payments = Pembayaran::query()->where('referensi_type', 'PENJUALAN')->where('referensi_id', $sale->id)->orderBy('id')->get();

        return view('sales.show', compact('sale', 'payments'));
    }

    public function collectPayment(Request $request, Penjualan $sale, CashLedgerService $cash, BusinessNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'nominal' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'metode' => ['required', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])],
        ]);
        $this->ensureOpenShift($request);

        DB::transaction(function () use ($sale, $data, $request, $cash): void {
            $sale = Penjualan::query()->lockForUpdate()->findOrFail($sale->id);
            $outstanding = round((float) $sale->total - (float) $sale->dibayar, 2);
            $amount = (float) $data['nominal'];
            if (in_array($sale->status, ['BATAL', 'DRAFT'], true) || $outstanding <= 0 || $amount > $outstanding) {
                throw ValidationException::withMessages(['nominal' => 'Nominal pembayaran melebihi sisa tagihan atau transaksi sudah ditutup.']);
            }

            $pendingReceivable = Pembayaran::query()->where('referensi_type', 'PENJUALAN')->where('referensi_id', $sale->id)
                ->where('metode', 'PIUTANG')->where('status', 'PENDING')->orderBy('id')->lockForUpdate()->first();
            if ($pendingReceivable) {
                $remainingReceivable = round((float) $pendingReceivable->nominal - $amount, 2);
                $pendingReceivable->forceFill([
                    'nominal' => max(0, $remainingReceivable),
                    'status' => $remainingReceivable <= 0 ? 'LUNAS' : 'PENDING',
                ])->save();
            }

            $sale->forceFill([
                'dibayar' => round((float) $sale->dibayar + $amount, 2),
                'status' => round((float) $sale->dibayar + $amount, 2) >= (float) $sale->total ? 'SELESAI' : 'SEBAGIAN',
            ])->save();
            $this->recordPaymentAndIncome($sale, $amount, $data['metode'], $request, $cash);
        });

        $sale->loadMissing('pelanggan.user');
        $notifications->notifyUsers(
            [$sale->pelanggan?->user],
            $request->user(),
            'Pembayaran penjualan tercatat',
            "Pembayaran tagihan {$sale->no_invoice} berhasil dicatat.",
            route('sales.show', $sale, false),
        );

        return back()->with('status', 'Pembayaran tagihan berhasil dicatat.');
    }

    private function recordPaymentAndIncome(Penjualan $sale, float $amount, string $method, Request $request, CashLedgerService $cash, string $reference = ''): void
    {
        Pembayaran::create([
            'tanggal' => now(), 'referensi_type' => 'PENJUALAN', 'referensi_id' => $sale->id,
            'metode' => $method, 'nominal' => $amount, 'status' => 'LUNAS',
            'keterangan' => trim('Pembayaran '.$sale->no_invoice.' '.$reference), 'user_id' => $request->user()->id, 'created_at' => now(),
        ]);
        $incomeType = match ($sale->jenis_penjualan) {
            'LAPTOP' => 'PENJUALAN_LAPTOP',
            'AKSESORIS' => 'PENJUALAN_AKSESORIS',
            default => 'LAINNYA',
        };
        Pendapatan::create([
            'tanggal' => now(), 'kode_pendapatan' => 'INC-'.$sale->no_invoice.'-'.Str::upper(Str::random(4)),
            'jenis_pendapatan' => $incomeType, 'sumber' => $sale->no_invoice,
            'referensi_type' => 'PENJUALAN', 'referensi_id' => $sale->id,
            'nominal' => $amount, 'keterangan' => 'Penerimaan '.$sale->no_invoice, 'user_id' => $request->user()->id,
        ]);

        if ($method === 'CASH') {
            $cash->receipt($amount, $request->user()->id, 'penjualan', $sale->id, 'Penjualan '.$sale->no_invoice);
        }
    }

    private function ensureOpenShift(Request $request): void
    {
        if ($request->user()->role?->name === 'KASIR' && ! KasirShift::query()->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift kasir terlebih dahulu sebelum membuat transaksi.']);
        }
    }
}
