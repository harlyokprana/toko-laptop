<?php

namespace Tests\Feature;

use App\Models\Penjualan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_returns_records_allowed_by_the_users_permissions(): void
    {
        $salesUser = $this->userWithPermissions(['view_sales']);
        $dashboardOnlyUser = $this->userWithPermissions(['view_dashboard']);
        $sale = Penjualan::create([
            'no_invoice' => 'INV-SEARCH-SECRET', 'tanggal' => today(), 'jenis_penjualan' => 'LAINNYA',
            'subtotal' => 1000, 'total' => 1000, 'dibayar' => 1000, 'status' => 'SELESAI', 'user_id' => $salesUser->id,
        ]);

        $this->actingAs($salesUser)->get(route('global-search', ['q' => 'INV-SEARCH']))
            ->assertOk()->assertSee('INV-SEARCH-SECRET')->assertSee(route('sales.show', $sale));
        $this->actingAs($dashboardOnlyUser)->get(route('global-search', ['q' => 'INV-SEARCH']))
            ->assertOk()->assertSee('Tidak ada hasil yang cocok')->assertDontSee('INV-SEARCH-SECRET');
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::create(['name' => 'SEARCH_'.uniqid()]);
        foreach ($names as $name) {
            $role->permissions()->attach(Permission::create(['name' => $name]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
