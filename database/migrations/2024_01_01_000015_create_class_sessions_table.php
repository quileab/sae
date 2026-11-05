<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->date('date');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->unsignedSmallInteger('class_number');
            $table->string('unit', 50);
            $table->string('type', 25);
            $table->text('content');
            $table->text('activities')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'date'], 'class_sessions_subject_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
