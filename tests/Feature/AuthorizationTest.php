<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pimpinan_can_view_dashboard_reports_and_profit_loss(): void
    {
        $user = $this->userWithPermissions(['view_dashboard', 'view_reports', 'view_profit_loss']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('module.reports'))->assertOk();
        $this->actingAs($user)->get(route('module.profit-loss'))->assertOk();
    }

    public function test_admin_cannot_view_reports_or_profit_loss(): void
    {
        $user = $this->userWithPermissions(['view_dashboard', 'view_products', 'manage_users']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('module.reports'))->assertForbidden();
        $this->actingAs($user)->get(route('module.profit-loss'))->assertForbidden();
    }

    public function test_hpp_reports_require_the_dedicated_hpp_permission(): void
    {
        $user = $this->userWithPermissions(['view_dashboard', 'view_reports']);

        $this->actingAs($user)->get(route('module.reports'))->assertOk()->assertDontSee('Detail Penjualan');
        $this->actingAs($user)->get(route('reports.show', 'detail-penjualan'))->assertForbidden();

        $role = $user->role;
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'view_hpp']));
        $user->unsetRelation('role');
        $this->actingAs($user)->get(route('reports.show', 'detail-penjualan'))->assertOk();
    }

    public function test_technician_cannot_view_profit_loss(): void
    {
        $user = $this->userWithPermissions(['view_dashboard', 'view_service', 'update_service_progress', 'view_stock']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('module.service-progress'))->assertOk();
        $this->actingAs($user)->get(route('module.profit-loss'))->assertForbidden();
    }

    public function test_customer_can_view_only_dashboard_and_own_service_area(): void
    {
        $user = $this->userWithPermissions(['view_dashboard', 'view_service']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Penjualan Hari Ini')->assertDontSee('Pendapatan Hari Ini');
        $this->actingAs($user)->get(route('module.service'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertForbidden();
        $this->actingAs($user)->get(route('module.reports'))->assertForbidden();
        $this->actingAs($user)->get(route('module.profit-loss'))->assertForbidden();
    }

    public function test_permission_helpers_check_any_and_all_permissions(): void
    {
        $user = $this->userWithPermissions(['view_dashboard', 'view_products']);

        $this->assertTrue($user->hasPermission('view_dashboard'));
        $this->assertTrue($user->hasAnyPermission(['view_reports', 'view_products']));
        $this->assertTrue($user->hasAllPermissions(['view_dashboard', 'view_products']));
        $this->assertFalse($user->hasAllPermissions(['view_dashboard', 'view_reports']));
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_ROLE']);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
