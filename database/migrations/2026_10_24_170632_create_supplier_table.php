<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();

            $table->string('kode_supplier', 30)->unique();
            $table->string('nama');
            $table->string('kontak')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();

            $table->enum('status', [
                'AKTIF',
                'NONAKTIF'
            ])->default('AKTIF');

            $table->timestamps();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier');
    }
};