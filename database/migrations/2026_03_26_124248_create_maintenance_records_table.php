<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('equipment_id')
                ->constrained('equipment')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Maintenance info
            $table->string('maintenance_type', 20)->default('preventive');
            $table->text('description');
            $table->string('technician_name')->nullable();
            $table->string('technician_contact')->nullable();
            $table->decimal('cost', 10, 2)->nullable();

            // Dates
            $table->date('started_at');
            $table->date('completed_at')->nullable();
            $table->date('next_service_date')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'maintenance_type']);
            $table->index('next_service_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
