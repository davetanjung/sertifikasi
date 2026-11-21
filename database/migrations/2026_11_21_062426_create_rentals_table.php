<?php

use App\Utilities\Constants\Const_Status;
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
        Schema::create('rentals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate(); // restrict karena financial record
            $table->foreignUuid('garage_id')->constrained()->restrictOnDelete()->restrictOnUpdate()->index();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('code');
            $table->unique(['code', 'start_date']);
            $table->string('status')->default(Const_Status::NEW);
            $table->string('rental_type'); // new atau extension.
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->unsignedSmallInteger('days'); // gamungkin negative, pakai unsigned
            $table->decimal('daily_price', 15, 2);
            $table->decimal('rent_amount', 15, 2);
            $table->string('payment_proof_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
