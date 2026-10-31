<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian', function (Blueprint $table) {
            $table->id();

            $table->string('no_invoice', 50)->unique();
            $table->date('tanggal');

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('supplier')
                ->nullOnDelete();

            $table->enum('jenis_pembelian', [
                'PEMBELIAN',
                'PEMBELIAN_INVENTARIS',
                'PENAMBAHAN_KAS',
                'LAINNYA'
            ])->default('PEMBELIAN');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('ongkir', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('dibayar', 15, 2)->default(0);

            $table->enum('status', [
                'DRAFT',
                'SELESAI',
                'SEBAGIAN',
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
            $table->index('jenis_pembelian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian');
    }
};