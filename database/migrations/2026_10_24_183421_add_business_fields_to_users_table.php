<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('users');

        if (! in_array('phone', $columns, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable()->after('password');
            });
        }

        if (! in_array('role_id', $columns, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('phone')
                    ->constrained('roles')
                    ->nullOnDelete();
            });
        }

        if (! in_array('status', $columns, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status')->default('AKTIF')->after('role_id');
            });
        }

        if (! in_array('last_login_at', $columns, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        // These fields are also part of the original users migration. Leaving
        // them in place avoids deleting core account data during a rollback.
    }
};
