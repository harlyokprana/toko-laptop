<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengeluaran', function (Blueprint $table) {
            $table->id();

            $table->dateTime('tanggal');

            $table->string('kode_beban', 50)->nullable();

            $table->string('kategori_beban');

            $table->text('keterangan')->nullable();

            $table->decimal('nominal', 15, 2);

            $table->enum('metode_bayar', [
                'CASH',
                'TRANSFER',
                'QRIS',
                'DEBIT',
                'KREDIT'
            ])->nullable();

            $table->string('referensi_type')->nullable();

            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('tanggal');
            $table->index('kategori_beban');
            $table->index(['referensi_type', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluaran');
    }
};