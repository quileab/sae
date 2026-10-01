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
        Schema::create('pre_enrollment_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('cycle_id');
            $table->foreignId('career_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('file_path', 250);
            $table->string('original_name', 150);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_materials');
    }
};
