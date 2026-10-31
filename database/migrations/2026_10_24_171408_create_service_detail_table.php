<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')
                ->constrained('service')
                ->cascadeOnDelete();

            $table->foreignId('produk_id')
                ->constrained('produk')
                ->restrictOnDelete();

            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('hpp', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();

            $table->index('service_id');
            $table->index('produk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_detail');
    }
};