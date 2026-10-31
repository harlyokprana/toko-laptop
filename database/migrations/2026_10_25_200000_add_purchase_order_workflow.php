<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelian', function (Blueprint $table): void {
            $table->date('estimasi_tiba')->nullable();
            $table->string('mata_uang', 10)->default('IDR');
            $table->string('termin_pembayaran', 30)->nullable();
            $table->decimal('pajak_persen', 5, 2)->default(0);
            $table->decimal('pajak', 15, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['DRAFT', 'MENUNGGU_APPROVAL', 'DISETUJUI', 'DALAM_PROSES', 'SEBAGIAN_DITERIMA', 'SELESAI', 'SEBAGIAN', 'BATAL'])->default('DRAFT')->change();
        });

        Schema::table('pembelian_detail', function (Blueprint $table): void {
            $table->decimal('qty_diterima', 15, 2)->default(0)->after('qty');
        });

        Schema::create('pembelian_penerimaan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pembelian_id')->constrained('pembelian')->restrictOnDelete();
            $table->string('nomor_penerimaan', 50)->unique();
            $table->dateTime('tanggal_terima');
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['pembelian_id', 'tanggal_terima']);
        });

        Schema::create('pembelian_penerimaan_detail', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('pembelian_penerimaan')->cascadeOnDelete();
            $table->foreignId('pembelian_detail_id')->constrained('pembelian_detail')->restrictOnDelete();
            $table->decimal('qty', 15, 2);
            $table->string('kondisi', 40)->default('BAIK');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index('pembelian_detail_id');
        });

        Schema::create('pembelian_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pembelian_id')->constrained('pembelian')->cascadeOnDelete();
            $table->string('event', 50);
            $table->string('status_sebelum', 40)->nullable();
            $table->string('status_sesudah', 40)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['pembelian_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian_history');
        Schema::dropIfExists('pembelian_penerimaan_detail');
        Schema::dropIfExists('pembelian_penerimaan');
        Schema::table('pembelian_detail', function (Blueprint $table): void {
            $table->dropColumn('qty_diterima');
        });
        Schema::table('pembelian', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['estimasi_tiba', 'mata_uang', 'termin_pembayaran', 'pajak_persen', 'pajak', 'approved_at']);
            $table->enum('status', ['DRAFT', 'SELESAI', 'SEBAGIAN', 'BATAL'])->default('DRAFT')->change();
        });
    }
};
