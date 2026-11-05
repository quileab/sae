<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('blood_type', 10)->nullable(); // A+, O-, etc.
            $table->string('healthcare_provider')->nullable(); // Obra Social / Prepaga
            $table->string('healthcare_number')->nullable(); // Nro de Afiliado
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_hospital')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('doctor_phone')->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('current_medication')->nullable();
            $table->text('dietary_restrictions')->nullable();
            $table->boolean('has_physical_fitness_certificate')->default(false);
            $table->date('physical_fitness_expires_at')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
