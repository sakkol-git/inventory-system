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
        Schema::create('plant_samples', function (Blueprint $table) {
            $table->id();

            // Core info
            $table->string('sample_name');
            $table->string('sample_code');
            $table->index('sample_code'); // uniqueness enforced at app level (soft-delete aware)

            // Relationships
            $table->foreignId('plant_species_id')
                ->constrained('plant_species')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('plant_variety_id')
                ->nullable()
                ->constrained('plant_varieties')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Ownership Info
            $table->string('owner_name')->nullable();
            $table->string('department')->nullable();

            // Origin Info
            $table->string('origin_location')->nullable();

            // Laboratory Info
            $table->date('brought_at')->nullable();
            $table->enum('lab_location', ['lab_a', 'lab_b', 'lab_c'])->nullable();

            // Status Info
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');

            // Quantity
            $table->integer('quantity')->default(0);

            // Additional Info
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Performance Optimization
            $table->index(['plant_species_id', 'plant_variety_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_samples');
    }
};
