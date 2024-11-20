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
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->date('date');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null'); // Profesor que dicta la clase.
            $table->unsignedSmallInteger('class_number'); // Número de clase.
            $table->string('unit', 50); // Unidad temática.
            $table->string('type', 25); // Tipo de clase (teoría, práctica, etc.).
            $table->text('content'); // Temas tratados.
            $table->text('activities')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
