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
        Schema::create('chemicals', function (Blueprint $table) {
            $table->id();

            // Core Info
            $table->string('common_name');
            $table->string('chemical_code')->nullable();
            $table->index('chemical_code');
            $table->enum('category', ['acid', 'base', 'solvent', 'oxidizer', 'reducer', 'other'])->default('other');

            // Quantity & Storage Info
            $table->unsignedInteger('quantity')->default(0);
            $table->string('storage_location')->nullable();

            // Expiry Info
            $table->date('expiry_date')->nullable();

            // Safety Info
            $table->enum('danger_level', ['low', 'medium', 'high'])->default('low');
            $table->text('safety_measures')->nullable();

            // Additional Info
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Performance Optimization
            $table->index(['category', 'expiry_date', 'danger_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chemicals');
    }
};
