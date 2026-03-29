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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();

            // Core Info
            $table->string('equipment_name');
            $table->string('equipment_code')->nullable();
            $table->index('equipment_code');
            $table->enum('category', ['microscope', 'centrifuge', 'incubator', 'spectrophotometer', 'other'])->default('other');
            $table->enum('status', ['available', 'borrowed', 'in_use', 'under_maintenance'])->default('available');
            $table->enum('condition', ['good', 'normal', 'broken'])->default('good');

            // Location Info
            $table->string('location')->nullable();

            // Purchase Info
            $table->string('manufacturer')->nullable();
            $table->string('model_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->index('serial_number');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();

            // Additional Info
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Performance Optimization
            $table->index(['category', 'status', 'condition']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
