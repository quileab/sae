<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justified_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('article')->nullable();
            $table->string('custom_article')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('approved');
            $table->timestamp('certificate_received_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date'], 'justified_absences_user_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justified_absences');
    }
};
