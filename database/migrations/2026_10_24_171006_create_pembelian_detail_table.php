<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pembelian_id')
                ->constrained('pembelian')
                ->cascadeOnDelete();

            $table->foreignId('produk_id')
                ->constrained('produk')
                ->restrictOnDelete();

            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();

            $table->index('pembelian_id');
            $table->index('produk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian_detail');
    }
};