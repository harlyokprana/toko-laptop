<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceApproval;
use App\Models\ServiceDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_cannot_enter_repair_when_approved_parts_are_unavailable(): void
    {
        $technician = $this->userWithPermissions(['update_service_progress']);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'PLG-'.uniqid(),
            'nama' => 'Pelanggan Tes',
            'status' => 'AKTIF',
        ]);
        $category = Kategori::create([
            'nama' => 'Sparepart Tes',
            'jenis' => 'SPAREPART',
            'status' => 'AKTIF',
        ]);
        $product = Produk::create([
            'kode_produk' => 'PART-'.uniqid(),
            'nama_produk' => 'Sparepart Tes',
            'kategori_id' => $category->id,
            'satuan' => 'PCS',
            'harga_beli' => 10,
            'harga_jual' => 15,
            'stok' => 0,
            'stok_minimum' => 0,
            'status' => 'AKTIF',
        ]);
        $service = Service::create([
            'no_service' => 'SRV-'.uniqid(),
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
            'status' => 'MENUNGGU_SPAREPART',
        ]);
        $approval = ServiceApproval::create([
            'service_id' => $service->id,
            'estimasi' => 15,
            'status' => 'APPROVED',
            'requested_by' => $technician->id,
            'approved_by' => $technician->id,
            'approved_at' => now(),
        ]);
        ServiceDetail::create([
            'service_id' => $service->id,
            'approval_id' => $approval->id,
            'produk_id' => $product->id,
            'qty' => 1,
            'harga' => 15,
            'hpp' => 10,
            'subtotal' => 15,
        ]);

        $this->actingAs($technician)
            ->from(route('service.show', $service))
            ->post(route('service.progress.store', $service), [
                'status' => 'DALAM_PERBAIKAN',
                'catatan' => 'Mulai perbaikan',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('service', [
            'id' => $service->id,
            'status' => 'MENUNGGU_SPAREPART',
        ]);
        $this->assertDatabaseHas('produk', ['id' => $product->id, 'stok' => 0]);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_service_status_cannot_skip_required_workflow_stages(): void
    {
        $technician = $this->userWithPermissions(['update_service_progress', 'view_service']);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'PLG-'.uniqid(),
            'nama' => 'Pelanggan Tes',
            'status' => 'AKTIF',
        ]);
        $service = Service::create([
            'no_service' => 'SRV-'.uniqid(),
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
            'status' => 'DITERIMA',
        ]);

        $this->actingAs($technician)
            ->from(route('service.show', $service))
            ->post(route('service.progress.store', $service), [
                'status' => 'SELESAI',
                'catatan' => 'Lewati proses diagnosis dan testing',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('service', ['id' => $service->id, 'status' => 'DITERIMA']);
        $this->assertDatabaseCount('service_progress', 0);
    }

    public function test_service_must_have_a_diagnosis_before_estimate_can_be_requested(): void
    {
        $cashier = $this->userWithPermissions(['create_service']);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'PLG-'.uniqid(),
            'nama' => 'Pelanggan Tes',
            'status' => 'AKTIF',
        ]);
        $service = Service::create([
            'no_service' => 'SRV-'.uniqid(),
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'status' => 'DIAGNOSA',
        ]);

        $this->actingAs($cashier)->post(route('service.approvals.store', $service), [
            'jasa' => 50000,
            'diskon_tipe' => 'NOMINAL',
            'catatan' => 'Menunggu pemeriksaan teknisi.',
        ])->assertForbidden();

        $this->assertDatabaseCount('service_approvals', 0);
        $this->assertDatabaseHas('service', ['id' => $service->id, 'status' => 'DIAGNOSA']);
    }

    public function test_technician_must_record_diagnosis_before_advancing_service(): void
    {
        $technician = $this->userWithPermissions(['update_service_progress', 'view_service']);
        $customer = Pelanggan::create([
            'kode_pelanggan' => 'PLG-'.uniqid(),
            'nama' => 'Pelanggan Tes',
            'status' => 'AKTIF',
        ]);
        $service = Service::create([
            'no_service' => 'SRV-'.uniqid(),
            'pelanggan_id' => $customer->id,
            'tanggal_masuk' => now(),
            'jenis_perangkat' => 'Laptop',
            'keluhan' => 'Tidak menyala',
            'teknisi_id' => $technician->id,
            'status' => 'DITERIMA',
        ]);

        $this->actingAs($technician)
            ->from(route('service.show', $service))
            ->post(route('service.progress.store', $service), [
                'status' => 'DIAGNOSA',
                'catatan' => 'Pemeriksaan belum dicatat.',
            ])
            ->assertSessionHasErrors('diagnosa');

        $this->assertDatabaseHas('service', ['id' => $service->id, 'status' => 'DITERIMA']);
        $this->assertDatabaseCount('service_progress', 0);
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'TEST_'.uniqid()]);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission]));
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
