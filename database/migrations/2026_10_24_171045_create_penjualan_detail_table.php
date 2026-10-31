<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('penjualan_id')
                ->constrained('penjualan')
                ->cascadeOnDelete();

            $table->foreignId('produk_id')
                ->constrained('produk')
                ->restrictOnDelete();

            $table->foreignId('laptop_id')
                ->nullable()
                ->constrained('laptop')
                ->nullOnDelete();

            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);

            // HPP pada saat transaksi
            $table->decimal('hpp', 15, 2)->default(0);

            // Laba kotor item
            $table->decimal('laba', 15, 2)->default(0);

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();

            $table->index('penjualan_id');
            $table->index('produk_id');
            $table->index('laptop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_detail');
    }
};