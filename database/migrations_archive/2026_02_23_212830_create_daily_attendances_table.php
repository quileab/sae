<?php

use App\Models\Career;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Career::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'late', 'early_leave', 'absent', 'half_absent'])->default('present');
            $table->decimal('absence_value', 3, 2)->default(0);
            $table->string('note', 100)->nullable();
            $table->timestamps();

            $table->unique(['career_id', 'user_id', 'date'], 'unique_career_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
    }
};
