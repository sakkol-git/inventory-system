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
        Schema::create('plant_stocks', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('plant_species_id')->constrained('plant_species')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('plant_variety_id')
                ->nullable()
                ->constrained('plant_varieties')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('plant_sample_id')
                ->nullable()
                ->constrained('plant_samples')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // core info
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);

            // Status Info
            $table->enum('status', ['available', 'reserved', 'out_of_stock'])->default('available');

            $table->softDeletes();
            $table->timestamps();

            // Performance Optimization
            $table->index(['plant_species_id', 'plant_variety_id', 'plant_sample_id', 'status'], 'plant_stocks_sample_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_stocks');
    }
};
