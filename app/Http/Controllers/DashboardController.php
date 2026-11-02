<?php

namespace App\Http\Controllers;

use App\Models\KasirShift;
use App\Models\KasMutasi;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Pendapatan;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Service;
use App\Models\ServiceProgress;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    private const SERVICE_STATUSES = [
        'DITERIMA' => ['label' => 'Diterima', 'color' => 'blue'],
        'DIAGNOSA' => ['label' => 'Diagnosa', 'color' => 'amber'],
        'MENUNGGU_PERSETUJUAN' => ['label' => 'Menunggu Persetujuan', 'color' => 'orange'],
        'MENUNGGU_SPAREPART' => ['label' => 'Menunggu Sparepart', 'color' => 'violet'],
        'DALAM_PERBAIKAN' => ['label' => 'Dalam Perbaikan', 'color' => 'sky'],
        'TESTING' => ['label' => 'Testing', 'color' => 'violet'],
        'SELESAI' => ['label' => 'Selesai', 'color' => 'emerald'],
        'SUDAH_DIAMBIL' => ['label' => 'Sudah Diambil', 'color' => 'slate'],
    ];

    public function __invoke(Request $request): View
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);
        $date = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();
        $previousDate = $date->copy()->subDay();
        $user = $request->user();
        $canSeeSales = $user->hasPermission('view_sales');
        $canSeePurchases = $user->hasPermission('view_purchases');
        $canSeeServices = $user->hasPermission('view_service');
        $canSeeStock = $user->hasPermission('view_stock');
        $canSeeCash = $user->hasPermission('view_cash');
        $canSeeIncome = $user->hasPermission('view_income');
        $metrics = [];

        if ($user->role?->name === 'PELANGGAN') {
            $customerServices = $this->visibleServices($user);
            $recentCustomerServices = (clone $customerServices)->with('progress.updater:id,name')
                ->latest('tanggal_masuk')->limit(6)->get();
            $serviceCounts = collect(self::SERVICE_STATUSES)->map(fn (array $details, string $status) => [
                'status' => $status,
                'label' => $details['label'],
                'count' => (clone $customerServices)->where('status', $status)->count(),
            ])->values();

            return view('service.customer-dashboard', [
                'user' => $user,
                'recentServices' => $recentCustomerServices,
                'serviceCounts' => $serviceCounts,
                'activeServices' => (clone $customerServices)->whereNotIn('status', ['SELESAI', 'SUDAH_DIAMBIL', 'BATAL'])->count(),
                'totalServices' => (clone $customerServices)->count(),
            ]);
        }

        if ($canSeeSales) {
            $todaySales = Penjualan::query()->whereDate('tanggal', $date)->whereNotIn('status', ['BATAL', 'DRAFT']);
            $previousSales = Penjualan::query()->whereDate('tanggal', $previousDate)->whereNotIn('status', ['BATAL', 'DRAFT']);
            $metrics[] = $this->metric('Penjualan Hari Ini', $this->money((clone $todaySales)->sum('total')), number_format((clone $todaySales)->count(), 0, ',', '.').' transaksi hari ini', $this->comparison((float) (clone $todaySales)->sum('total'), (float) (clone $previousSales)->sum('total')), 'cart', 'emerald');
        }

        $serviceQuery = $this->visibleServices($user);
        if ($canSeeServices) {
            $todayServices = (clone $serviceQuery)->whereDate('tanggal_masuk', $date);
            $previousServices = (clone $serviceQuery)->whereDate('tanggal_masuk', $previousDate);
            if ($user->role?->name === 'TEKNISI') {
                $metrics[] = $this->metric('Service Masuk Hari Ini', number_format((clone $todayServices)->count(), 0, ',', '.'), 'Service yang ditugaskan kepada Anda', $this->comparison((float) (clone $todayServices)->count(), (float) (clone $previousServices)->count()), 'service', 'blue');
            } else {
                $metrics[] = $this->metric('Service Masuk Hari Ini', $this->money((clone $todayServices)->sum('total')), number_format((clone $todayServices)->count(), 0, ',', '.').' service diterima', $this->comparison((float) (clone $todayServices)->sum('total'), (float) (clone $previousServices)->sum('total')), 'service', 'blue');
            }
        }

        if ($canSeePurchases) {
            $todayPurchases = Pembelian::query()->whereDate('tanggal', $date)->where('status', '!=', 'BATAL');
            $previousPurchases = Pembelian::query()->whereDate('tanggal', $previousDate)->where('status', '!=', 'BATAL');
            $metrics[] = $this->metric('Pembelian Hari Ini', $this->money((clone $todayPurchases)->sum('total')), number_format((clone $todayPurchases)->count(), 0, ',', '.').' pesanan', $this->comparison((float) (clone $todayPurchases)->sum('total'), (float) (clone $previousPurchases)->sum('total')), 'purchase', 'violet');
        }

        if ($canSeeCash) {
            $todayCashIn = KasMutasi::query()->whereDate('tanggal', $date);
            $previousCashIn = KasMutasi::query()->whereDate('tanggal', $previousDate);
            $metrics[] = $this->metric('Kas Masuk Hari Ini', $this->money((clone $todayCashIn)->sum('debit')), number_format((clone $todayCashIn)->count(), 0, ',', '.').' mutasi kas', $this->comparison((float) (clone $todayCashIn)->sum('debit'), (float) (clone $previousCashIn)->sum('debit')), 'cash', 'amber');
        }

        if ($canSeeIncome && ! $canSeePurchases) {
            $todayIncome = Pendapatan::query()->forProfitLoss()->whereDate('tanggal', $date);
            $previousIncome = Pendapatan::query()->forProfitLoss()->whereDate('tanggal', $previousDate);
            $metrics[] = $this->metric('Pendapatan Hari Ini', $this->money((clone $todayIncome)->sum('nominal')), number_format((clone $todayIncome)->count(), 0, ',', '.').' penerimaan', $this->comparison((float) (clone $todayIncome)->sum('nominal'), (float) (clone $previousIncome)->sum('nominal')), 'deposit', 'cyan');
        }

        $salesChart = $canSeeSales ? $this->salesChart($date) : collect();
        $paymentMethods = $canSeeSales ? $this->paymentMethods($date) : collect();
        $paymentTotal = $paymentMethods->sum('amount');
        $paymentGradient = $this->paymentGradient($paymentMethods, $paymentTotal);
        $serviceStatuses = $canSeeServices ? $this->serviceStatuses($serviceQuery) : collect();
        $lowStockProducts = $canSeeStock
            ? Produk::query()->where('status', 'AKTIF')->whereColumn('stok', '<=', 'stok_minimum')->orderBy('stok')->limit(5)->get(['id', 'kode_produk', 'nama_produk', 'merek', 'tipe', 'jenis_produk', 'atribut', 'stok', 'stok_minimum'])
            : collect();
        $recentSales = $canSeeSales ? Penjualan::query()->with('pelanggan:id,nama')->whereNotIn('status', ['BATAL', 'DRAFT'])->latest('id')->limit(5)->get() : collect();
        $recentServices = $canSeeServices ? (clone $serviceQuery)->with('pelanggan:id,nama')->latest('id')->limit(5)->get() : collect();
        $activities = $this->recentActivities($request, $date, $canSeeSales, $canSeeServices, $canSeePurchases, $canSeeIncome);
        $currentShift = $canSeeCash
            ? KasirShift::query()->where('user_id', $user->id)->where('status', 'OPEN')->latest('opened_at')->first()
            : null;

        return view('dashboard', [
            'date' => $date,
            'metrics' => $metrics,
            'salesChart' => $salesChart,
            'chartTotal' => $salesChart->sum('amount'),
            'chartMax' => max(1, (float) $salesChart->max('amount')),
            'paymentMethods' => $paymentMethods,
            'paymentTotal' => $paymentTotal,
            'paymentGradient' => $paymentGradient,
            'serviceStatuses' => $serviceStatuses,
            'lowStockProducts' => $lowStockProducts,
            'recentSales' => $recentSales,
            'recentServices' => $recentServices,
            'activities' => $activities,
            'currentShift' => $currentShift,
            'isCashier' => $user->role?->name === 'KASIR',
            'user' => $user,
            'canSeeSales' => $canSeeSales,
            'canSeePurchases' => $canSeePurchases,
            'canSeeServices' => $canSeeServices,
            'canSeeStock' => $canSeeStock,
            'canSeeCash' => $canSeeCash,
        ]);
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

    private function salesChart(Carbon $date): Collection
    {
        $start = $date->copy()->subDays(6);
        $totals = Penjualan::query()->whereBetween('tanggal', [$start->toDateString(), $date->toDateString()])
            ->whereNotIn('status', ['BATAL', 'DRAFT'])->selectRaw('tanggal, SUM(total) as total, COUNT(*) as count')->groupBy('tanggal')->get()->keyBy(fn ($row) => Carbon::parse($row->tanggal)->toDateString());

        return collect(range(0, 6))->map(function (int $offset) use ($start, $totals): array {
            $day = $start->copy()->addDays($offset);
            $row = $totals->get($day->toDateString());

            return [
                'date' => $day,
                'label' => $day->locale('id')->translatedFormat('d M'),
                'weekday' => $day->locale('id')->translatedFormat('D'),
                'amount' => (float) ($row?->total ?? 0),
                'count' => (int) ($row?->count ?? 0),
            ];
        });
    }

    private function paymentMethods(Carbon $date): Collection
    {
        $amounts = Pembayaran::query()->whereDate('tanggal', $date)->where('status', 'LUNAS')
            ->selectRaw('metode, SUM(nominal) as total')->groupBy('metode')->pluck('total', 'metode');
        $receivable = Pembayaran::query()->whereDate('created_at', $date)->where('metode', 'PIUTANG')->where('status', 'PENDING')->sum('nominal');
        $methods = collect([
            ['label' => 'Tunai', 'color' => '#1677f8', 'amount' => (float) ($amounts['CASH'] ?? 0)],
            ['label' => 'Transfer', 'color' => '#39c98a', 'amount' => (float) ($amounts['TRANSFER'] ?? 0)],
            ['label' => 'QRIS', 'color' => '#ad79ff', 'amount' => (float) ($amounts['QRIS'] ?? 0)],
            ['label' => 'Debit/Kredit', 'color' => '#f4b400', 'amount' => (float) ($amounts['DEBIT'] ?? 0) + (float) ($amounts['KREDIT'] ?? 0)],
            ['label' => 'Piutang', 'color' => '#f05268', 'amount' => (float) $receivable],
        ]);

        return $methods->map(function (array $method) use ($methods): array {
            $total = $methods->sum('amount');
            $method['percent'] = $total > 0 ? round($method['amount'] / $total * 100, 1) : 0;

            return $method;
        });
    }

    private function paymentGradient(Collection $methods, float $total): string
    {
        if ($total <= 0) {
            return 'conic-gradient(#e2e8f0 0deg 360deg)';
        }

        $cursor = 0.0;
        $parts = [];
        foreach ($methods as $method) {
            if ($method['amount'] <= 0) {
                continue;
            }
            $end = min(360, $cursor + $method['amount'] / $total * 360);
            $parts[] = $method['color'].' '.$cursor.'deg '.$end.'deg';
            $cursor = $end;
        }

        return 'conic-gradient('.implode(', ', $parts).')';
    }

    private function serviceStatuses(Builder $serviceQuery): Collection
    {
        return collect(self::SERVICE_STATUSES)->map(function (array $details, string $status) use ($serviceQuery): array {
            return $details + ['status' => $status, 'count' => (clone $serviceQuery)->where('status', $status)->count()];
        });
    }

    private function recentActivities(Request $request, Carbon $date, bool $canSeeSales, bool $canSeeServices, bool $canSeePurchases, bool $canSeeIncome): Collection
    {
        $items = collect();
        if ($canSeeSales) {
            $sales = Penjualan::query()->with('user:id,name')->whereDate('created_at', $date)->latest('created_at')->limit(8)->get();
            foreach ($sales as $sale) {
                if ($request->user()->role?->name === 'KASIR' && $sale->user_id !== $request->user()->id) {
                    continue;
                }
                $items->push(['at' => $sale->created_at, 'user' => $sale->user?->name ?? '—', 'activity' => 'Membuat penjualan', 'detail' => $sale->no_invoice.' · '.$this->money($sale->total)]);
            }
        }
        if ($canSeeServices) {
            $services = (clone $this->visibleServices($request->user()))->whereDate('created_at', $date)->latest('created_at')->limit(8)->get();
            foreach ($services as $service) {
                $items->push(['at' => $service->created_at, 'user' => '—', 'activity' => 'Menerima service', 'detail' => $service->no_service.' · '.($service->merek ?: $service->jenis_perangkat)]);
            }
            $progress = ServiceProgress::query()->with(['service:id,no_service,status,teknisi_id', 'updater:id,name'])
                ->whereHas('service', function (Builder $query) use ($request): void {
                    if ($request->user()->role?->name === 'TEKNISI') {
                        $query->where('teknisi_id', $request->user()->id);
                    } elseif ($request->user()->role?->name === 'PELANGGAN') {
                        $query->whereHas('pelanggan', fn (Builder $customer) => $customer->where('user_id', $request->user()->id));
                    }
                })
                ->whereDate('created_at', $date)->latest('created_at')->limit(8)->get();
            foreach ($progress as $entry) {
                $items->push(['at' => $entry->created_at, 'user' => $entry->updater?->name ?? '—', 'activity' => 'Update status service', 'detail' => ($entry->service?->no_service ?? 'Service').' · '.str_replace('_', ' ', $entry->status)]);
            }
        }
        if ($canSeePurchases) {
            foreach (Pembelian::query()->with('user:id,name')->whereDate('created_at', $date)->latest('created_at')->limit(5)->get() as $purchase) {
                $items->push(['at' => $purchase->created_at, 'user' => $purchase->user?->name ?? '—', 'activity' => 'Mencatat pembelian', 'detail' => $purchase->no_invoice.' · '.$this->money($purchase->total)]);
            }
        }
        if ($canSeeIncome) {
            foreach (Pendapatan::query()->with('user:id,name')->whereNull('referensi_type')->whereDate('created_at', $date)->latest('created_at')->limit(5)->get() as $income) {
                $items->push(['at' => $income->created_at, 'user' => $income->user?->name ?? '—', 'activity' => 'Mencatat pendapatan', 'detail' => ($income->kode_pendapatan ?: $income->sumber).' · '.$this->money($income->nominal)]);
            }
        }

        return $items->sortByDesc(fn (array $item) => $item['at']?->timestamp ?? 0)->take(8)->values();
    }

    private function metric(string $label, string $value, string $detail, array $comparison, string $icon, string $tone): array
    {
        return compact('label', 'value', 'detail', 'comparison', 'icon', 'tone');
    }

    private function comparison(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return ['label' => $current > 0 ? 'Baru hari ini' : '0%', 'up' => $current > 0];
        }

        $change = (($current - $previous) / $previous) * 100;

        return ['label' => ($change > 0 ? '+' : '').number_format($change, 0, ',', '.').'%', 'up' => $change >= 0];
    }

    private function money(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
