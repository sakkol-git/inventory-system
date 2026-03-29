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
        Schema::create('plant_varieties', function (Blueprint $table) {
            $table->id();

            // Relation to plant_species
            $table->foreignId('plant_species_id')
                ->constrained('plant_species')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Core info
            $table->string('name');
            $table->string('variety_code');
            $table->index('variety_code'); // uniqueness enforced at app level (soft-delete aware)

            //            // Ownership Info
            //            $table->string('owner_name')->nullable();
            //            $table->string('department')->nullable();
            //
            //            // Origin Info
            //            $table->string('origin_location')->nullable();
            //
            //            // Laboratory Info
            //            $table->date('brought_at')->nullable();
            //
            //            // Status Info
            //            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');

            // Additional Info
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Performance Optimization
            $table->index(['plant_species_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_varieties');
    }
};
