<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->enum('status', ['active', 'completed', 'withdrawn'])->default('active');
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->date('final_grade_date')->nullable();
            $table->foreignId('modalities_id')->nullable()->constrained('modalities')->onUpdate('cascade')->onDelete('set null');
            $table->text('observations')->nullable();
            $table->integer('attendance_percentage')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'subject_id'], 'enrollments_user_subject_idx');
            $table->index(['subject_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
