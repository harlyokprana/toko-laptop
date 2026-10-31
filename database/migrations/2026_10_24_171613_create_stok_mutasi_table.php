<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_mutasi', function (Blueprint $table) {
            $table->id();

            $table->dateTime('tanggal');

            $table->foreignId('produk_id')
                ->constrained('produk')
                ->restrictOnDelete();

            $table->enum('jenis_mutasi', [
                'PEMBELIAN',
                'PENJUALAN',
                'SERVICE',
                'RETUR_PEMBELIAN',
                'RETUR_PENJUALAN',
                'ADJUSTMENT'
            ]);

            /*
             * Polymorphic reference:
             * contoh:
             * pembelian_id
             * penjualan_id
             * service_id
             */
            $table->string('referensi_type')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->decimal('qty_masuk', 15, 2)->default(0);
            $table->decimal('qty_keluar', 15, 2)->default(0);

            // Saldo stok setelah mutasi
            $table->decimal('stok_saldo', 15, 2)->default(0);

            // HPP per unit saat mutasi
            $table->decimal('hpp', 15, 2)->default(0);

            $table->text('keterangan')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index('tanggal');
            $table->index('produk_id');
            $table->index('jenis_mutasi');
            $table->index(['referensi_type', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_mutasi');
    }
};