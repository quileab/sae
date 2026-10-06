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
        Schema::table('online_evaluations', function (Blueprint $table) {
            $table->float('passing_score')->nullable()->default(6)->after('duration_minutes');
            $table->integer('max_attempts')->default(1)->after('passing_score');
            $table->string('grading_method')->default('highest')->after('max_attempts');
            $table->boolean('shuffle_questions')->default(false)->after('grading_method');
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->integer('attempt_number')->default(1)->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_evaluations', function (Blueprint $table) {
            $table->dropColumn(['passing_score', 'max_attempts', 'grading_method', 'shuffle_questions']);
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->dropColumn('attempt_number');
        });
    }
};
