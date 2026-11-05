<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('firstname', 30);
            $table->string('lastname', 30);
            $table->enum('role', [
                'admin',
                'student',
                'teacher',
                'director',
                'administrative',
                'treasurer',
                'user',
                'preceptor',
            ])->default('user');
            $table->string('phone', 20)->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index(['role', 'enabled']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
