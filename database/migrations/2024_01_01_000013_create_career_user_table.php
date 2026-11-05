<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['career_id', 'user_id']);
            $table->index(['user_id', 'career_id'], 'career_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_user');
    }
};
