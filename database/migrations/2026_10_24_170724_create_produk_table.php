<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk', function (Blueprint $table) {
            $table->id();

            $table->string('kode_produk', 50)->unique();
            $table->string('nama_produk');

            $table->foreignId('kategori_id')
                ->constrained('kategori')
                ->restrictOnDelete();

            $table->string('merek')->nullable();
            $table->string('tipe')->nullable();
            $table->string('satuan', 30)->default('PCS');

            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual', 15, 2)->default(0);

            $table->integer('stok')->default(0);
            $table->integer('stok_minimum')->default(0);

            $table->enum('status', [
                'AKTIF',
                'NONAKTIF'
            ])->default('AKTIF');

            $table->timestamps();

            $table->index('nama_produk');
            $table->index('merek');
            $table->index('kategori_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};