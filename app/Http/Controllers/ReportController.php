<?php

namespace App\Http\Controllers;

use App\Models\Pendapatan;
use App\Models\Pengeluaran;
use App\Support\CsvSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const HPP_REPORTS = ['detail-penjualan', 'penjualan-produk', 'penjualan-sales', 'stok', 'mutasi-stok'];

    private const REPORTS = [
        'penjualan' => ['Penjualan', 'Daftar invoice, status, nilai, dan penerimaan penjualan.', 'Penjualan'],
        'detail-penjualan' => ['Detail Penjualan', 'Rincian item per invoice, HPP, biaya unit, dan gross profit.', 'Penjualan'],
        'penjualan-produk' => ['Penjualan per Produk', 'Kuantitas, omzet, HPP, dan margin berdasarkan produk.', 'Penjualan'],
        'penjualan-sales' => ['Penjualan per Sales', 'Performa transaksi yang dibuat setiap sales/kasir.', 'Penjualan'],
        'penjualan-laptop' => ['Penjualan Laptop', 'Invoice laptop beserta nilai jual dan penerimaan.', 'Penjualan'],
        'pembelian' => ['Pembelian', 'Daftar invoice pembelian dan status pembayarannya.', 'Pembelian'],
        'pembelian-supplier' => ['Pembelian per Supplier', 'Nilai invoice pembelian per supplier.', 'Pembelian'],
        'service' => ['Service', 'Daftar penerimaan perangkat dan posisi tagihannya.', 'Service'],
        'service-teknisi' => ['Service per Teknisi', 'Jumlah pekerjaan dan nilai service per teknisi.', 'Service'],
        'service-status' => ['Service per Status', 'Jumlah dan nilai service pada setiap status.', 'Service'],
        'stok' => ['Inventori & Nilai Stok', 'Daftar seluruh produk, jumlah stok, dan nilai persediaan berdasarkan stok × HPP.', 'Persediaan'],
        'mutasi-stok' => ['Mutasi Stok', 'Pergerakan stok masuk dan keluar berdasarkan tanggal.', 'Persediaan'],
        'pendapatan' => ['Pendapatan', 'Catatan penerimaan per jenis dan referensi.', 'Keuangan'],
        'pengeluaran' => ['Pengeluaran', 'Beban transaksi dan operasional yang tercatat.', 'Keuangan'],
        'kas' => ['Kas', 'Mutasi tunai masuk/keluar dan saldo berjalan.', 'Keuangan'],
        'piutang' => ['Piutang', 'Saldo piutang penjualan/service yang belum lunas.', 'Keuangan'],
        'pembayaran' => ['Pembayaran', 'Riwayat pembayaran tunai dan non-tunai.', 'Keuangan'],
        'laba-kotor' => ['Laba Kotor', 'Pendapatan penjualan/service dikurangi HPP barang yang terjual/terpakai.', 'Profit & Loss'],
        'laba-bersih' => ['Laba Bersih', 'Laba kotor ditambah pendapatan non-penjualan dikurangi beban.', 'Profit & Loss'],
        'profit-loss' => ['Profit & Loss', 'Laporan laba rugi yang memisahkan omzet, HPP, pendapatan lain, dan beban.', 'Profit & Loss'],
    ];

    public function index(Request $request): View
    {
        $reports = self::REPORTS;
        if (! $request->user()->hasPermission('view_profit_loss')) {
            unset($reports['laba-kotor'], $reports['laba-bersih'], $reports['profit-loss']);
        }
        if (! $request->user()->hasPermission('view_hpp')) {
            foreach (self::HPP_REPORTS as $report) {
                unset($reports[$report]);
            }
        }

        $type = strtolower((string) $request->query('type', ''));
        $categoryByType = [
            'sales' => ['Penjualan', 'Pembelian'],
            'service' => ['Service'],
            'kas' => ['Keuangan'],
            'cash' => ['Keuangan'],
            'stock' => ['Persediaan'],
            'persediaan' => ['Persediaan'],
            'other' => ['Profit & Loss'],
        ];
        if ($type !== '' && isset($categoryByType[$type])) {
            $allowed = $categoryByType[$type];
            $reports = array_filter($reports, fn (array $definition) => in_array($definition[2], $allowed, true));
        }

        return view('reports.index', compact('reports'));
    }

    public function show(Request $request, string $report): View|StreamedResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        if (in_array($report, ['laba-kotor', 'laba-bersih', 'profit-loss'], true)) {
            abort_unless($request->user()->hasPermission('view_profit_loss'), 403);
        } else {
            abort_unless($request->user()->hasPermission('view_reports'), 403);
        }
        if (in_array($report, self::HPP_REPORTS, true)) {
            abort_unless($request->user()->hasPermission('view_hpp'), 403);
        }
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', Rule::in([15, 30, 50])],
            'export' => ['nullable', Rule::in(['csv'])],
            'print' => ['nullable', Rule::in(['1'])],
        ]);
        [$title, $description] = self::REPORTS[$report];
        $dataset = in_array($report, ['laba-kotor', 'laba-bersih', 'profit-loss'], true)
            ? $this->profitLossDataset($filters, $report)
            : $this->reportDataset($report, $filters);
        $columns = $dataset['columns'];
        $rowsSource = in_array($report, ['laba-kotor', 'laba-bersih', 'profit-loss'], true)
            ? $dataset['rows']
            : $dataset['query'];
        $isSnapshot = $report === 'stok';
        $filters['date_from'] = $filters['date_from'] ?? '';
        $filters['date_to'] = $filters['date_to'] ?? '';
        $printMode = ($filters['print'] ?? null) === '1';

        if (($filters['export'] ?? null) === 'csv') {
            $rows = $rowsSource instanceof Builder ? $rowsSource->get() : collect($rowsSource);

            return response()->streamDownload(function () use ($rows, $columns): void {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_values($columns));
                foreach ($rows as $row) {
                    $line = [];
                    foreach (array_keys($columns) as $key) {
                        $line[] = CsvSanitizer::escapeFormula(data_get($row, $key));
                    }
                    fputcsv($output, $line);
                }
                fclose($output);
            }, 'laporan-'.$report.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($rowsSource instanceof Builder) {
            $totalRows = (clone $rowsSource)->count();
            if ($printMode) {
                $rows = $rowsSource->get();
            } else {
                $rows = $rowsSource->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
            }
        } else {
            $totalRows = count($rowsSource);
            if ($printMode) {
                $rows = collect($rowsSource);
            } else {
                $page = LengthAwarePaginator::resolveCurrentPage();
                $perPage = (int) ($filters['per_page'] ?? 15);
                $rows = new LengthAwarePaginator(
                    collect($rowsSource)->forPage($page, $perPage)->values(), $totalRows, $perPage, $page,
                    ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()],
                );
            }
        }

        $stats = $dataset['stats'] ?? [['label' => 'Baris laporan', 'value' => number_format($totalRows, 0, ',', '.'), 'detail' => 'Sesuai filter yang dipilih']];

        return view('reports.show', compact('report', 'title', 'description', 'filters', 'columns', 'rows', 'stats', 'printMode', 'isSnapshot'));
    }

    public function profitLoss(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()->hasPermission('view_profit_loss'), 403);

        return $this->show($request, 'profit-loss');
    }

    private function reportDataset(string $report, array $filters): array
    {
        $date = fn (Builder $query, string $column) => $this->applyDate($query, $column, $filters);
        $search = fn (Builder $query, array $columns) => $this->applySearch($query, $columns, $filters['q'] ?? null);

        switch ($report) {
            case 'penjualan':
            case 'penjualan-laptop':
                $query = DB::table('penjualan as s')->leftJoin('pelanggan as c', 'c.id', '=', 's.pelanggan_id')->leftJoin('users as u', 'u.id', '=', 's.user_id')
                    ->select('s.id', 's.tanggal', 's.no_invoice', 'c.nama as pelanggan', 's.jenis_penjualan as jenis', 's.total', 's.dibayar', 's.status', 'u.name as sales')
                    ->orderByDesc('s.tanggal')->orderByDesc('s.id');
                $date($query, 's.tanggal');
                if ($report === 'penjualan-laptop') {
                    $query->where('s.jenis_penjualan', 'LAPTOP');
                }
                if (($filters['q'] ?? '') !== '') {
                    $search($query, ['s.no_invoice', 'c.nama', 'u.name', 's.status']);
                }

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'no_invoice' => 'No. Invoice', 'pelanggan' => 'Pelanggan', 'jenis' => 'Jenis', 'sales' => 'Sales', 'total' => 'Total', 'dibayar' => 'Dibayar', 'status' => 'Status']];

            case 'detail-penjualan':
                $query = DB::table('penjualan_detail as d')->join('penjualan as s', 's.id', '=', 'd.penjualan_id')->join('produk as p', 'p.id', '=', 'd.produk_id')->leftJoin('laptop as l', 'l.id', '=', 'd.laptop_id')
                    ->whereNotIn('s.status', ['DRAFT', 'BATAL'])
                    ->select('s.tanggal', 's.no_invoice', 'p.kode_produk', 'p.nama_produk', 'l.serial_number', 'd.qty', 'd.harga', 'd.diskon', 'd.subtotal', 'd.hpp', DB::raw('COALESCE(l.biaya_tambahan, 0) as biaya_unit'), DB::raw('(d.subtotal - (d.hpp + COALESCE(l.biaya_tambahan, 0)) * d.qty) as gross_profit'))
                    ->orderByDesc('s.tanggal')->orderByDesc('s.id');
                $date($query, 's.tanggal');
                $search($query, ['s.no_invoice', 'p.kode_produk', 'p.nama_produk', 'l.serial_number']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'no_invoice' => 'Invoice', 'kode_produk' => 'Kode', 'nama_produk' => 'Produk', 'serial_number' => 'Serial', 'qty' => 'Qty', 'harga' => 'Harga Jual', 'diskon' => 'Diskon', 'subtotal' => 'Pendapatan', 'hpp' => 'HPP/unit', 'biaya_unit' => 'Biaya Tambahan/unit', 'gross_profit' => 'Laba Kotor']];

            case 'penjualan-produk':
                $query = DB::table('penjualan_detail as d')->join('penjualan as s', 's.id', '=', 'd.penjualan_id')->join('produk as p', 'p.id', '=', 'd.produk_id')->leftJoin('laptop as l', 'l.id', '=', 'd.laptop_id')
                    ->whereNotIn('s.status', ['DRAFT', 'BATAL'])->select('p.kode_produk', 'p.nama_produk', DB::raw('SUM(d.qty) as qty'), DB::raw('COUNT(DISTINCT s.id) as transaksi'), DB::raw('SUM(d.subtotal) as revenue'), DB::raw('SUM((d.hpp + COALESCE(l.biaya_tambahan,0)) * d.qty) as cogs'), DB::raw('SUM(d.subtotal - (d.hpp + COALESCE(l.biaya_tambahan,0)) * d.qty) as gross_profit'))
                    ->groupBy('p.id', 'p.kode_produk', 'p.nama_produk')->orderByDesc('revenue');
                $date($query, 's.tanggal');
                $search($query, ['p.kode_produk', 'p.nama_produk']);

                return ['query' => $query, 'columns' => ['kode_produk' => 'Kode', 'nama_produk' => 'Produk', 'qty' => 'Qty Terjual', 'transaksi' => 'Transaksi', 'revenue' => 'Pendapatan', 'cogs' => 'HPP + Biaya Unit', 'gross_profit' => 'Laba Kotor']];

            case 'penjualan-sales':
                $marginBySale = DB::table('penjualan_detail as d')->join('penjualan as ms', 'ms.id', '=', 'd.penjualan_id')->leftJoin('laptop as ml', 'ml.id', '=', 'd.laptop_id')
                    ->whereNotIn('ms.status', ['DRAFT', 'BATAL'])->select('ms.id as sale_id', DB::raw('SUM(d.subtotal - (d.hpp + COALESCE(ml.biaya_tambahan,0)) * d.qty) as gross_profit'))->groupBy('ms.id');
                $query = DB::table('penjualan as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')->leftJoinSub($marginBySale, 'm', fn ($join) => $join->on('m.sale_id', '=', 's.id'))
                    ->whereNotIn('s.status', ['DRAFT', 'BATAL'])
                    ->select('u.name as sales', DB::raw('COUNT(s.id) as transaksi'), DB::raw('SUM(s.total) as total'), DB::raw('SUM(s.dibayar) as dibayar'), DB::raw('SUM(COALESCE(m.gross_profit,0)) as gross_profit'))
                    ->groupBy('s.user_id', 'u.name')->orderByDesc('total');
                $date($query, 's.tanggal');
                $search($query, ['u.name']);

                return ['query' => $query, 'columns' => ['sales' => 'Sales', 'transaksi' => 'Transaksi', 'total' => 'Total Penjualan', 'dibayar' => 'Dibayar', 'gross_profit' => 'Laba Kotor']];

            case 'pembelian':
                $query = DB::table('pembelian as b')->leftJoin('supplier as s', 's.id', '=', 'b.supplier_id')->leftJoin('users as u', 'u.id', '=', 'b.user_id')
                    ->select('b.tanggal', 'b.no_invoice', 's.nama as supplier', 'b.jenis_pembelian as jenis', 'b.total', 'b.dibayar', 'b.status', 'u.name as user')->orderByDesc('b.tanggal')->orderByDesc('b.id');
                $date($query, 'b.tanggal');
                $search($query, ['b.no_invoice', 's.nama', 'u.name', 'b.status']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'no_invoice' => 'No. Invoice', 'supplier' => 'Supplier', 'jenis' => 'Jenis', 'total' => 'Total', 'dibayar' => 'Dibayar', 'status' => 'Status', 'user' => 'Petugas']];

            case 'pembelian-supplier':
                $query = DB::table('pembelian as b')->leftJoin('supplier as s', 's.id', '=', 'b.supplier_id')->where('b.status', '!=', 'BATAL')
                    ->select(DB::raw("COALESCE(s.nama, 'Tanpa Supplier') as supplier"), DB::raw('COUNT(b.id) as transaksi'), DB::raw('SUM(b.subtotal) as subtotal'), DB::raw('SUM(b.diskon) as diskon'), DB::raw('SUM(b.ongkir) as ongkir'), DB::raw('SUM(b.total) as total'))->groupBy('b.supplier_id', 's.nama')->orderByDesc('total');
                $date($query, 'b.tanggal');
                $search($query, ['s.nama']);

                return ['query' => $query, 'columns' => ['supplier' => 'Supplier', 'transaksi' => 'Invoice', 'subtotal' => 'Subtotal', 'diskon' => 'Diskon', 'ongkir' => 'Ongkir', 'total' => 'Total Pembelian']];

            case 'service':
                $query = DB::table('service as s')->leftJoin('pelanggan as c', 'c.id', '=', 's.pelanggan_id')->leftJoin('users as u', 'u.id', '=', 's.teknisi_id')
                    ->select('s.tanggal_masuk', 's.tanggal_selesai', 's.no_service', 'c.nama as pelanggan', 's.jenis_perangkat', 's.merek', 's.model', 's.serial_number', 'u.name as teknisi', 's.status', 's.total', 's.dibayar', DB::raw('(s.total-s.dibayar) as sisa'))
                    ->orderByDesc('s.tanggal_masuk')->orderByDesc('s.id');
                $date($query, 's.tanggal_masuk');
                $search($query, ['s.no_service', 'c.nama', 's.merek', 's.model', 's.serial_number', 'u.name', 's.status']);

                return ['query' => $query, 'columns' => ['tanggal_masuk' => 'Masuk', 'tanggal_selesai' => 'Selesai', 'no_service' => 'No. Service', 'pelanggan' => 'Pelanggan', 'perangkat' => 'Jenis', 'merek' => 'Merek', 'model' => 'Model', 'serial_number' => 'Serial', 'teknisi' => 'Teknisi', 'status' => 'Status', 'total' => 'Total', 'dibayar' => 'Dibayar', 'sisa' => 'Sisa Tagihan']];

            case 'service-teknisi':
                $query = DB::table('service as s')->leftJoin('users as u', 'u.id', '=', 's.teknisi_id')->where('s.status', '!=', 'BATAL')
                    ->select(DB::raw("COALESCE(u.name, 'Belum ditugaskan') as teknisi"), DB::raw('COUNT(s.id) as service'), DB::raw("SUM(CASE WHEN s.status NOT IN ('SELESAI','SUDAH_DIAMBIL') THEN 1 ELSE 0 END) as aktif"), DB::raw('SUM(CASE WHEN s.status IN (\'SELESAI\',\'SUDAH_DIAMBIL\') THEN 1 ELSE 0 END) as selesai'), DB::raw("SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM service_approvals a WHERE a.service_id=s.id AND a.status='PENDING') THEN s.total ELSE 0 END) as nilai_service"))
                    ->groupBy('s.teknisi_id', 'u.name')->orderByDesc('service');
                $date($query, 's.tanggal_masuk');
                $search($query, ['u.name']);

                return ['query' => $query, 'columns' => ['teknisi' => 'Teknisi', 'service' => 'Jumlah Service', 'aktif' => 'Aktif', 'selesai' => 'Selesai', 'nilai_service' => 'Nilai Service']];

            case 'service-status':
                $query = DB::table('service as s')->select('s.status', DB::raw('COUNT(s.id) as service'), DB::raw('SUM(s.total) as nilai_service'), DB::raw('SUM(s.dibayar) as dibayar'), DB::raw('SUM(s.total-s.dibayar) as sisa'))->groupBy('s.status')->orderBy('s.status');
                $date($query, 's.tanggal_masuk');
                $search($query, ['s.status']);

                return ['query' => $query, 'columns' => ['status' => 'Status', 'service' => 'Jumlah Service', 'nilai_service' => 'Nilai Service', 'dibayar' => 'Dibayar', 'sisa' => 'Sisa']];

            case 'stok':
                $query = DB::table('produk as p')->join('kategori as k', 'k.id', '=', 'p.kategori_id')->select('p.kode_produk', 'p.nama_produk', 'p.merek', 'k.nama as kategori', 'p.stok', 'p.stok_minimum', 'p.harga_beli', DB::raw('(p.stok * p.harga_beli) as nilai_persediaan'))
                    ->orderByRaw('CASE WHEN p.stok <= p.stok_minimum THEN 0 ELSE 1 END')->orderBy('p.nama_produk');
                $stockSearchColumns = ['p.kode_produk', 'p.nama_produk', 'p.merek', 'k.nama'];
                $search($query, $stockSearchColumns);
                $summaryQuery = DB::table('produk as p')->join('kategori as k', 'k.id', '=', 'p.kategori_id');
                $search($summaryQuery, $stockSearchColumns);
                $summary = $summaryQuery->selectRaw('COUNT(*) as products, COALESCE(SUM(p.stok), 0) as total_stock, COALESCE(SUM(p.stok * p.harga_beli), 0) as inventory_value, COALESCE(SUM(CASE WHEN p.stok <= p.stok_minimum THEN 1 ELSE 0 END), 0) as low_stock')->first();

                return [
                    'query' => $query,
                    'columns' => ['kode_produk' => 'Kode', 'nama_produk' => 'Produk', 'merek' => 'Merek', 'kategori' => 'Kategori', 'stok' => 'Stok', 'stok_minimum' => 'Minimum', 'harga_beli' => 'HPP/unit', 'nilai_persediaan' => 'Nilai Persediaan'],
                    'stats' => [
                        ['label' => 'Produk terdaftar', 'value' => number_format((int) $summary->products, 0, ',', '.'), 'detail' => 'Seluruh produk sesuai filter pencarian'],
                        ['label' => 'Total unit stok', 'value' => number_format((float) $summary->total_stock, 0, ',', '.'), 'detail' => 'Jumlah stok dari seluruh produk'],
                        ['label' => 'Stok menipis', 'value' => number_format((int) $summary->low_stock, 0, ',', '.'), 'detail' => 'Stok di bawah atau sama dengan minimum'],
                        ['label' => 'Nilai inventori', 'value' => $this->money((float) $summary->inventory_value), 'detail' => 'Total stok × HPP; tidak berubah oleh filter tanggal'],
                    ],
                ];

            case 'mutasi-stok':
                $query = DB::table('stok_mutasi as m')->join('produk as p', 'p.id', '=', 'm.produk_id')->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                    ->select('m.tanggal', 'p.kode_produk', 'p.nama_produk', 'm.jenis_mutasi as jenis', 'm.referensi_type', 'm.referensi_id', 'm.qty_masuk', 'm.qty_keluar', 'm.stok_saldo', 'm.hpp', 'm.keterangan', 'u.name as user')->orderByDesc('m.tanggal')->orderByDesc('m.id');
                $date($query, 'm.tanggal');
                $search($query, ['p.kode_produk', 'p.nama_produk', 'm.jenis_mutasi', 'm.keterangan', 'm.referensi_type']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'kode_produk' => 'Kode', 'nama_produk' => 'Produk', 'jenis' => 'Jenis', 'referensi_type' => 'Referensi', 'referensi_id' => 'ID Ref', 'qty_masuk' => 'Masuk', 'qty_keluar' => 'Keluar', 'stok_saldo' => 'Saldo Stok', 'hpp' => 'HPP', 'keterangan' => 'Keterangan', 'user' => 'User']];

            case 'pendapatan':
                $query = DB::table('pendapatan as p')->leftJoin('users as u', 'u.id', '=', 'p.user_id')->whereNotIn('p.jenis_pendapatan', Pendapatan::NON_REVENUE_TYPES)->select('p.tanggal', 'p.kode_pendapatan', 'p.jenis_pendapatan as jenis', 'p.sumber', 'p.referensi_type', 'p.referensi_id', 'p.nominal', 'p.keterangan', 'u.name as user')->orderByDesc('p.tanggal')->orderByDesc('p.id');
                $date($query, 'p.tanggal');
                $search($query, ['p.kode_pendapatan', 'p.jenis_pendapatan', 'p.sumber', 'p.keterangan', 'p.referensi_type']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'kode_pendapatan' => 'Kode', 'jenis' => 'Jenis', 'sumber' => 'Sumber', 'referensi_type' => 'Referensi', 'referensi_id' => 'ID Ref', 'nominal' => 'Nominal', 'keterangan' => 'Keterangan', 'user' => 'User']];

            case 'pengeluaran':
                $query = DB::table('pengeluaran as e')->leftJoin('users as u', 'u.id', '=', 'e.user_id')->whereNotIn('e.kategori_beban', Pengeluaran::CASH_BALANCE_CATEGORIES)->select('e.tanggal', 'e.kode_beban', 'e.kategori_beban as kategori', 'e.keterangan', 'e.nominal', 'e.metode_bayar', 'e.referensi_type', 'e.referensi_id', 'u.name as user')->orderByDesc('e.tanggal')->orderByDesc('e.id');
                $date($query, 'e.tanggal');
                $search($query, ['e.kode_beban', 'e.kategori_beban', 'e.keterangan', 'e.metode_bayar', 'u.name']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'kode_beban' => 'Kode Beban', 'kategori' => 'Kategori', 'keterangan' => 'Keterangan', 'nominal' => 'Nominal', 'metode_bayar' => 'Metode', 'referensi_type' => 'Referensi', 'referensi_id' => 'ID Ref', 'user' => 'User']];

            case 'kas':
                $query = DB::table('kas_mutasi as k')->leftJoin('users as u', 'u.id', '=', 'k.user_id')->select('k.tanggal', 'k.jenis', 'k.referensi_type', 'k.referensi_id', 'k.keterangan', 'k.debit as cash_in', 'k.kredit as cash_out', 'k.saldo', 'u.name as user')->orderByDesc('k.tanggal')->orderByDesc('k.id');
                $date($query, 'k.tanggal');
                $search($query, ['k.jenis', 'k.referensi_type', 'k.keterangan', 'u.name']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'jenis' => 'Jenis', 'referensi_type' => 'Referensi', 'referensi_id' => 'ID Ref', 'keterangan' => 'Keterangan', 'cash_in' => 'Cash In', 'cash_out' => 'Cash Out', 'saldo' => 'Saldo', 'user' => 'User']];

            case 'piutang':
                $query = DB::table('pembayaran as p')->leftJoin('penjualan as s', function ($join) {
                    $join->on('s.id', '=', 'p.referensi_id')->where('p.referensi_type', '=', 'PENJUALAN');
                })
                    ->leftJoin('service as v', function ($join) {
                        $join->on('v.id', '=', 'p.referensi_id')->where('p.referensi_type', '=', 'SERVICE');
                    })
                    ->leftJoin('pelanggan as c', function ($join) {
                        $join->on('c.id', '=', DB::raw('COALESCE(s.pelanggan_id,v.pelanggan_id)'));
                    })
                    ->leftJoin('users as u', 'u.id', '=', 'p.user_id')->where('p.metode', 'PIUTANG')->where('p.status', 'PENDING')
                    ->select('p.tanggal', DB::raw('COALESCE(s.no_invoice,v.no_service) as referensi'), 'c.nama as pelanggan', 'p.referensi_type as jenis', 'p.nominal as sisa', 'p.keterangan', 'u.name as user')->orderByDesc('p.tanggal')->orderByDesc('p.id');
                $date($query, 'p.tanggal');
                $search($query, ['s.no_invoice', 'v.no_service', 'c.nama', 'p.keterangan']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'referensi' => 'Invoice / Service', 'pelanggan' => 'Pelanggan', 'jenis' => 'Jenis', 'sisa' => 'Sisa Piutang', 'keterangan' => 'Keterangan', 'user' => 'Petugas']];

            case 'pembayaran':
                $query = DB::table('pembayaran as p')->leftJoin('penjualan as s', function ($join) {
                    $join->on('s.id', '=', 'p.referensi_id')->where('p.referensi_type', '=', 'PENJUALAN');
                })
                    ->leftJoin('service as v', function ($join) {
                        $join->on('v.id', '=', 'p.referensi_id')->where('p.referensi_type', '=', 'SERVICE');
                    })
                    ->leftJoin('users as u', 'u.id', '=', 'p.user_id')->select('p.tanggal', DB::raw('COALESCE(s.no_invoice,v.no_service,p.referensi_type) as referensi'), 'p.metode', 'p.nominal', 'p.status', 'p.keterangan', 'u.name as user')->orderByDesc('p.tanggal')->orderByDesc('p.id');
                $date($query, 'p.tanggal');
                $search($query, ['s.no_invoice', 'v.no_service', 'p.metode', 'p.status', 'p.keterangan']);

                return ['query' => $query, 'columns' => ['tanggal' => 'Tanggal', 'referensi' => 'Referensi', 'metode' => 'Metode', 'nominal' => 'Nominal', 'status' => 'Status', 'keterangan' => 'Keterangan', 'user' => 'Petugas']];

            default:
                abort(404);
        }
    }

    private function profitLossDataset(array $filters, string $report): array
    {
        $sales = DB::table('penjualan_detail as d')->join('penjualan as s', 's.id', '=', 'd.penjualan_id')->leftJoin('laptop as l', 'l.id', '=', 'd.laptop_id')->whereNotIn('s.status', ['DRAFT', 'BATAL']);
        $this->applyDate($sales, 's.tanggal', $filters);
        if (! empty($filters['q'])) {
            $this->applySearch($sales, ['s.no_invoice'], $filters['q']);
        }
        $salesRevenue = (float) (clone $sales)->sum('d.subtotal');
        $salesCogs = (float) (clone $sales)->sum(DB::raw('(d.hpp + COALESCE(l.biaya_tambahan,0)) * d.qty'));

        $services = DB::table('service as s')->whereNotIn('s.status', ['BATAL'])->whereNotExists(function ($query) {
            $query->selectRaw('1')->from('service_approvals as a')->whereColumn('a.service_id', 's.id')->where('a.status', 'PENDING');
        });
        $this->applyDate($services, 's.tanggal_masuk', $filters);
        if (! empty($filters['q'])) {
            $this->applySearch($services, ['s.no_service'], $filters['q']);
        }
        $serviceRevenue = (float) (clone $services)->sum('s.total');
        $serviceIds = (clone $services)->select('s.id');
        $serviceCogs = (float) DB::table('service_detail as d')->whereIn('d.service_id', $serviceIds)->whereNotNull('d.stock_deducted_at')->sum(DB::raw('d.hpp * d.qty'));

        $otherIncomeQuery = DB::table('pendapatan as p')->whereNotIn('p.jenis_pendapatan', [
            ...['SERVICE', 'PENJUALAN_AKSESORIS', 'PENJUALAN_LAPTOP'],
            ...Pendapatan::NON_REVENUE_TYPES,
        ])
            ->where(function ($query) {
                $query->whereNull('p.referensi_type')->orWhereNotIn('p.referensi_type', ['PENJUALAN', 'SERVICE']);
            });
        $this->applyDate($otherIncomeQuery, 'p.tanggal', $filters);
        if (! empty($filters['q'])) {
            $this->applySearch($otherIncomeQuery, ['p.kode_pendapatan', 'p.sumber', 'p.keterangan'], $filters['q']);
        }
        $otherIncome = (float) (clone $otherIncomeQuery)->sum('p.nominal');

        $expenseQuery = DB::table('pengeluaran as e')->whereNotIn('e.kategori_beban', Pengeluaran::NON_PROFIT_LOSS_CATEGORIES);
        $this->applyDate($expenseQuery, 'e.tanggal', $filters);
        if (! empty($filters['q'])) {
            $this->applySearch($expenseQuery, ['e.kode_beban', 'e.kategori_beban', 'e.keterangan'], $filters['q']);
        }
        $expenses = (float) (clone $expenseQuery)->sum('e.nominal');

        $salesGross = $salesRevenue - $salesCogs;
        $serviceGross = $serviceRevenue - $serviceCogs;
        $gross = $salesGross + $serviceGross;
        $net = $gross + $otherIncome - $expenses;
        $rows = [
            ['komponen' => 'Penjualan bersih setelah diskon', 'nominal' => $salesRevenue, 'catatan' => 'Berdasarkan detail invoice; tidak mengambil ulang pendapatan Penjualan yang dicatat per pembayaran.'],
            ['komponen' => 'HPP produk terjual', 'nominal' => -$salesCogs, 'catatan' => 'HPP transaksi termasuk biaya tambahan unit laptop. Pembelian stok tidak dikurangkan di sini.'],
            ['komponen' => 'Laba kotor penjualan', 'nominal' => $salesGross, 'catatan' => 'Penjualan bersih dikurangi HPP produk terjual.'],
            ['komponen' => 'Pendapatan service', 'nominal' => $serviceRevenue, 'catatan' => 'Nilai service disetujui; estimasi yang masih menunggu approval dikecualikan.'],
            ['komponen' => 'HPP sparepart terpakai', 'nominal' => -$serviceCogs, 'catatan' => 'HPP sparepart yang telah tercatat keluar dari stok.'],
            ['komponen' => 'Laba kotor service', 'nominal' => $serviceGross, 'catatan' => 'Pendapatan service dikurangi HPP sparepart terpakai.'],
            ['komponen' => 'Total laba kotor', 'nominal' => $gross, 'catatan' => 'Laba kotor penjualan ditambah laba kotor service.'],
        ];
        if ($report !== 'laba-kotor') {
            $rows[] = ['komponen' => 'Pendapatan lain', 'nominal' => $otherIncome, 'catatan' => 'Printing, sewa, dan pendapatan lain; baris otomatis Penjualan/Service tidak dijumlahkan ulang.'];
            $rows[] = ['komponen' => 'Beban operasional dan lainnya', 'nominal' => -$expenses, 'catatan' => 'Beban yang tercatat di pengeluaran, termasuk payroll. Tabel Pembelian inventory tidak dianggap beban.'];
            $rows[] = ['komponen' => 'Laba bersih', 'nominal' => $net, 'catatan' => 'Laba kotor + pendapatan lain − pengeluaran.'];
        } elseif ($report === 'laba-kotor') {
            $rows = array_slice($rows, 0, 7);
        }
        if ($report === 'laba-bersih') {
            $rows = array_slice($rows, -3);
        }
        if (! empty($filters['q'])) {
            $needle = mb_strtolower($filters['q']);
            $rows = array_values(array_filter($rows, fn ($row) => str_contains(mb_strtolower($row['komponen'].' '.$row['catatan']), $needle)));
        }
        $stats = [
            ['label' => 'Laba Kotor', 'value' => $this->money($gross), 'detail' => 'Penjualan dan service setelah HPP terpakai'],
            ['label' => 'Pendapatan Lain', 'value' => $this->money($otherIncome), 'detail' => 'Tidak menduplikasi invoice penjualan/service'],
            ['label' => 'Total Beban', 'value' => $this->money($expenses), 'detail' => 'Dari pengeluaran pada periode'],
            ['label' => 'Laba Bersih', 'value' => $this->money($net), 'detail' => 'Tidak mengurangi pembelian inventory secara langsung'],
        ];

        return ['rows' => $rows, 'columns' => ['komponen' => 'Komponen', 'nominal' => 'Nilai', 'catatan' => 'Perhitungan'], 'stats' => $stats];
    }

    private function applyDate(Builder $query, string $column, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function applySearch(Builder $query, array $columns, ?string $search): void
    {
        if (! $search) {
            return;
        }
        $query->where(function (Builder $builder) use ($columns, $search): void {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function money(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
