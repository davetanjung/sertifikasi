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
        Schema::create('garages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 4)->unique(); // C001, C002, C999. Maks 4 digit
            $table->string('type');
            $table->decimal('daily_price', 15, 2);
            $table->decimal('price_per_kwh', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
