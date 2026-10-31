<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laptop', function (Blueprint $table) {
            $table->renameColumn('kode_laptop', 'sku');
        });
    }

    public function down(): void
    {
        Schema::table('laptop', function (Blueprint $table) {
            $table->renameColumn('sku', 'kode_laptop');
        });
    }
};