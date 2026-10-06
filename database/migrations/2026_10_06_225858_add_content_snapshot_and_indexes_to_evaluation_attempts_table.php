<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->json('content_snapshot')->nullable()->after('answers');
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->index(['online_evaluation_id', 'user_id'], 'evaluation_attempts_evaluation_user_index');
            $table->index(['online_evaluation_id', 'status'], 'evaluation_attempts_evaluation_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->dropIndex('evaluation_attempts_evaluation_user_index');
            $table->dropIndex('evaluation_attempts_evaluation_status_index');
            $table->dropColumn('content_snapshot');
        });
    }
};
