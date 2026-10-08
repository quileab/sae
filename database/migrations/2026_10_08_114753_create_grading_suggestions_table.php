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
            $table->foreignId('evaluation_attempt_id')
                ->constrained('evaluation_attempts')
                ->cascadeOnDelete();
            $table->string('block_id');
            $table->unsignedSmallInteger('rubric_version')->default(1);
            $table->char('rubric_hash', 40)->nullable();
            $table->string('agent')->nullable();

            $table->json('criteria');
            $table->decimal('total_points', 6, 2);
            $table->decimal('max_points', 6, 2);
            $table->json('flags')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();

            $table->string('state')->default('proposed');
            $table->unsignedInteger('revision')->default(1);
            $table->json('history')->nullable();
            $table->string('disagreement_reason')->nullable();
            $table->decimal('final_points', 6, 2)->nullable();

            $table->longText('raw_response')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One live suggestion per block; re-importing overwrites in place and
            // pushes the previous payload into history.
            $table->unique(['evaluation_attempt_id', 'block_id'], 'grading_suggestions_attempt_block_unique');
            $table->index(['state', 'evaluation_attempt_id'], 'grading_suggestions_state_attempt_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_suggestions');
    }
};
