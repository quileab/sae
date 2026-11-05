<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number')->default(1);
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->string('status')->default('in_progress');
            $table->decimal('score', 5, 2)->nullable();
            $table->json('answers')->nullable();
            $table->json('content_snapshot')->nullable();
            $table->json('rubric_snapshot')->nullable();
            $table->json('block_scores')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->index(['online_evaluation_id', 'user_id'], 'evaluation_attempts_evaluation_user_index');
            $table->index(['online_evaluation_id', 'status'], 'evaluation_attempts_evaluation_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_attempts');
    }
};
