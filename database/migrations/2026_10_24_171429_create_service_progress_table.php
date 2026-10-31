<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')
                ->constrained('service')
                ->cascadeOnDelete();

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
            ]);

            $table->unsignedTinyInteger('progress_percent')->default(0);

            $table->text('catatan')->nullable();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index('service_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_progress');
    }
};