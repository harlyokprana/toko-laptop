<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use App\Models\Pelanggan;
use App\Models\Penjualan;
use App\Models\Pembelian;
use App\Models\Produk;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        $term = trim($data['q']);
        $user = $request->user();
        $results = collect();

        if ($user->hasAnyPermission(['view_products', 'view_stock'])) {
            foreach (Produk::query()->where(function (Builder $query) use ($term): void {
                $query->where('kode_produk', 'like', "%{$term}%")
                    ->orWhere('nama_produk', 'like', "%{$term}%")
                    ->orWhere('merek', 'like', "%{$term}%");
            })->limit(6)->get(['id', 'kode_produk', 'nama_produk', 'merek']) as $product) {
                $results->push(['type' => 'Produk', 'title' => $product->nama_produk, 'detail' => trim($product->kode_produk.' · '.($product->merek ?? '')), 'url' => $user->hasPermission('view_stock') ? route('stock.products.show', $product) : route('products.index')]);
            }
        }

        if ($user->hasPermission('view_stock')) {
            foreach (Laptop::query()->where(function (Builder $query) use ($term): void {
                $query->where('sku', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%")
                    ->orWhere('model_seri', 'like', "%{$term}%");
            })->limit(6)->get() as $laptop) {
                $results->push(['type' => 'Nomor Serial', 'title' => $laptop->model_seri ?: 'Laptop', 'detail' => $laptop->sku.' · '.$laptop->serial_number, 'url' => route('laptops.show', $laptop)]);
            }
        }

        if ($user->hasPermission('view_sales')) {
            foreach (Pelanggan::query()->where(function (Builder $query) use ($term): void {
                $query->where('nama', 'like', "%{$term}%")
                    ->orWhere('kode_pelanggan', 'like', "%{$term}%")
                    ->orWhere('telepon', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })->limit(6)->get(['id', 'kode_pelanggan', 'nama', 'telepon']) as $customer) {
                $results->push(['type' => 'Pelanggan', 'title' => $customer->nama, 'detail' => $customer->kode_pelanggan.' · '.($customer->telepon ?: 'Tanpa telepon'), 'url' => route('customers.show', $customer)]);
            }
            foreach (Penjualan::query()->where('no_invoice', 'like', "%{$term}%")->latest('id')->limit(6)->get(['id', 'no_invoice', 'total', 'status']) as $sale) {
                $results->push(['type' => 'Invoice', 'title' => $sale->no_invoice, 'detail' => 'Rp '.number_format((float) $sale->total, 0, ',', '.').' · '.$sale->status, 'url' => route('sales.show', $sale)]);
            }
        }

        if ($user->hasPermission('view_purchases')) {
            foreach (\App\Models\Supplier::query()->where(function (Builder $query) use ($term): void {
                $query->where('nama', 'like', "%{$term}%")
                    ->orWhere('kode_supplier', 'like', "%{$term}%")
                    ->orWhere('telepon', 'like', "%{$term}%");
            })->limit(5)->get(['id', 'nama', 'kode_supplier']) as $supplier) {
                $results->push(['type' => 'Supplier', 'title' => $supplier->nama, 'detail' => $supplier->kode_supplier, 'url' => route('suppliers.show', $supplier)]);
            }
            foreach (Pembelian::query()->where('jenis_pembelian', 'PEMBELIAN')->where('no_invoice', 'like', "%{$term}%")->latest('id')->limit(5)->get(['id', 'no_invoice', 'total', 'status']) as $purchase) {
                $results->push(['type' => 'Purchase Order', 'title' => $purchase->no_invoice, 'detail' => 'Rp '.number_format((float) $purchase->total, 0, ',', '.').' · '.$purchase->status, 'url' => route('purchase-orders.show', $purchase)]);
            }
        }

        if ($user->hasPermission('view_service')) {
            $serviceQuery = Service::query();
            if ($user->role?->name === 'TEKNISI') {
                $serviceQuery->where('teknisi_id', $user->id);
            } elseif ($user->role?->name === 'PELANGGAN') {
                $serviceQuery->whereHas('pelanggan', fn (Builder $customer) => $customer->where('user_id', $user->id));
            }
            foreach ($serviceQuery->where(function (Builder $query) use ($term): void {
                $query->where('no_service', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%")
                    ->orWhere('merek', 'like', "%{$term}%")
                    ->orWhere('model', 'like', "%{$term}%");
            })->latest('id')->limit(6)->get(['id', 'no_service', 'serial_number', 'status', 'model', 'merek']) as $service) {
                $results->push(['type' => 'Service', 'title' => $service->no_service, 'detail' => trim(($service->merek ?: '').' '.($service->model ?: '').' · '.str_replace('_', ' ', $service->status)), 'url' => route('service.show', $service)]);
            }
        }

        return view('search.index', ['term' => $term, 'results' => $results->take(30)->values()]);
    }
}
