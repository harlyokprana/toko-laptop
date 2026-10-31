<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('kode_pelanggan', 30)->unique();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->text('alamat')->nullable();

            $table->enum('status', [
                'AKTIF',
                'NONAKTIF'
            ])->default('AKTIF');

            $table->timestamps();

            $table->index('nama');
            $table->index('telepon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};