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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Who performed the action
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();

            // Polymorphic relation to plant_species, plant_varieties, plant_samples, plant_stocks
            $table->morphs('transactionable');

            // Action type
            $table->enum('action', ['added', 'updated', 'consumed', 'borrowed', 'returned', 'harvested', 'disposed']);

            // Additional details about the transaction
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('note')->nullable();

            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
