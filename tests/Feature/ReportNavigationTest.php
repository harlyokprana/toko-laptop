<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_report_card_opens_its_report_page(): void
    {
        $user = $this->userWithPermissions(['view_reports', 'view_profit_loss', 'view_hpp']);
        $reports = [
            'penjualan',
            'detail-penjualan',
            'penjualan-produk',
            'penjualan-sales',
            'penjualan-laptop',
            'pembelian',
            'pembelian-supplier',
            'service',
            'service-teknisi',
            'service-status',
            'stok',
            'mutasi-stok',
            'pendapatan',
            'pengeluaran',
            'kas',
            'piutang',
            'pembayaran',
            'laba-kotor',
            'laba-bersih',
            'profit-loss',
        ];

        $this->actingAs($user)->get(route('module.reports'))
            ->assertOk()
            ->assertSee('Penjualan per Produk')
            ->assertSee('/reports/penjualan', false);

        foreach ([
            'sales' => 'Pembelian',
            'service' => 'Service per Teknisi',
            'cash' => 'Pembayaran',
            'stock' => 'Mutasi Stok',
            'other' => 'Profit & Loss',
        ] as $type => $label) {
            $this->get(route('module.reports', ['type' => $type]))
                ->assertOk()
                ->assertSee($label);
        }

        foreach ($reports as $report) {
            $this->get(route('reports.show', $report))->assertOk();
        }
    }

    public function test_report_pages_require_report_permission(): void
    {
        $user = $this->userWithPermissions(['view_dashboard']);

        $this->actingAs($user)->get(route('reports.show', 'penjualan'))->assertForbidden();
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_REPORTS']);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
