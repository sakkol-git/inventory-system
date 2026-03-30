<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemical_usage_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chemical_id')
                ->constrained('chemicals')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('chemical_batch_id')
                ->nullable()
                ->constrained('chemical_batches')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Usage details
            $table->decimal('quantity_used', 10, 2);
            $table->string('unit', 20)->default('ml');
            $table->string('purpose');
            $table->string('experiment_name')->nullable();
            $table->dateTime('used_at');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['chemical_id', 'used_at']);
            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_usage_logs');
    }
};
