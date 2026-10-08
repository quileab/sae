<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->json('rubric_snapshot')->nullable()->after('content_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            $table->dropColumn('rubric_snapshot');
        });
    }
};
