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
        if (! Schema::hasIndex('message_user', 'message_user_user_read_idx')) {
            Schema::table('message_user', function (Blueprint $table) {
                $table->index(['user_id', 'read_at'], 'message_user_user_read_idx');
            });
        }

        if (! Schema::hasIndex('messages', 'messages_sender_subject_date_idx')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['sender_id', 'subject_id', 'created_at'], 'messages_sender_subject_date_idx');
            });
        }

        if (! Schema::hasIndex('userpayments', 'userpayments_user_date_idx')) {
            Schema::table('userpayments', function (Blueprint $table) {
                $table->index(['user_id', 'date'], 'userpayments_user_date_idx');
            });
        }

        if (! Schema::hasIndex('enrollments', 'enrollments_user_subject_idx')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->index(['user_id', 'subject_id'], 'enrollments_user_subject_idx');
            });
        }

        if (! Schema::hasIndex('class_sessions', 'class_sessions_subject_date_idx')) {
            Schema::table('class_sessions', function (Blueprint $table) {
                $table->index(['subject_id', 'date'], 'class_sessions_subject_date_idx');
            });
        }

        if (! Schema::hasIndex('justified_absences', 'justified_absences_user_dates_idx')) {
            Schema::table('justified_absences', function (Blueprint $table) {
                $table->index(['user_id', 'start_date', 'end_date'], 'justified_absences_user_dates_idx');
            });
        }

        if (! Schema::hasIndex('daily_attendances', 'daily_attendances_career_date_idx')) {
            Schema::table('daily_attendances', function (Blueprint $table) {
                $table->index(['career_id', 'date'], 'daily_attendances_career_date_idx');
            });
        }

        if (! Schema::hasIndex('career_user', 'career_user_idx')) {
            Schema::table('career_user', function (Blueprint $table) {
                $table->index(['user_id', 'career_id'], 'career_user_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_user', function (Blueprint $table) {
            $table->dropIndex('message_user_user_read_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_sender_subject_date_idx');
        });

        Schema::table('userpayments', function (Blueprint $table) {
            $table->dropIndex('userpayments_user_date_idx');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_user_subject_idx');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('class_sessions_subject_date_idx');
        });

        Schema::table('justified_absences', function (Blueprint $table) {
            $table->dropIndex('justified_absences_user_dates_idx');
        });

        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->dropIndex('daily_attendances_career_date_idx');
        });

        Schema::table('career_user', function (Blueprint $table) {
            $table->dropIndex('career_user_idx');
        });
    }
};
