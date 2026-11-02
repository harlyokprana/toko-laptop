<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pembelian;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\BusinessNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_service_notifies_its_customer_and_assigned_technician_only(): void
    {
        $operator = $this->userWithPermissions(['create_service']);
        $technicianRole = Role::create(['name' => 'TEKNISI']);
        $technician = User::factory()->create(['role_id' => $technicianRole->id]);
        $customerAccount = User::factory()->create(['role_id' => Role::create(['name' => 'PELANGGAN'])->id]);
        $otherUser = User::factory()->create();
        $customer = Pelanggan::create([
            'user_id' => $customerAccount->id,
            'nama' => 'Pelanggan Tes',
            'status' => 'AKTIF',
        ]);

        $this->actingAs($operator)->post(route('service.store'), [
            'pelanggan_id' => $customer->id,
            'jenis_perangkat' => 'Laptop',
            'merek' => 'Contoh',
            'model' => 'Model Tes',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $customerAccount->unreadNotifications()->count());
        $this->assertSame(1, $technician->unreadNotifications()->count());
        $this->assertSame(0, $operator->notifications()->count());
        $this->assertSame(0, $otherUser->notifications()->count());

        $notification = $customerAccount->notifications()->firstOrFail();
        $this->actingAs($customerAccount)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Service baru diterima');

        $this->get(route('notifications.open', $notification->id))
            ->assertRedirect($notification->data['url']);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($otherUser)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();
    }

    public function test_user_can_mark_only_their_own_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->notify(new BusinessNotification('Milik saya', 'Pesan', '/dashboard'));
        $otherUser->notify(new BusinessNotification('Milik user lain', 'Pesan', '/dashboard'));

        $this->actingAs($user)->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
    }

    public function test_purchase_order_notifies_approvers_and_then_its_creator(): void
    {
        $creator = $this->userWithPermissions(['view_purchases', 'create_purchases']);
        $approver = $this->userWithPermissions(['view_purchases', 'edit_purchases']);
        $supplier = Supplier::create([
            'kode_supplier' => 'SUP-'.uniqid(),
            'nama' => 'Supplier Tes',
            'status' => 'AKTIF',
        ]);
        $category = Kategori::create([
            'nama' => 'Aksesori Tes',
            'jenis' => 'AKSESORIS',
            'status' => 'AKTIF',
        ]);
        $product = Produk::create([
            'kode_produk' => 'PROD-'.uniqid(),
            'nama_produk' => 'Mouse Tes',
            'kategori_id' => $category->id,
            'satuan' => 'PCS',
            'harga_beli' => 100,
            'harga_jual' => 150,
            'stok' => 5,
            'stok_minimum' => 1,
            'status' => 'AKTIF',
        ]);

        $this->actingAs($creator)->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'tanggal' => today()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 100,
                'discount_type' => 'NOMINAL',
                'discount_value' => 0,
            ]],
        ])->assertRedirect();

        $purchaseOrder = Pembelian::query()->firstOrFail();
        $this->assertSame(1, $approver->unreadNotifications()->count());
        $this->assertSame(0, $creator->notifications()->count());

        $this->actingAs($approver)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertRedirect();

        $this->assertSame(1, $creator->unreadNotifications()->count());
        $this->assertSame(1, $approver->unreadNotifications()->count());
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_'.uniqid()]);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
