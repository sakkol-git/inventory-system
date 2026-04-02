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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Core Info
            $table->string('name');

            // Authentication Info
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();

            // Contact Info
            $table->string('phone', 20)->nullable();

            // Role Info
            $table->enum('role', ['admin', 'lab_manager', 'student'])->default('student');

            // Profile Image
            $table->string('profile_image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
