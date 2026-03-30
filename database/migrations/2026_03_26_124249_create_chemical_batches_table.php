<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemical_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chemical_id')
                ->constrained('chemicals')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('batch_number', 100);
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 20)->default('ml');
            $table->date('expiry_date')->nullable();

            // Supplier tracking
            $table->string('supplier_name')->nullable();
            $table->string('supplier_contact')->nullable();

            $table->date('received_at')->nullable();
            $table->decimal('cost_per_unit', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['chemical_id', 'batch_number']);
            $table->index(['chemical_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_batches');
    }
};
