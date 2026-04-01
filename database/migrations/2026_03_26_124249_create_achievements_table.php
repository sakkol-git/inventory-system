<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('achievement_name');
            $table->text('description')->nullable();
            $table->string('criteria_type');    // e.g. samples_count, chemicals_count, borrows_count
            $table->unsignedInteger('criteria_value')->default(1); // threshold to earn
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('achievement_id')
                ->constrained('achievements')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->dateTime('earned_at');
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};
