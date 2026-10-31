<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendapatan', function (Blueprint $table) {
            $table->id();

            $table->dateTime('tanggal');

            $table->string('kode_pendapatan', 50)->nullable();

            $table->enum('jenis_pendapatan', [
                'SERVICE',
                'PENJUALAN_AKSESORIS',
                'PRINTING',
                'SEWA',
                'PENJUALAN_LAPTOP',
                'LAINNYA'
            ]);

            $table->string('sumber')->nullable();

            $table->string('referensi_type')->nullable();

            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->decimal('nominal', 15, 2);

            $table->text('keterangan')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('tanggal');
            $table->index('jenis_pendapatan');
            $table->index(['referensi_type', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendapatan');
    }
};