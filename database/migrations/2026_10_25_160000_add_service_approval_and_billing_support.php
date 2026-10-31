<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service', function (Blueprint $table) {
            $table->decimal('diskon', 15, 2)->default(0)->after('jasa');
            $table->decimal('pajak_persen', 5, 2)->default(0)->after('diskon');
            $table->decimal('pajak', 15, 2)->default(0)->after('pajak_persen');
            $table->decimal('biaya_tambahan', 15, 2)->default(0)->after('pajak');
        });

        Schema::create('service_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('service')->cascadeOnDelete();
            $table->string('status_sebelum', 40)->nullable();
            $table->unsignedTinyInteger('progress_sebelum')->default(0);
            $table->decimal('jasa_sebelum', 15, 2)->default(0);
            $table->decimal('diskon_sebelum', 15, 2)->default(0);
            $table->decimal('pajak_persen_sebelum', 5, 2)->default(0);
            $table->decimal('pajak_sebelum', 15, 2)->default(0);
            $table->decimal('biaya_tambahan_sebelum', 15, 2)->default(0);
            $table->decimal('total_sebelum', 15, 2)->default(0);
            $table->decimal('estimasi', 15, 2);
            $table->decimal('nominal', 15, 2)->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'status']);
        });

        Schema::table('service_detail', function (Blueprint $table) {
            $table->foreignId('approval_id')->nullable()->constrained('service_approvals')->nullOnDelete();
            $table->timestamp('stock_deducted_at')->nullable();
        });

        // Detail lama dibuat bersamaan dengan pemotongan stok oleh alur service sebelumnya.
        DB::table('service_detail')
            ->whereNull('approval_id')
            ->whereNull('stock_deducted_at')
            ->update(['stock_deducted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('service_detail', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_id');
            $table->dropColumn('stock_deducted_at');
        });

        Schema::dropIfExists('service_approvals');

        Schema::table('service', function (Blueprint $table) {
            $table->dropColumn(['diskon', 'pajak_persen', 'pajak', 'biaya_tambahan']);
        });
    }
};
