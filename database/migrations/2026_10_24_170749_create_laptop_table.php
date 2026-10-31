<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laptop', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produk_id')
                ->nullable()
                ->constrained('produk')
                ->nullOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('supplier')
                ->nullOnDelete();

            $table->string('kode_laptop', 50)->unique();
            $table->string('serial_number', 100)->unique();

            $table->date('tanggal_perolehan')->nullable();

            $table->string('merek')->nullable();
            $table->string('model_seri')->nullable();
            $table->text('spesifikasi')->nullable();

            $table->decimal('hpp', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);
            $table->decimal('biaya_tambahan', 15, 2)->default(0);

            $table->enum('status', [
                'READY',
                'SOLD',
                'REPAIR',
                'REJECT',
                'DISPLAY',
                'DIPAKAI_INTERNAL'
            ])->default('READY');

            $table->unsignedBigInteger('penjualan_id')->nullable();

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index('serial_number');
            $table->index('status');
            $table->index('tanggal_perolehan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laptop');
    }
};