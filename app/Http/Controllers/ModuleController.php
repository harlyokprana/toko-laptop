<?php

namespace App\Http\Controllers;

use App\Models\KasMutasi;
use App\Models\Pembelian;
use App\Models\Pendapatan;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Service;
use App\Models\ServiceProgress;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ModuleController extends Controller
{
    public function show(Request $request, string $module): View
    {
        $titles = [
            'sales' => 'Penjualan',
            'purchases' => 'Pembelian',
            'service' => $request->user()->role?->name === 'PELANGGAN' ? 'Service Saya' : 'Service',
            'service-progress' => 'Progress Service',
            'income' => 'Pendapatan',
            'expenses' => 'Pengeluaran',
            'cash' => 'Kas',
            'reports' => 'Laporan',
            'profit-loss' => 'Laba Rugi',
            'ai' => 'AI Assistant',
            'users' => 'User Management',
        ];

        abort_unless(isset($titles[$module]), 404);

        $user = $request->user();
        $stats = [];
        $columns = [];
        $rows = null;
        $notice = null;

        switch ($module) {
            case 'sales':
                $query = Penjualan::query()->with('pelanggan:id,nama')->latest('tanggal');
                if ($request->query('status_group') === 'receivable') {
                    $query->whereIn('status', ['SEBAGIAN', 'PIUTANG']);
                } elseif (in_array($request->query('status'), ['DRAFT', 'SELESAI', 'SEBAGIAN', 'PIUTANG', 'BATAL'], true)) {
                    $query->where('status', $request->query('status'));
                }
                if ($search = trim((string) $request->query('q'))) {
                    $query->where(function (Builder $builder) use ($search) {
                        $builder->where('no_invoice', 'like', "%{$search}%")
                            ->orWhereHas('pelanggan', fn (Builder $customer) => $customer->where('nama', 'like', "%{$search}%"));
                    });
                }
                $stats = [
                    $this->stat('Transaksi bulan ini', number_format((clone $query)->whereBetween('tanggal', $this->monthRange())->whereNotIn('status', ['BATAL', 'DRAFT'])->count()), 'Draft dan transaksi batal tidak dihitung'),
                    $this->stat('Nilai penjualan bulan ini', $this->money((clone $query)->whereBetween('tanggal', $this->monthRange())->whereNotIn('status', ['BATAL', 'DRAFT'])->sum('total')), 'Total transaksi bersih dari pembatalan dan draft'),
                ];
                $columns = ['invoice' => 'No. Invoice', 'tanggal' => 'Tanggal', 'pelanggan' => 'Pelanggan', 'jenis' => 'Jenis', 'total' => 'Total', 'status' => 'Status'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (Penjualan $sale) => [
                    'id' => $sale->id,
                    'invoice' => $sale->no_invoice,
                    'tanggal' => $sale->tanggal?->format('d M Y') ?? '—',
                    'pelanggan' => $sale->pelanggan?->nama ?? 'Umum',
                    'jenis' => str_replace('_', ' ', $sale->jenis_penjualan),
                    'total' => $this->money($sale->total),
                    'status' => str_replace('_', ' ', $sale->status),
                ]);
                break;

            case 'purchases':
                $query = Pembelian::query()->with('supplier:id,nama')->latest('tanggal');
                if (in_array($request->query('status'), ['DRAFT', 'SELESAI', 'SEBAGIAN', 'BATAL'], true)) {
                    $query->where('status', $request->query('status'));
                }
                if ($search = trim((string) $request->query('q'))) {
                    $query->where(function (Builder $builder) use ($search) {
                        $builder->where('no_invoice', 'like', "%{$search}%")
                            ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('nama', 'like', "%{$search}%"));
                    });
                }
                $stats = [
                    $this->stat('Pembelian bulan ini', number_format((clone $query)->whereBetween('tanggal', $this->monthRange())->count()), 'Jumlah invoice pembelian'),
                    $this->stat('Nilai pembelian bulan ini', $this->money((clone $query)->whereBetween('tanggal', $this->monthRange())->where('status', '!=', 'BATAL')->sum('total')), 'Total pembelian selain pembatalan'),
                ];
                $columns = ['invoice' => 'No. Invoice', 'tanggal' => 'Tanggal', 'supplier' => 'Supplier', 'jenis' => 'Jenis', 'total' => 'Total', 'status' => 'Status'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (Pembelian $purchase) => [
                    'invoice' => $purchase->no_invoice,
                    'tanggal' => $purchase->tanggal?->format('d M Y') ?? '—',
                    'supplier' => $purchase->supplier?->nama ?? '—',
                    'jenis' => str_replace('_', ' ', $purchase->jenis_pembelian),
                    'total' => $this->money($purchase->total),
                    'status' => str_replace('_', ' ', $purchase->status),
                ]);
                break;

            case 'service':
                $query = Service::query()->with('pelanggan:id,nama', 'teknisi:id,name')->latest('tanggal_masuk');
                if ($request->query('status_group') === 'closed') {
                    $query->whereIn('status', ['SELESAI', 'SUDAH_DIAMBIL']);
                } elseif (in_array($request->query('status'), ['DITERIMA', 'DIAGNOSA', 'MENUNGGU_PERSETUJUAN', 'MENUNGGU_SPAREPART', 'DALAM_PERBAIKAN', 'TESTING', 'SELESAI', 'SUDAH_DIAMBIL', 'BATAL'], true)) {
                    $query->where('status', $request->query('status'));
                }
                if ($search = trim((string) $request->query('q'))) {
                    $query->where(function (Builder $builder) use ($search) {
                        $builder->where('no_service', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%")
                            ->orWhere('merek', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhereHas('pelanggan', fn (Builder $customer) => $customer->where('nama', 'like', "%{$search}%"));
                    });
                }
                if ($user->role?->name === 'TEKNISI') {
                    $query->where('teknisi_id', $user->id);
                } elseif ($user->role?->name === 'PELANGGAN') {
                    $query->whereHas('pelanggan', fn (Builder $builder) => $builder->where('user_id', $user->id));
                }
                $active = (clone $query)->whereNotIn('status', ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL']);
                $stats = [
                    $this->stat('Service aktif', number_format($active->count()), 'Tidak termasuk service selesai atau batal'),
                    $this->stat('Total service', number_format((clone $query)->count()), 'Data yang sesuai dengan akses akun'),
                ];
                $columns = ['nomor' => 'No. Service', 'pelanggan' => 'Pelanggan', 'perangkat' => 'Perangkat', 'teknisi' => 'Teknisi', 'masuk' => 'Tanggal Masuk', 'status' => 'Status'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (Service $service) => [
                    'nomor' => $service->no_service,
                    'pelanggan' => $user->role?->name === 'PELANGGAN' ? 'Service Saya' : ($service->pelanggan?->nama ?? '—'),
                    'perangkat' => trim($service->merek.' '.$service->model) ?: $service->jenis_perangkat,
                    'teknisi' => $service->teknisi?->name ?? 'Belum ditugaskan',
                    'masuk' => $service->tanggal_masuk?->format('d M Y') ?? '—',
                    'status' => str_replace('_', ' ', $service->status),
                ]);
                break;

            case 'service-progress':
                $latestProgress = ServiceProgress::query()
                    ->whereColumn('service_id', 'service.id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
                $query = Service::query()
                    ->with(['teknisi:id,name'])
                    ->withCount('progress')
                    ->addSelect([
                        'latest_progress_percent' => (clone $latestProgress)->select('progress_percent')->limit(1),
                        'latest_progress_at' => (clone $latestProgress)->select('created_at')->limit(1),
                    ])
                    ->whereHas('progress')
                    ->when($user->role?->name === 'TEKNISI', fn (Builder $builder) => $builder->where('teknisi_id', $user->id))
                    ->when($user->role?->name === 'PELANGGAN', fn (Builder $builder) => $builder->whereHas(
                        'pelanggan',
                        fn (Builder $customer) => $customer->where('user_id', $user->id),
                    ))
                    ->latest('tanggal_masuk');
                $stats = [$this->stat('Service dengan progress', number_format((clone $query)->count()), 'Satu baris untuk setiap nomor service')];
                $columns = ['service' => 'No. Service', 'perangkat' => 'Perangkat', 'teknisi' => 'Teknisi', 'progress' => 'Progress', 'status' => 'Status', 'waktu' => 'Diperbarui'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (Service $service) => [
                    'id' => $service->id,
                    'service' => $service->no_service,
                    'perangkat' => trim(($service->merek ?? '').' '.($service->model ?? '')) ?: ($service->jenis_perangkat ?? '—'),
                    'teknisi' => $service->teknisi?->name ?? 'Belum ditugaskan',
                    'progress' => number_format((int) ($service->latest_progress_percent ?? 0)).'%',
                    'status' => str_replace('_', ' ', $service->status),
                    'waktu' => $service->latest_progress_at
                        ? Carbon::parse($service->latest_progress_at)->format('d M Y H:i')
                        : '—',
                ]);
                break;

            case 'income':
                $query = Pendapatan::query()->forProfitLoss()->latest('tanggal');
                $stats = [
                    $this->stat('Pendapatan bulan ini', $this->money(Pendapatan::query()->forProfitLoss()->whereBetween('tanggal', $this->monthRange())->sum('nominal')), 'Jumlah catatan pendapatan periode ini'),
                    $this->stat('Catatan bulan ini', number_format((clone $query)->whereBetween('tanggal', $this->monthRange())->count()), 'Transaksi pendapatan tercatat'),
                ];
                $columns = ['tanggal' => 'Tanggal', 'kode' => 'Kode', 'jenis' => 'Jenis', 'sumber' => 'Sumber', 'keterangan' => 'Keterangan', 'nominal' => 'Nominal'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (Pendapatan $income) => [
                    'tanggal' => $income->tanggal?->format('d M Y') ?? '—',
                    'kode' => $income->kode_pendapatan ?: '—',
                    'jenis' => str_replace('_', ' ', $income->jenis_pendapatan),
                    'sumber' => $income->sumber ?: '—',
                    'keterangan' => $income->keterangan ?: '—',
                    'nominal' => $this->money($income->nominal),
                ]);
                break;

            case 'expenses':
                $query = Pengeluaran::query()->whereNotIn('kategori_beban', Pengeluaran::CASH_BALANCE_CATEGORIES)->latest('tanggal');
                $stats = [
                    $this->stat('Pengeluaran bulan ini', $this->money(Pengeluaran::query()->forProfitLoss()->whereBetween('tanggal', $this->monthRange())->sum('nominal')), 'Jumlah catatan pengeluaran periode ini'),
                    $this->stat('Catatan bulan ini', (string) (clone $query)->whereBetween('tanggal', $this->monthRange())->count(), 'Transaksi pengeluaran tercatat'),
                ];
                $columns = ['tanggal' => 'Tanggal', 'kode' => 'Kode', 'kategori' => 'Kategori', 'metode' => 'Metode Bayar', 'keterangan' => 'Keterangan', 'nominal' => 'Nominal'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (Pengeluaran $expense) => [
                    'tanggal' => $expense->tanggal?->format('d M Y') ?? '—',
                    'kode' => $expense->kode_beban ?: '—',
                    'kategori' => str_replace('_', ' ', $expense->kategori_beban),
                    'metode' => $expense->metode_bayar ?? '—',
                    'keterangan' => $expense->keterangan ?: '—',
                    'nominal' => $this->money($expense->nominal),
                ]);
                break;

            case 'cash':
                $query = KasMutasi::query()->latest('tanggal');
                $balance = (clone $query)->orderByDesc('tanggal')->orderByDesc('id')->value('saldo') ?? 0;
                $stats = [
                    $this->stat('Saldo terakhir', $this->money($balance), 'Saldo dari mutasi kas terakhir'),
                    $this->stat('Mutasi bulan ini', number_format((clone $query)->whereBetween('tanggal', $this->monthRange())->count()), 'Pergerakan kas yang tercatat'),
                ];
                $columns = ['tanggal' => 'Tanggal', 'jenis' => 'Jenis', 'keterangan' => 'Keterangan', 'debit' => 'Masuk', 'kredit' => 'Keluar', 'saldo' => 'Saldo'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (KasMutasi $mutation) => [
                    'tanggal' => $mutation->tanggal?->format('d M Y') ?? '—',
                    'jenis' => str_replace('_', ' ', $mutation->jenis),
                    'keterangan' => $mutation->keterangan ?: '—',
                    'debit' => $this->money($mutation->debit),
                    'kredit' => $this->money($mutation->kredit),
                    'saldo' => $this->money($mutation->saldo),
                ]);
                break;

            case 'reports':
                $range = $this->monthRange();
                $type = $request->query('type', 'other');
                $stats = match ($type) {
                    'sales' => [
                        $this->stat('Nilai penjualan', $this->money(Penjualan::query()->whereBetween('tanggal', $range)->whereNotIn('status', ['BATAL', 'DRAFT'])->sum('total')), 'Transaksi penjualan bulan berjalan'),
                        $this->stat('Jumlah transaksi', number_format(Penjualan::query()->whereBetween('tanggal', $range)->whereNotIn('status', ['BATAL', 'DRAFT'])->count()), 'Tidak termasuk transaksi batal atau draft'),
                    ],
                    'service' => [
                        $this->stat('Service masuk', number_format(Service::query()->whereBetween('tanggal_masuk', $range)->count()), 'Perangkat diterima bulan berjalan'),
                        $this->stat('Service aktif', number_format(Service::query()->whereBetween('tanggal_masuk', $range)->whereNotIn('status', ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL'])->count()), 'Belum selesai atau dibatalkan'),
                    ],
                    'cash' => [
                        $this->stat('Kas masuk', $this->money(KasMutasi::query()->whereBetween('tanggal', $range)->sum('debit')), 'Mutasi debit bulan berjalan'),
                        $this->stat('Kas keluar', $this->money(KasMutasi::query()->whereBetween('tanggal', $range)->sum('kredit')), 'Mutasi kredit bulan berjalan'),
                    ],
                    'stock' => [
                        $this->stat('Produk aktif', number_format(Produk::query()->where('status', 'AKTIF')->count()), 'SKU aktif di inventaris'),
                        $this->stat('Stok menipis', number_format(Produk::query()->where('status', 'AKTIF')->whereColumn('stok', '<=', 'stok_minimum')->count()), 'Stok sama dengan atau di bawah minimum'),
                    ],
                    default => [
                        $this->stat('Pendapatan', $this->money(Pendapatan::query()->forProfitLoss()->whereBetween('tanggal', $range)->sum('nominal')), 'Periode bulan berjalan'),
                        $this->stat('Pengeluaran', $this->money(Pengeluaran::query()->forProfitLoss()->whereBetween('tanggal', $range)->sum('nominal')), 'Periode bulan berjalan'),
                        $this->stat('Pembelian', $this->money(Pembelian::query()->whereBetween('tanggal', $range)->where('status', '!=', 'BATAL')->sum('total')), 'Periode bulan berjalan'),
                    ],
                };
                $notice = 'Ringkasan '.match ($type) {
                    'sales' => 'penjualan', 'service' => 'service', 'cash' => 'kas', 'stock' => 'stok', default => 'lainnya'
                }.' untuk bulan berjalan.';
                break;

            case 'profit-loss':
                $range = $this->monthRange();
                $grossProfit = PenjualanDetail::query()
                    ->whereHas('penjualan', fn (Builder $builder) => $builder->whereBetween('tanggal', $range)->whereNotIn('status', ['BATAL', 'DRAFT']))
                    ->sum('laba');
                $expenses = Pengeluaran::query()->forProfitLoss()->whereBetween('tanggal', $range)->sum('nominal');
                $stats = [
                    $this->stat('Laba kotor penjualan', $this->money($grossProfit), 'Akumulasi laba item transaksi penjualan'),
                    $this->stat('Pengeluaran', $this->money($expenses), 'Periode bulan berjalan'),
                    $this->stat('Laba bersih sementara', $this->money($grossProfit - $expenses), 'Laba kotor dikurangi pengeluaran tercatat'),
                ];
                $notice = 'Ringkasan sementara ini memakai laba item penjualan yang tersimpan dan pengeluaran tercatat; pembelian barang tidak dikurangkan lagi karena HPP sudah masuk ke laba item.';
                break;

            case 'users':
                $query = User::query()->with('role:id,name')->orderBy('name');
                $stats = [
                    $this->stat('Total pengguna', number_format(User::query()->count()), 'Semua akun aplikasi'),
                    $this->stat('Akun aktif', number_format(User::query()->where('status', 'AKTIF')->count()), 'Akun dengan status aktif'),
                ];
                $columns = ['nama' => 'Nama', 'email' => 'Email', 'role' => 'Role', 'status' => 'Status', 'login' => 'Login Terakhir'];
                $rows = $query->paginate(15)->withQueryString()->through(fn (User $account) => [
                    'nama' => $account->name,
                    'email' => $account->email,
                    'role' => $account->role?->name ?? 'Belum ditetapkan',
                    'status' => $account->status,
                    'login' => $account->last_login_at?->format('d M Y H:i') ?? 'Belum pernah',
                ]);
                break;

            case 'ai':
                $notice = 'Halaman AI sudah tersedia. Sambungkan provider AI dan konfigurasi kredensialnya untuk mengaktifkan percakapan.';
                break;
        }

        return view('modules.show', compact('module', 'stats', 'columns', 'rows', 'notice') + [
            'title' => $titles[$module],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function monthRange(): array
    {
        return [now()->startOfMonth(), now()->endOfMonth()];
    }

    /** @return array{label: string, value: string, detail: string} */
    private function stat(string $label, string $value, string $detail): array
    {
        return compact('label', 'value', 'detail');
    }

    private function money(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
