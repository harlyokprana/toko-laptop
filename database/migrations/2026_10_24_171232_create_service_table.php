<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service', function (Blueprint $table) {
            $table->id();

            $table->string('no_service', 50)->unique();

            $table->foreignId('pelanggan_id')
                ->constrained('pelanggan')
                ->restrictOnDelete();

            $table->dateTime('tanggal_masuk');
            $table->dateTime('tanggal_selesai')->nullable();

            $table->string('jenis_perangkat', 100);
            $table->string('merek', 100)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 100)->nullable();

            $table->text('keluhan')->nullable();
            $table->text('diagnosa')->nullable();

            $table->foreignId('teknisi_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', [
                'DITERIMA',
                'DIAGNOSA',
                'MENUNGGU_PERSETUJUAN',
                'MENUNGGU_SPAREPART',
                'DALAM_PERBAIKAN',
                'TESTING',
                'SELESAI',
                'SUDAH_DIAMBIL',
                'BATAL'
            ])->default('DITERIMA');

            $table->decimal('jasa', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('dibayar', 15, 2)->default(0);

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index('no_service');
            $table->index('tanggal_masuk');
            $table->index('status');
            $table->index('teknisi_id');
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service');
    }
};