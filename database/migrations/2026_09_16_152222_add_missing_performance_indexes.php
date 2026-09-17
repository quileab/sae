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
        Schema::table('grades', function (Blueprint $table) {
            $table->index(['user_id', 'class_session_id']);
            $table->index('class_session_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['subject_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->index(['user_id', 'career_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index(['role', 'enabled']);
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->index(['configs_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'class_session_id']);
            $table->dropIndex(['class_session_id']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'career_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['role', 'enabled']);
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropIndex(['configs_id', 'user_id']);
        });
    }
};
