<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laptop', function (Blueprint $table) {
            $table->string('kondisi', 20)->default('Baru')->after('model_seri');
            $table->string('processor', 255)->nullable()->after('kondisi');
            $table->string('ram', 20)->nullable()->after('processor');
            $table->string('storage_jenis', 30)->nullable()->after('ram');
            $table->string('storage_kapasitas', 30)->nullable()->after('storage_jenis');
            $table->string('gpu', 255)->nullable()->after('storage_kapasitas');
            $table->string('ukuran_layar', 20)->nullable()->after('gpu');
            $table->string('resolusi_layar', 30)->nullable()->after('ukuran_layar');
        });
    }

    public function down(): void
    {
        Schema::table('laptop', function (Blueprint $table) {
            $table->dropColumn([
                'kondisi', 'processor', 'ram', 'storage_jenis',
                'storage_kapasitas', 'gpu', 'ukuran_layar', 'resolusi_layar',
            ]);
        });
    }
};