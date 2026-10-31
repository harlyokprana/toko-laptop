<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();

            $table->dateTime('tanggal');

            $table->string('referensi_type');

            $table->unsignedBigInteger('referensi_id');

            $table->enum('metode', [
                'CASH',
                'TRANSFER',
                'QRIS',
                'DEBIT',
                'KREDIT',
                'PIUTANG'
            ]);

            $table->decimal('nominal', 15, 2);

            $table->enum('status', [
                'PENDING',
                'LUNAS',
                'BATAL'
            ])->default('LUNAS');

            $table->text('keterangan')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index('tanggal');
            $table->index('referensi_type');
            $table->index('referensi_id');
            $table->index(['referensi_type', 'referensi_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};