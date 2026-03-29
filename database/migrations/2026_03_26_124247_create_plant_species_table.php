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
        Schema::create('plant_species', function (Blueprint $table) {
            $table->id();

            // core info
            $table->string('common_name');
            $table->string('khmer_name')->nullable();
            $table->string('scientific_name');
            $table->index('scientific_name'); // uniqueness enforced at app level (soft-delete aware)

            // Classification Info
            $table->string('family')->nullable();
            $table->enum('growth_type', ['annual', 'perennial', 'biennial'])->nullable();
            $table->string('native_region')->nullable();
            $table->string('propagation_method')->nullable();

            // Additional Info
            $table->string('description')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_species');
    }
};
