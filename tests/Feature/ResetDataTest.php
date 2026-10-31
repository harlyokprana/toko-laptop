<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_sqlite_reset_removes_operational_data_and_preserves_access_data(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $role = Role::create(['name' => 'PIMPINAN']);
        $permission = Permission::create(['name' => 'manage_roles']);
        $role->permissions()->attach($permission);
        $currentUser = User::factory()->create(['role_id' => $role->id]);
        $otherUser = User::factory()->create(['role_id' => $role->id]);
        $category = Kategori::create(['nama' => 'Kategori Reset', 'jenis' => 'LAINNYA', 'status' => 'AKTIF']);
        $product = Produk::create([
            'kode_produk' => 'RESET-001',
            'nama_produk' => 'Produk untuk direset',
            'kategori_id' => $category->id,
            'satuan' => 'PCS',
            'harga_beli' => 100,
            'harga_jual' => 150,
            'stok' => 1,
            'stok_minimum' => 0,
            'status' => 'AKTIF',
        ]);

        $this->actingAs($currentUser)
            ->post(route('reset-data.run'), ['confirmation' => 'RESET ALL DATA'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Data aplikasi berhasil direset. Role, permission, schema, dan akun pimpinan yang sedang digunakan tetap tersedia.');

        $this->assertDatabaseHas('users', ['id' => $currentUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'PIMPINAN']);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'manage_roles']);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
        $this->assertDatabaseMissing('produk', ['id' => $product->id]);
        $this->assertDatabaseMissing('kategori', ['id' => $category->id]);
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertSame(0, (int) DB::selectOne('PRAGMA defer_foreign_keys')->defer_foreign_keys);
    }

    public function test_reset_requires_exact_confirmation(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $role = Role::create(['name' => 'PIMPINAN']);
        $role->permissions()->attach(Permission::create(['name' => 'manage_roles']));
        $user = User::factory()->create(['role_id' => $role->id]);
        $category = Kategori::create(['nama' => 'Kategori Tetap', 'jenis' => 'LAINNYA', 'status' => 'AKTIF']);

        $this->actingAs($user)
            ->from(route('reset-data.show'))
            ->post(route('reset-data.run'), ['confirmation' => 'RESET'])
            ->assertRedirect(route('reset-data.show'))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('kategori', ['id' => $category->id]);
    }
}
