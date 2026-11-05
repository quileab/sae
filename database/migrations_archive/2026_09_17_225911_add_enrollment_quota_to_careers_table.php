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
        Schema::table('careers', function (Blueprint $table) {
            $table->unsignedInteger('enrollment_quota_2027')->nullable()->after('allow_evaluations');
            $table->boolean('enrollment_quota_enabled')->default(false)->after('enrollment_quota_2027');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->dropColumn(['enrollment_quota_2027', 'enrollment_quota_enabled']);
        });
    }
};
