<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'late', 'early_leave', 'absent', 'half_absent'])->default('present');
            $table->decimal('absence_value', 3, 2)->default(0.00);
            $table->string('note', 100)->nullable();
            $table->timestamps();

            $table->unique(['career_id', 'user_id', 'date'], 'unique_career_user_date');
            $table->index(['career_id', 'date'], 'daily_attendances_career_date_idx');
            $table->index(['user_id', 'career_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
    }
};
