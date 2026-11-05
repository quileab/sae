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
        Schema::create('pre_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('cycle_id');
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('doc_type', 10)->default('DNI');
            $table->string('doc_number', 20);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('submitted');
            $table->string('source', 50)->default('preinsc');
            $table->boolean('migrated_from_sheets')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'doc_number', 'career_id'], 'pre_enroll_cycle_doc_career_unique');
            $table->index(['cycle_id', 'career_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollments');
    }
};
