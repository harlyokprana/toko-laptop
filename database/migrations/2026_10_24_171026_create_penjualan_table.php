<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();

            $table->string('no_invoice', 50)->unique();
            $table->date('tanggal');

            $table->foreignId('pelanggan_id')
                ->nullable()
                ->constrained('pelanggan')
                ->nullOnDelete();

            $table->enum('jenis_penjualan', [
                'LAPTOP',
                'AKSESORIS',
                'SPAREPART',
                'LAINNYA'
            ])->default('LAINNYA');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('biaya_tambahan', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('dibayar', 15, 2)->default(0);
            $table->decimal('kembalian', 15, 2)->default(0);

            $table->enum('status', [
                'DRAFT',
                'SELESAI',
                'SEBAGIAN',
                'PIUTANG',
                'BATAL'
            ])->default('DRAFT');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index('tanggal');
            $table->index('status');
            $table->index('jenis_penjualan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan');
    }
};