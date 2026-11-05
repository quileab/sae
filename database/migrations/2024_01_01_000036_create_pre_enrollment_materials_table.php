<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_enrollment_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('cycle_id');
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->timestamps();

            $table->index(['cycle_id', 'career_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_materials');
    }
};
