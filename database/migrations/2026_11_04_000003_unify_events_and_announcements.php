<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clean up temporary announcements tables from previous migration
        Schema::dropIfExists('announcement_user');
        Schema::dropIfExists('announcements');

        // 2. Enhance the existing events table
        Schema::table('events', function (Blueprint $table) {
            $table->string('type')->default('event')->after('id'); // 'event', 'announcement', 'exam_board'
            $table->dateTime('start')->nullable()->change();
            $table->dateTime('end')->nullable()->change();
            $table->string('priority')->default('normal')->after('color'); // 'normal', 'important', 'urgent'
            $table->foreignId('career_id')->nullable()->after('subject_id')->constrained('careers')->nullOnDelete();
            $table->json('target_roles')->nullable()->after('career_id'); // ['student', 'teacher', 'tutor']
            $table->boolean('requires_acknowledgment')->default(false)->after('target_roles');
            $table->boolean('show_in_calendar')->default(true)->after('requires_acknowledgment');
            $table->timestamp('published_at')->useCurrent()->after('show_in_calendar');
            $table->timestamp('expires_at')->nullable()->after('published_at');

            $table->index(['type', 'published_at']);
            $table->index(['show_in_calendar', 'start']);
        });

        // 3. Create the event_user pivot table for read and acknowledgment auditing
        Schema::create('event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['events_type_published_at_index']);
            $table->dropIndex(['events_show_in_calendar_start_index']);
            $table->dropForeign(['career_id']);
            $table->dropColumn([
                'type',
                'priority',
                'career_id',
                'target_roles',
                'requires_acknowledgment',
                'show_in_calendar',
                'published_at',
                'expires_at',
            ]);
        });
    }
};
