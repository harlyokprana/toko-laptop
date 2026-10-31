<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasir_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->text('opening_note')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->text('closing_note')->nullable();
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasir_shifts');
    }
};
