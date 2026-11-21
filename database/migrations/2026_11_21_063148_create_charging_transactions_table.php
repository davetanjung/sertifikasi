<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('charging_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rental_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->decimal('current_capacity_kw');
            $table->decimal('charged_kwh');
            $table->decimal('price_per_kwh', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('payment_proof_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charging_transactions');
    }
};
