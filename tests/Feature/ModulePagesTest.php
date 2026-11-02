<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pimpinan_can_open_every_business_menu_without_placeholder_pages(): void
    {
        $permissions = [
            'view_dashboard', 'view_products', 'create_products', 'edit_products', 'delete_products',
            'view_sales', 'create_sales', 'edit_sales', 'view_purchases', 'create_purchases', 'edit_purchases',
            'view_service', 'create_service', 'edit_service', 'update_service_progress', 'view_stock',
            'adjust_stock', 'view_income', 'view_expense', 'view_cash', 'view_reports', 'view_profit_loss',
            'manage_users', 'manage_roles', 'use_ai',
        ];
        $role = Role::create(['name' => 'PIMPINAN']);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission]));
        }

        $user = User::factory()->create(['role_id' => $role->id]);
        $routes = [
            'products.index', 'laptops.index', 'customers.index', 'suppliers.index',
            'module.sales', 'purchase-orders.index', 'purchase-orders.receiving.index', 'module.service',
            'module.service-progress', 'stock.index', 'module.income', 'expenses.index', 'module.cash',
            'module.reports', 'module.profit-loss', 'module.ai', 'module.users', 'module.roles',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('Halaman ini sudah dilindungi permission yang sesuai.')
                ->assertDontSee('Fitur operasional');
        }

        $this->actingAs($user)->get(route('categories.index'))
            ->assertRedirect(route('products.index'));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="theme-toggle"', false)
            ->assertSee("localStorage.getItem('app-theme')", false);
    }

    public function test_service_progress_list_shows_one_link_per_service_and_opens_its_history(): void
    {
        $role = Role::create(['name' => 'PROGRESS_TEST']);
        foreach (['view_service', 'update_service_progress'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'PLG-PROGRESS',
            'nama' => 'Pelanggan Progress',
            'status' => 'AKTIF',
        ]);
        $service = Service::create([
            'no_service' => 'SRV-GROUPING',
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'merek' => 'Lenovo',
            'model' => 'ThinkPad',
            'keluhan' => 'Tidak menyala',
            'status' => 'TESTING',
        ]);
        foreach ([['DALAM_PERBAIKAN', 60], ['TESTING', 80]] as [$status, $percent]) {
            ServiceProgress::create([
                'service_id' => $service->id,
                'status' => $status,
                'progress_percent' => $percent,
                'catatan' => 'Pembaruan '.$percent,
                'updated_by' => $user->id,
                'created_at' => now()->addSeconds($percent),
            ]);
        }

        $response = $this->actingAs($user)->get(route('module.service-progress'))
            ->assertOk()
            ->assertSee(route('service.show', $service), false)
            ->assertSee('80%')
            ->assertSee('TESTING')
            ->assertDontSee('Pembaruan 60')
            ->assertDontSee('Pembaruan 80');

        $this->assertSame(1, substr_count($response->getContent(), 'SRV-GROUPING'));
    }
}
