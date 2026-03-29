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
        Schema::create('borrow_records', function (Blueprint $table) {
            $table->id();

            // Who borrowed
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Polymorphic: what was borrowed (Equipment, Chemical, PlantSample, etc.)
            $table->morphs('borrowable');

            // Borrow details
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20)->default('pending');

            // Timestamps for borrow lifecycle
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('returned_at')->nullable();

            // Additional Info
            $table->text('notes')->nullable();

            $table->timestamps();

            // Performance Optimization
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrow_records');
    }
};
