<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_attempt_id')->constrained('evaluation_attempts')->cascadeOnDelete();
            $table->string('block_id', 64);
            $table->string('rubric_version', 32)->nullable();
            $table->string('rubric_hash', 64)->nullable();
            $table->string('agent', 64)->default('primary');
            $table->json('criteria')->nullable();
            $table->decimal('total_points', 5, 2)->nullable();
            $table->decimal('max_points', 5, 2)->nullable();
            $table->json('flags')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->enum('state', ['suggested', 'accepted', 'rejected', 'modified'])->default('suggested');
            $table->unsignedInteger('revision')->default(1);
            $table->json('history')->nullable();
            $table->text('disagreement_reason')->nullable();
            $table->decimal('final_points', 5, 2)->nullable();
            $table->longText('raw_response')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['evaluation_attempt_id', 'block_id']);
            $table->index(['state', 'agent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_suggestions');
    }
};
