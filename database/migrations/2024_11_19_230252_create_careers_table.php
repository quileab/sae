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
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Nombre de la carrera.
            $table->string('resolution', 40)->nullable(); // Resolución legal.
            $table->boolean('allow_enrollments')->default(true); // Inscripciones abiertas.
            $table->boolean('allow_evaluations')->default(true); // Evaluaciones activas.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('careers');
    }
};
