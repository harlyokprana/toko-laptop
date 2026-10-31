<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->string('periode', 7);
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('potongan', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('tanggal_pembayaran')->nullable();
            $table->enum('metode_pembayaran', ['CASH', 'TRANSFER', 'QRIS', 'DEBIT', 'KREDIT'])->nullable();
            $table->enum('status', ['DRAFT', 'PAID', 'CANCELLED'])->default('DRAFT');
            $table->foreignId('pengeluaran_id')->nullable()->constrained('pengeluaran')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'periode']);
            $table->index(['periode', 'status']);
        });

        $newPermissions = [
            'create_expense' => 'Create expenses',
            'edit_expense' => 'Edit expenses',
            'delete_expense' => 'Delete expenses',
            'view_payroll' => 'View payroll',
            'manage_payroll' => 'Manage payroll',
        ];
        foreach ($newPermissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['description' => $description]);
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys($newPermissions))->pluck('id', 'name');
        $roles = DB::table('roles')->whereIn('name', ['PIMPINAN', 'MANAGER'])->pluck('id', 'name');
        foreach ($roles as $roleName => $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
        $permissionIds = DB::table('permissions')->whereIn('name', [
            'create_expense', 'edit_expense', 'delete_expense', 'view_payroll', 'manage_payroll',
        ])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
