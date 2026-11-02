<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pimpinan_can_create_a_cashier_account_from_user_management(): void
    {
        $pimpinan = $this->userWithPermissions(['manage_users']);
        $cashierRole = Role::create(['name' => 'KASIR', 'description' => 'Petugas transaksi']);

        $this->actingAs($pimpinan)->get(route('module.users'))
            ->assertOk()
            ->assertSee('Tambah Pengguna');

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('KASIR')
            ->assertSee('name="password_confirmation"', false);

        $this->post(route('users.store'), [
            'name' => 'Kasir Baru',
            'email' => 'kasir.baru@example.test',
            'phone' => '08123456789',
            'role_id' => $cashierRole->id,
            'status' => 'AKTIF',
            'password' => 'KasirAman123!',
            'password_confirmation' => 'KasirAman123!',
        ])->assertRedirect(route('module.users'))
            ->assertSessionHas('status', 'Pengguna berhasil ditambahkan.');

        $user = User::where('email', 'kasir.baru@example.test')->firstOrFail();
        $this->assertSame($cashierRole->id, $user->role_id);
        $this->assertTrue(Hash::check('KasirAman123!', $user->password));
        $this->assertSame('AKTIF', $user->status);
    }

    public function test_pelanggan_account_cannot_be_created_manually_from_user_management(): void
    {
        $pimpinan = $this->userWithPermissions(['manage_users']);
        $customerRole = Role::create(['name' => 'PELANGGAN']);

        $this->actingAs($pimpinan)->get(route('users.create'))
            ->assertOk()->assertDontSee('PELANGGAN');
        $this->actingAs($pimpinan)->post(route('users.store'), [
            'name' => 'Pelanggan Baru',
            'email' => 'pelanggan.baru@example.test',
            'role_id' => $customerRole->id,
            'status' => 'AKTIF',
            'password' => 'PelangganAman123!',
            'password_confirmation' => 'PelangganAman123!',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'pelanggan.baru@example.test']);
    }

    public function test_user_management_cannot_create_another_pimpinan_account(): void
    {
        $pimpinan = $this->userWithPermissions(['manage_users']);
        $ownerRole = Role::create(['name' => 'PIMPINAN']);

        $this->actingAs($pimpinan)->post(route('users.store'), [
            'name' => 'Owner Kedua',
            'email' => 'owner.kedua@example.test',
            'role_id' => $ownerRole->id,
            'status' => 'AKTIF',
            'password' => 'OwnerAman123!',
            'password_confirmation' => 'OwnerAman123!',
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'owner.kedua@example.test']);
        $this->assertSame(0, Pelanggan::count());
    }

    public function test_user_management_routes_require_manage_users_permission(): void
    {
        $user = $this->userWithPermissions(['view_dashboard']);

        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
        $this->actingAs($user)->post(route('users.store'), [])->assertForbidden();
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_USER_MANAGER']);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
