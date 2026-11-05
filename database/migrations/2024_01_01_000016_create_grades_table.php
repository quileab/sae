<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('class_session_id')->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('grade')->default(0);
            $table->boolean('approved')->default(false);
            $table->unsignedTinyInteger('attendance')->default(0);
            $table->string('type', 20)->default('regular');
            $table->unsignedBigInteger('recovered_grade_id')->nullable();
            $table->text('comments', 255)->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'user_id'], 'unique_class_session_user');
            $table->foreign('recovered_grade_id')->references('id')->on('grades')->nullOnDelete();
            $table->index(['user_id', 'class_session_id']);
            $table->index('class_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
