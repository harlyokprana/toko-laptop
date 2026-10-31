<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_mutasi', function (Blueprint $table) {
            $table->id();

            $table->dateTime('tanggal');

            $table->enum('jenis', [
                'PENDAPATAN',
                'PENGELUARAN',
                'PEMBAYARAN',
                'PENAMBAHAN_KAS',
                'PENGAMBILAN_KAS',
                'ADJUSTMENT'
            ]);

            $table->string('referensi_type')->nullable();

            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->text('keterangan')->nullable();

            $table->decimal('debit', 15, 2)->default(0);

            $table->decimal('kredit', 15, 2)->default(0);

            $table->decimal('saldo', 15, 2)->default(0);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('tanggal');
            $table->index('jenis');
            $table->index(['referensi_type', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_mutasi');
    }
};