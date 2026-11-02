<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pendapatan;
use App\Models\Produk;
use App\Models\Service;
use App\Models\ServiceApproval;
use App\Models\ServiceDetail;
use App\Models\ServiceProgress;
use App\Models\User;
use App\Services\BusinessNotificationService;
use App\Services\CashLedgerService;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    private const STATUSES = [
        'DITERIMA' => ['label' => 'Diterima', 'progress' => 0],
        'DIAGNOSA' => ['label' => 'Diagnosa', 'progress' => 20],
        'MENUNGGU_PERSETUJUAN' => ['label' => 'Menunggu Persetujuan', 'progress' => 30],
        'MENUNGGU_SPAREPART' => ['label' => 'Menunggu Sparepart', 'progress' => 45],
        'DALAM_PERBAIKAN' => ['label' => 'Dalam Perbaikan', 'progress' => 60],
        'TESTING' => ['label' => 'Testing', 'progress' => 80],
        'SELESAI' => ['label' => 'Selesai', 'progress' => 100],
        'SUDAH_DIAMBIL' => ['label' => 'Sudah Diambil', 'progress' => 100],
        'BATAL' => ['label' => 'Batal', 'progress' => 0],
    ];

    private const STATUS_TRANSITIONS = [
        'DITERIMA' => ['DIAGNOSA', 'BATAL'],
        'DIAGNOSA' => ['DALAM_PERBAIKAN', 'BATAL'],
        'MENUNGGU_PERSETUJUAN' => [],
        'MENUNGGU_SPAREPART' => ['DALAM_PERBAIKAN', 'BATAL'],
        'DALAM_PERBAIKAN' => ['TESTING', 'BATAL'],
        'TESTING' => ['DALAM_PERBAIKAN', 'SELESAI', 'BATAL'],
        'SELESAI' => [],
        'SUDAH_DIAMBIL' => [],
        'BATAL' => [],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'teknisi_id' => ['nullable', 'integer', 'exists:users,id'],
            'pelanggan_id' => ['nullable', 'integer', 'exists:pelanggan,id'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $user = $request->user();
        $base = $this->visibleServices($user);
        $statusCounts = collect(self::STATUSES)->map(fn (array $details, string $status) => [
            'status' => $status,
            'label' => $details['label'],
            'count' => (clone $base)->where('status', $status)->count(),
        ])->values();

        $query = (clone $base)->with(['pelanggan:id,nama', 'teknisi:id,name'])->latest('tanggal_masuk');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('tanggal_masuk', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('tanggal_masuk', '<=', $filters['date_to']);
        }
        if (! empty($filters['teknisi_id']) && $user->role?->name !== 'TEKNISI') {
            $query->where('teknisi_id', $filters['teknisi_id']);
        }
        if (! empty($filters['pelanggan_id'])) {
            $query->where('pelanggan_id', $filters['pelanggan_id']);
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('no_service', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('merek', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhereHas('pelanggan', fn (Builder $customer) => $customer->where('nama', 'like', "%{$search}%"));
            });
        }

        return view('service.index', [
            'services' => $query->paginate(12)->withQueryString(),
            'statusCounts' => $statusCounts,
            'technicians' => User::query()->whereHas('role', fn (Builder $role) => $role->where('name', 'TEKNISI'))
                ->where('status', 'AKTIF')->orderBy('name')->get(['id', 'name']),
            'customers' => $user->role?->name === 'PELANGGAN'
                ? collect()
                : Pelanggan::query()->where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama']),
            'filters' => $filters,
            'canViewBilling' => $user->role?->name !== 'TEKNISI',
        ]);
    }

    public function create(Request $request): View
    {
        return view('service.create', [
            'customers' => Pelanggan::query()->where('status', 'AKTIF')->orderBy('nama')
                ->get(['id', 'kode_pelanggan', 'nama', 'telepon']),
            'technicians' => User::query()->whereHas('role', fn (Builder $query) => $query->where('name', 'TEKNISI'))
                ->where('status', 'AKTIF')->orderBy('name')->get(['id', 'name']),
            'isCashier' => $request->user()->role?->name === 'KASIR',
            'openShift' => KasirShift::query()->where('user_id', $request->user()->id)
                ->where('status', 'OPEN')->latest('opened_at')->first(),
        ]);
    }

    public function store(Request $request, BusinessNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'jenis_perangkat' => ['required', 'string', 'max:100'],
            'merek' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'keluhan' => ['required', 'string', 'max:5000'],
            'teknisi_id' => ['required', 'integer', 'exists:users,id'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureOpenShift($request);
        if (! Pelanggan::query()->whereKey($data['pelanggan_id'])->where('status', 'AKTIF')->exists()) {
            throw ValidationException::withMessages(['pelanggan_id' => 'Pilih pelanggan yang aktif.']);
        }
        if (! User::query()->whereKey($data['teknisi_id'])
            ->where('status', 'AKTIF')->whereHas('role', fn (Builder $role) => $role->where('name', 'TEKNISI'))->exists()) {
            throw ValidationException::withMessages(['teknisi_id' => 'Pilih teknisi aktif.']);
        }

        $service = DB::transaction(function () use ($data, $request): Service {
            $service = Service::create([
                'no_service' => 'SRV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'pelanggan_id' => $data['pelanggan_id'],
                'tanggal_masuk' => now(),
                'jenis_perangkat' => $data['jenis_perangkat'],
                'merek' => $data['merek'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'keluhan' => $data['keluhan'],
                'teknisi_id' => $data['teknisi_id'],
                'status' => 'DITERIMA',
                'jasa' => 0,
                'diskon' => 0,
                'pajak_persen' => 0,
                'pajak' => 0,
                'biaya_tambahan' => 0,
                'total' => 0,
                'dibayar' => 0,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $this->recordProgress($service, 'DITERIMA', 0, 'Perangkat diterima untuk pemeriksaan.', $request->user()->id);

            return $service;
        });

        $notifications->notifyServiceStakeholders(
            $service,
            $request->user(),
            'Service baru diterima',
            "Service {$service->no_service} telah diterima dan menunggu pemeriksaan.",
        );

        return redirect()->route('service.print', [$service, 'intake']);
    }

    public function show(Request $request, Service $service): View
    {
        $this->assertCanView($request->user(), $service);
        $user = $request->user();
        $service->load([
            'pelanggan:id,nama,kode_pelanggan,telepon,email,alamat,user_id',
            'teknisi:id,name',
            'details.produk:id,kode_produk,nama_produk,harga_jual',
            'details.approval:id,status',
            'progress.updater:id,name',
            'approvals.requestedBy:id,name',
            'approvals.approvedBy:id,name',
            'approvals.details.produk:id,kode_produk,nama_produk,harga_jual,stok',
        ]);
        $canViewBilling = $user->role?->name !== 'TEKNISI' && $user->hasPermission('view_service');
        $payments = ! $canViewBilling
            ? collect()
            : Pembayaran::query()->where('referensi_type', 'SERVICE')->where('referensi_id', $service->id)->orderBy('id')->get();
        $pendingApproval = $service->approvals->where('status', 'PENDING')->sortByDesc('id')->first();
        $canUpdateProgress = $this->canUpdateProgress($user, $service);
        $canRequestApproval = $this->canRequestApproval($user, $service) && ! $pendingApproval;
        $canDecideApproval = $pendingApproval && $this->canDecideApproval($user, $service);
        $products = $canRequestApproval
            ? Produk::query()->where('status', 'AKTIF')->orderBy('nama_produk')->get(['id', 'kode_produk', 'nama_produk', 'stok', 'harga_jual'])
            : collect();
        $statusProgress = collect(self::STATUSES)->map(fn (array $item, string $status) => [
            'status' => $status,
            'label' => $item['label'],
            'progress' => $item['progress'],
        ])->values();
        $availableStatuses = array_merge(
            [$service->status],
            self::STATUS_TRANSITIONS[$service->status] ?? [],
        );

        return view('service.show', compact(
            'service', 'payments', 'pendingApproval', 'canViewBilling', 'canUpdateProgress',
            'canRequestApproval', 'canDecideApproval', 'products', 'statusProgress', 'availableStatuses'
        ));
    }

    public function printDocument(Request $request, Service $service, string $document): View
    {
        $this->assertCanView($request->user(), $service);
        abort_unless(in_array($document, ['intake', 'final'], true), 404);
        abort_if($document === 'final' && (
            $request->user()->role?->name === 'TEKNISI' || ! $request->user()->hasPermission('view_service')
        ), 403);
        abort_if(
            $document === 'final' && ! in_array($service->status, ['SELESAI', 'SUDAH_DIAMBIL'], true),
            404,
        );

        $service->load([
            'pelanggan:id,nama,telepon,alamat',
            'teknisi:id,name',
            'details.produk:id,nama_produk',
        ]);
        $payments = $document === 'final'
            ? Pembayaran::query()
                ->where('referensi_type', 'SERVICE')
                ->where('referensi_id', $service->id)
                ->where('status', 'LUNAS')
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get()
            : collect();
        $balance = max(0, (float) $service->total - (float) $service->dibayar);

        return view('service.print', compact('service', 'document', 'payments', 'balance'));
    }

    public function updateProgress(Request $request, Service $service, StockService $stock, BusinessNotificationService $notifications): RedirectResponse
    {
        abort_unless($this->canUpdateProgress($request->user(), $service), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'diagnosa' => ['nullable', 'string', 'max:5000'],
            'catatan' => ['required', 'string', 'max:5000'],
        ]);

        $previousStatus = $service->status;
        DB::transaction(function () use ($service, $data, $request, $stock): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            if (in_array($locked->status, ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL'], true)) {
                throw ValidationException::withMessages(['status' => 'Service yang telah selesai atau dibatalkan tidak dapat diubah progress-nya.']);
            }
            if ($locked->approvals()->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages(['status' => 'Selesaikan persetujuan biaya terlebih dahulu.']);
            }
            $allowedStatuses = array_merge(
                [$locked->status],
                self::STATUS_TRANSITIONS[$locked->status] ?? [],
            );
            if (! in_array($data['status'], $allowedStatuses, true)) {
                throw ValidationException::withMessages(['status' => 'Perpindahan status service tidak sesuai alur pengerjaan.']);
            }
            if ($data['status'] === 'DIAGNOSA' && blank($data['diagnosa'] ?? $locked->diagnosa)) {
                throw ValidationException::withMessages(['diagnosa' => 'Isi hasil pemeriksaan sebelum melanjutkan ke tahap diagnosa.']);
            }
            if ($data['status'] === 'MENUNGGU_PERSETUJUAN') {
                throw ValidationException::withMessages(['status' => 'Gunakan form pengajuan estimasi agar persetujuan memiliki rincian biaya.']);
            }
            if (in_array($data['status'], ['SELESAI', 'SUDAH_DIAMBIL'], true) && $locked->approvals()->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages(['status' => 'Service masih menunggu persetujuan customer.']);
            }
            if ($data['status'] === 'SUDAH_DIAMBIL') {
                if ($locked->status !== 'SELESAI') {
                    throw ValidationException::withMessages(['status' => 'Service harus berstatus Selesai sebelum dicatat diambil.']);
                }
                if ((float) $locked->dibayar < (float) $locked->total) {
                    throw ValidationException::withMessages(['status' => 'Lunasi tagihan service sebelum perangkat diserahkan.']);
                }
            }
            if ($data['status'] === 'BATAL' && (float) $locked->dibayar > 0) {
                throw ValidationException::withMessages(['status' => 'Service yang sudah menerima pembayaran tidak dapat dibatalkan dari progress.']);
            }
            if ($data['status'] === 'BATAL' && $locked->details()->whereNotNull('stock_deducted_at')->exists()) {
                throw ValidationException::withMessages(['status' => 'Service dengan sparepart terpakai perlu diselesaikan melalui proses koreksi stok.']);
            }
            if ($data['status'] === 'DALAM_PERBAIKAN') {
                if (! $this->consumeApprovedParts($locked, $request, $stock)) {
                    throw ValidationException::withMessages([
                        'status' => 'Stok sparepart belum mencukupi untuk memulai perbaikan.',
                    ]);
                }
            }

            $progress = $data['progress_percent'] ?? self::STATUSES[$data['status']]['progress'];
            $locked->forceFill([
                'status' => $data['status'],
                'diagnosa' => $data['diagnosa'] ?? $locked->diagnosa,
                'tanggal_selesai' => $data['status'] === 'SELESAI'
                    ? ($locked->tanggal_selesai ?? now())
                    : ($data['status'] === 'BATAL' ? null : $locked->tanggal_selesai),
            ])->save();
            $this->recordProgress($locked, $data['status'], $progress, $data['catatan'], $request->user()->id);
        });

        if ($previousStatus !== $data['status']) {
            $notifications->notifyServiceStakeholders(
                $service,
                $request->user(),
                'Progress service diperbarui',
                "Service {$service->no_service} sekarang berstatus ".str_replace('_', ' ', strtolower($data['status'])).'.',
            );
        }

        return back()->with('status', 'Progress service berhasil diperbarui.');
    }

    public function requestApproval(Request $request, Service $service, BusinessNotificationService $notifications): RedirectResponse
    {
        abort_unless($this->canRequestApproval($request->user(), $service), 403);
        $data = $request->validate([
            'jasa' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'diskon_tipe' => ['required', Rule::in(['NOMINAL', 'PERSEN'])],
            'diskon_nilai' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'pajak_aktif' => ['nullable', 'boolean'],
            'pajak_persen' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'biaya_tambahan' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'parts' => ['nullable', 'array', 'max:30'],
            'parts.*.produk_id' => ['required', 'integer', 'distinct', 'exists:produk,id'],
            'parts.*.qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'catatan' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($service, $data, $request): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($locked->approvals()->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages(['approval' => 'Masih ada pengajuan biaya yang menunggu keputusan.']);
            }
            if (in_array($locked->status, ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL'], true)) {
                throw ValidationException::withMessages(['approval' => 'Estimasi tidak dapat diubah setelah service selesai atau dibatalkan.']);
            }

            $parts = collect($data['parts'] ?? [])->filter(fn (array $part) => ! empty($part['produk_id']))->values();
            $productIds = $parts->pluck('produk_id')->map(fn ($id) => (int) $id)->unique()->values();
            $products = $productIds->isEmpty()
                ? collect()
                : Produk::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $newPartLines = [];
            $newPartSubtotal = 0.0;
            foreach ($parts as $part) {
                $product = $products->get((int) $part['produk_id']);
                if (! $product || $product->status !== 'AKTIF') {
                    throw ValidationException::withMessages(['parts' => 'Pilih produk sparepart yang aktif.']);
                }
                $qty = (int) $part['qty'];
                $price = (float) $product->harga_jual;
                $subtotal = round($price * $qty, 2);
                $newPartSubtotal = round($newPartSubtotal + $subtotal, 2);
                $newPartLines[] = compact('product', 'qty', 'price', 'subtotal');
            }

            $approvedPartSubtotal = ServiceDetail::query()->where('service_id', $locked->id)
                ->where(function (Builder $query) {
                    $query->whereNull('approval_id')->orWhereHas('approval', fn (Builder $approval) => $approval->where('status', 'APPROVED'));
                })->sum('subtotal');
            $jasa = (float) $data['jasa'];
            $subtotal = round($jasa + (float) $approvedPartSubtotal + $newPartSubtotal, 2);
            $discountValue = (float) ($data['diskon_nilai'] ?? 0);
            $discount = ($data['diskon_tipe'] ?? 'NOMINAL') === 'PERSEN'
                ? round($subtotal * $discountValue / 100, 2)
                : round($discountValue, 2);
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['diskon_nilai' => 'Diskon tidak boleh melebihi subtotal jasa dan sparepart.']);
            }
            $taxRate = ! empty($data['pajak_aktif']) ? (float) ($data['pajak_persen'] ?? 0) : 0;
            $tax = round(($subtotal - $discount) * $taxRate / 100, 2);
            $fee = (float) ($data['biaya_tambahan'] ?? 0);
            $estimatedTotal = round($subtotal - $discount + $tax + $fee, 2);
            if ($estimatedTotal < (float) $locked->dibayar) {
                throw ValidationException::withMessages(['jasa' => 'Total estimasi tidak boleh lebih rendah dari pembayaran yang sudah diterima.']);
            }

            $previousProgress = (int) ($locked->progress()->latest('id')->value('progress_percent') ?? 0);
            $approval = ServiceApproval::create([
                'service_id' => $locked->id,
                'status_sebelum' => $locked->status,
                'progress_sebelum' => $previousProgress,
                'jasa_sebelum' => $locked->jasa,
                'diskon_sebelum' => $locked->diskon,
                'pajak_persen_sebelum' => $locked->pajak_persen,
                'pajak_sebelum' => $locked->pajak,
                'biaya_tambahan_sebelum' => $locked->biaya_tambahan,
                'total_sebelum' => $locked->total,
                'estimasi' => $estimatedTotal,
                'status' => 'PENDING',
                'requested_by' => $request->user()->id,
                'catatan' => $data['catatan'],
            ]);

            foreach ($newPartLines as $line) {
                ServiceDetail::create([
                    'service_id' => $locked->id,
                    'approval_id' => $approval->id,
                    'produk_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'harga' => $line['price'],
                    'hpp' => $line['product']->harga_beli,
                    'subtotal' => $line['subtotal'],
                    'stock_deducted_at' => null,
                ]);
            }

            $locked->forceFill([
                'jasa' => $jasa,
                'diskon' => $discount,
                'pajak_persen' => $taxRate,
                'pajak' => $tax,
                'biaya_tambahan' => $fee,
                'total' => $estimatedTotal,
                'status' => 'MENUNGGU_PERSETUJUAN',
            ])->save();
            $this->recordProgress($locked, 'MENUNGGU_PERSETUJUAN', 30, $data['catatan'], $request->user()->id);
        });

        $notifications->notifyServiceStakeholders(
            $service,
            $request->user(),
            'Persetujuan estimasi diperlukan',
            "Estimasi biaya service {$service->no_service} menunggu persetujuan pelanggan.",
        );

        return back()->with('status', 'Estimasi biaya dikirim untuk persetujuan. Stok sparepart belum dipotong.');
    }

    public function decideApproval(Request $request, Service $service, ServiceApproval $approval, StockService $stock, BusinessNotificationService $notifications): RedirectResponse
    {
        abort_unless((int) $approval->service_id === (int) $service->id, 404);
        abort_unless($this->canDecideApproval($request->user(), $service), 404);
        $staffDecision = $request->user()->role?->name !== 'PELANGGAN';
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'catatan' => [$staffDecision ? 'required' : 'nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($service, $approval, $data, $request, $stock): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            $lockedApproval = ServiceApproval::query()->where('service_id', $locked->id)->lockForUpdate()->findOrFail($approval->id);
            if ($lockedApproval->status !== 'PENDING') {
                throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
            }
            if ($locked->status !== 'MENUNGGU_PERSETUJUAN') {
                throw ValidationException::withMessages(['decision' => 'Service tidak lagi menunggu keputusan estimasi.']);
            }

            $note = trim((string) $lockedApproval->catatan);
            if (! empty($data['catatan'])) {
                $note = trim($note."\nKeputusan: ".$data['catatan']);
            }

            if ($data['decision'] === 'APPROVED') {
                if ((float) $lockedApproval->estimasi < (float) $locked->dibayar) {
                    throw ValidationException::withMessages(['decision' => 'Estimasi yang disetujui lebih kecil dari pembayaran yang sudah diterima.']);
                }
                $lockedApproval->forceFill([
                    'status' => 'APPROVED',
                    'nominal' => $lockedApproval->estimasi,
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'catatan' => $note,
                ])->save();
                $partsReady = $this->consumeApprovedParts($locked, $request, $stock);
                $nextStatus = $partsReady ? 'DALAM_PERBAIKAN' : 'MENUNGGU_SPAREPART';
                $nextProgress = self::STATUSES[$nextStatus]['progress'];
                $locked->forceFill(['status' => $nextStatus])->save();
                $this->syncPendingReceivable($locked, $request->user()->id);
                $this->recordProgress(
                    $locked,
                    $nextStatus,
                    $nextProgress,
                    $partsReady ? 'Estimasi disetujui. Pengerjaan dapat dimulai.' : 'Estimasi disetujui. Menunggu ketersediaan sparepart.',
                    $request->user()->id,
                );
            } else {
                $lockedApproval->forceFill([
                    'status' => 'REJECTED',
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                    'catatan' => $note,
                ])->save();
                ServiceDetail::query()->where('approval_id', $lockedApproval->id)->delete();
                $previousStatus = $lockedApproval->status_sebelum ?: 'DIAGNOSA';
                $locked->forceFill([
                    'jasa' => $lockedApproval->jasa_sebelum,
                    'diskon' => $lockedApproval->diskon_sebelum,
                    'pajak_persen' => $lockedApproval->pajak_persen_sebelum,
                    'pajak' => $lockedApproval->pajak_sebelum,
                    'biaya_tambahan' => $lockedApproval->biaya_tambahan_sebelum,
                    'total' => $lockedApproval->total_sebelum,
                    'status' => $previousStatus,
                ])->save();
                $this->syncPendingReceivable($locked, $request->user()->id);
                $this->recordProgress(
                    $locked,
                    $previousStatus,
                    $lockedApproval->progress_sebelum,
                    'Estimasi ditolak. '.($data['catatan'] ?: 'Menunggu diagnosis atau estimasi revisi.'),
                    $request->user()->id,
                );
            }
        });

        $notifications->notifyUsers(
            [$service->teknisi, User::query()->find($approval->requested_by)],
            $request->user(),
            'Keputusan estimasi service',
            "Estimasi untuk service {$service->no_service} telah ".($data['decision'] === 'APPROVED' ? 'disetujui' : 'ditolak').'.',
            route('service.show', $service, false),
        );

        return back()->with('status', $data['decision'] === 'APPROVED'
            ? 'Estimasi disetujui dan tercatat pada service.'
            : 'Estimasi ditolak. Service kembali ke status sebelumnya.');
    }

    public function handover(Request $request, Service $service, BusinessNotificationService $notifications): RedirectResponse
    {
        abort_unless(
            $request->user()->role?->name === 'KASIR' || $request->user()->hasPermission('edit_service'),
            403,
        );
        $data = $request->validate([
            'nama_penerima' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($service, $data, $request): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($locked->status !== 'SELESAI') {
                throw ValidationException::withMessages(['handover' => 'Perangkat hanya dapat diserahkan setelah service selesai.']);
            }
            if ((float) $locked->dibayar < (float) $locked->total) {
                throw ValidationException::withMessages(['handover' => 'Lunasi tagihan service sebelum perangkat diserahkan.']);
            }

            $locked->forceFill(['status' => 'SUDAH_DIAMBIL'])->save();
            $note = 'Perangkat diserahkan kepada '.$data['nama_penerima'].'.';
            if (filled($data['catatan'] ?? null)) {
                $note .= ' '.$data['catatan'];
            }
            $this->recordProgress($locked, 'SUDAH_DIAMBIL', 100, $note, $request->user()->id);
        });

        $notifications->notifyServiceStakeholders(
            $service,
            $request->user(),
            'Perangkat service telah diserahkan',
            "Perangkat untuk service {$service->no_service} telah diserahkan kepada {$data['nama_penerima']}.",
        );

        return back()->with('status', 'Serah-terima perangkat berhasil dicatat.');
    }

    public function collectPayment(Request $request, Service $service, CashLedgerService $cash, BusinessNotificationService $notifications): RedirectResponse
    {
        abort_if($request->user()->role?->name === 'TEKNISI', 403);
        $data = $request->validate([
            'payments' => ['sometimes', 'array', 'max:8'],
            'payments.*.metode' => ['required_with:payments', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])],
            'payments.*.nominal' => ['required_with:payments', 'numeric', 'gt:0', 'decimal:0,2'],
            'payments.*.keterangan' => ['nullable', 'string', 'max:255'],
            'nominal' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'metode' => ['nullable', Rule::in(['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])],
        ]);
        $payments = $data['payments'] ?? (isset($data['nominal'], $data['metode'])
            ? [['nominal' => $data['nominal'], 'metode' => $data['metode'], 'keterangan' => null]]
            : []);
        if ($payments === []) {
            throw ValidationException::withMessages(['payments' => 'Masukkan setidaknya satu pembayaran.']);
        }
        $this->ensureOpenShift($request);

        DB::transaction(function () use ($service, $payments, $request, $cash): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($locked->status === 'BATAL' || $locked->approvals()->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages(['nominal' => 'Service batal atau masih menunggu persetujuan biaya.']);
            }
            $outstanding = round((float) $locked->total - (float) $locked->dibayar, 2);
            $amount = collect($payments)->sum(fn (array $payment) => (float) $payment['nominal']);
            if ($outstanding <= 0 || $amount > $outstanding) {
                throw ValidationException::withMessages(['payments' => 'Jumlah pembayaran melebihi sisa tagihan service.']);
            }

            foreach ($payments as $payment) {
                $method = $payment['metode'];
                $nominal = (float) $payment['nominal'];
                $reference = trim((string) ($payment['keterangan'] ?? ''));
                $description = trim('Pembayaran service '.$locked->no_service.' '.$reference);
                Pembayaran::create([
                    'tanggal' => now(), 'referensi_type' => 'SERVICE', 'referensi_id' => $locked->id,
                    'metode' => $method, 'nominal' => $nominal, 'status' => 'LUNAS',
                    'keterangan' => $description, 'user_id' => $request->user()->id, 'created_at' => now(),
                ]);
                Pendapatan::create([
                    'tanggal' => now(),
                    'kode_pendapatan' => 'INC-'.$locked->no_service.'-'.Str::upper(Str::random(5)),
                    'jenis_pendapatan' => 'SERVICE', 'sumber' => $locked->no_service,
                    'referensi_type' => 'SERVICE', 'referensi_id' => $locked->id,
                    'nominal' => $nominal, 'keterangan' => $description, 'user_id' => $request->user()->id,
                ]);
                if ($method === 'CASH') {
                    $cash->receipt($nominal, $request->user()->id, 'service', $locked->id, $description);
                }
            }

            $locked->forceFill(['dibayar' => round((float) $locked->dibayar + $amount, 2)])->save();
            $this->syncPendingReceivable($locked, $request->user()->id);
        });

        $service->loadMissing('pelanggan.user');
        $notifications->notifyUsers(
            [$service->pelanggan?->user],
            $request->user(),
            'Pembayaran service tercatat',
            "Pembayaran untuk service {$service->no_service} telah tercatat.",
            route('service.show', $service, false),
        );

        return back()->with('status', 'Pembayaran service berhasil dicatat.');
    }

    private function visibleServices(User $user): Builder
    {
        $query = Service::query();
        if ($user->role?->name === 'TEKNISI') {
            $query->where('teknisi_id', $user->id);
        } elseif ($user->role?->name === 'PELANGGAN') {
            $query->whereHas('pelanggan', fn (Builder $customer) => $customer->where('user_id', $user->id));
        }

        return $query;
    }

    private function assertCanView(User $user, Service $service): void
    {
        if ($user->role?->name === 'TEKNISI' && (int) $service->teknisi_id !== (int) $user->id) {
            abort(404);
        }
        if ($user->role?->name === 'PELANGGAN' && ! $service->pelanggan()->where('user_id', $user->id)->exists()) {
            abort(404);
        }
    }

    private function canUpdateProgress(User $user, Service $service): bool
    {
        if (in_array($service->status, ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL'], true)) {
            return false;
        }

        if ($user->hasPermission('edit_service')) {
            return $user->role?->name !== 'TEKNISI' || (int) $service->teknisi_id === (int) $user->id;
        }

        return $user->hasPermission('update_service_progress')
            && ($user->role?->name !== 'TEKNISI' || (int) $service->teknisi_id === (int) $user->id);
    }

    private function canRequestApproval(User $user, Service $service): bool
    {
        if (! in_array($service->status, ['DIAGNOSA', 'MENUNGGU_SPAREPART', 'DALAM_PERBAIKAN', 'TESTING'], true)
            || blank($service->diagnosa)) {
            return false;
        }

        if ($user->role?->name === 'TEKNISI') {
            return $user->hasPermission('update_service_progress') && (int) $service->teknisi_id === (int) $user->id;
        }

        if ($user->role?->name === 'KASIR') {
            return $user->hasPermission('create_service');
        }

        return $user->hasPermission('edit_service');
    }

    private function canDecideApproval(User $user, Service $service): bool
    {
        if ($user->role?->name === 'PELANGGAN') {
            return $service->pelanggan()->where('user_id', $user->id)->exists();
        }

        return $user->role?->name !== 'TEKNISI' && $user->hasPermission('view_service');
    }

    private function recordProgress(Service $service, string $status, int $progress, ?string $note, ?int $userId): void
    {
        ServiceProgress::create([
            'service_id' => $service->id,
            'status' => $status,
            'progress_percent' => max(0, min(100, $progress)),
            'catatan' => $note,
            'updated_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Returns false when an approved part is still unavailable; no partial stock issue is made. */
    private function consumeApprovedParts(Service $service, Request $request, StockService $stock): bool
    {
        $details = ServiceDetail::query()->where('service_id', $service->id)
            ->whereNull('stock_deducted_at')
            ->whereHas('approval', fn (Builder $approval) => $approval->where('status', 'APPROVED'))
            ->get();
        if ($details->isEmpty()) {
            return true;
        }

        $requiredByProduct = $details->groupBy('produk_id')->map(fn ($lines) => $lines->sum(fn (ServiceDetail $line) => (int) $line->qty));
        $products = Produk::query()->whereIn('id', $requiredByProduct->keys())->lockForUpdate()->get()->keyBy('id');
        foreach ($requiredByProduct as $productId => $qty) {
            $product = $products->get((int) $productId);
            if (! $product || $product->status !== 'AKTIF' || (float) $product->stok < $qty) {
                return false;
            }
        }

        foreach ($requiredByProduct as $productId => $qty) {
            $product = $products->get((int) $productId);
            $stock->decrease((int) $productId, (int) $qty, 'SERVICE', $request->user()->id, 'Sparepart service '.$service->no_service, 'service', $service->id);
        }
        ServiceDetail::query()->whereIn('id', $details->pluck('id'))->update(['stock_deducted_at' => now()]);

        return true;
    }

    private function syncPendingReceivable(Service $service, int $userId): void
    {
        $outstanding = round((float) $service->total - (float) $service->dibayar, 2);
        $receivable = Pembayaran::query()->where('referensi_type', 'SERVICE')
            ->where('referensi_id', $service->id)->where('metode', 'PIUTANG')
            ->where('status', 'PENDING')->orderBy('id')->lockForUpdate()->first();

        if ($outstanding <= 0) {
            if ($receivable) {
                $receivable->forceFill(['nominal' => 0, 'status' => 'LUNAS'])->save();
            }

            return;
        }

        if ($receivable) {
            $receivable->forceFill(['nominal' => $outstanding])->save();

            return;
        }

        Pembayaran::create([
            'tanggal' => now(), 'referensi_type' => 'SERVICE', 'referensi_id' => $service->id,
            'metode' => 'PIUTANG', 'nominal' => $outstanding, 'status' => 'PENDING',
            'keterangan' => 'Sisa tagihan service '.$service->no_service,
            'user_id' => $userId, 'created_at' => now(),
        ]);
    }

    private function ensureOpenShift(Request $request): void
    {
        if ($request->user()->role?->name === 'KASIR' && ! KasirShift::query()
            ->where('user_id', $request->user()->id)->where('status', 'OPEN')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Buka shift kasir terlebih dahulu sebelum menerima atau menagih service.']);
        }
    }
}
