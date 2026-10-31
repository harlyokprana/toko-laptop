<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_role_and_is_redirected_to_access_management(): void
    {
        $manager = $this->manager();
        Permission::query()->create(['name' => 'create_sales']);

        $response = $this->actingAs($manager)->post(route('roles.store'), [
            'name' => 'Kasir Cabang',
            'description' => 'Mengelola transaksi cabang.',
            'is_active' => 1,
        ]);
        $role = Role::query()->where('name', 'KASIR CABANG')->firstOrFail();

        $response->assertRedirect(route('roles.permissions.edit', $role));
        $this->assertTrue($role->is_active);
        $this->assertCount(0, $role->permissions);

        $this->actingAs($manager)->get(route('roles.permissions.edit', $role))
            ->assertOk()
            ->assertSee('Membuat transaksi dan mencatat pembayaran')
            ->assertSee('Penjualan & Pelanggan');
    }

    public function test_manager_can_update_role_access_using_known_permission_ids(): void
    {
        $manager = $this->manager();
        $role = Role::query()->create(['name' => 'KASIR CABANG', 'description' => 'Role kasir.']);
        $permission = Permission::query()->create(['name' => 'view_stock', 'description' => 'View stock']);

        $this->actingAs($manager)->put(route('roles.permissions.update', $role), [
            'permissions' => [$permission->id],
        ])->assertRedirect(route('roles.permissions.edit', $role));

        $this->assertTrue($role->fresh()->permissions->contains('id', $permission->id));
    }

    public function test_role_in_use_and_system_roles_cannot_be_deleted(): void
    {
        $manager = $this->manager();
        $inUse = Role::query()->create(['name' => 'ROLE TERPAKAI']);
        User::factory()->create(['role_id' => $inUse->id]);
        $systemRole = Role::query()->create(['name' => 'KASIR']);

        $this->actingAs($manager)->delete(route('roles.destroy', $inUse))
            ->assertSessionHasErrors('role');
        $this->actingAs($manager)->delete(route('roles.destroy', $systemRole))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $inUse->id]);
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_system_role_name_and_active_status_cannot_be_changed(): void
    {
        $manager = $this->manager();
        $systemRole = Role::query()->create(['name' => 'KASIR']);

        $this->actingAs($manager)->put(route('roles.update', $systemRole), [
            'name' => 'OPERATOR',
            'description' => 'Kasir',
            'is_active' => 1,
        ])->assertSessionHasErrors('name');

        $this->actingAs($manager)->put(route('roles.update', $systemRole), [
            'name' => 'KASIR',
            'description' => 'Kasir',
            'is_active' => 0,
        ])->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id, 'name' => 'KASIR', 'is_active' => true]);
    }

    public function test_pimpinan_and_current_manager_cannot_remove_their_required_role_access(): void
    {
        $manager = $this->manager();
        $pimpinan = Role::query()->create(['name' => 'PIMPINAN']);
        $dashboard = Permission::query()->create(['name' => 'view_dashboard']);
        $manageRoles = Permission::query()->firstOrCreate(['name' => 'manage_roles']);
        $pimpinan->permissions()->attach([$dashboard->id, $manageRoles->id]);

        $this->actingAs($manager)->put(route('roles.permissions.update', $pimpinan), [
            'permissions' => [],
        ])->assertSessionHasErrors('permissions');

        $this->actingAs($manager)->put(route('roles.permissions.update', $manager->role), [
            'permissions' => [],
        ])->assertSessionHasErrors('permissions');

        $this->assertTrue($manager->fresh()->hasPermission('manage_roles'));
        $this->assertTrue($pimpinan->fresh()->permissions->contains('name', 'manage_roles'));
    }

    public function test_permissions_of_a_deactivated_role_are_not_granted(): void
    {
        $role = Role::query()->create(['name' => 'ROLE NONAKTIF', 'is_active' => false]);
        $role->permissions()->attach(Permission::query()->create(['name' => 'view_dashboard'])->id);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertFalse($user->hasPermission('view_dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    private function manager(): User
    {
        $role = Role::query()->create(['name' => 'PENGATUR AKSES']);
        $role->permissions()->attach(Permission::query()->firstOrCreate(['name' => 'manage_roles'])->id);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
