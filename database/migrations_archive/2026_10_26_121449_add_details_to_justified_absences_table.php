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
        Schema::table('justified_absences', function (Blueprint $table) {
            $table->string('article')->nullable()->after('end_date');
            $table->string('custom_article')->nullable()->after('article');
            $table->string('status')->default('approved')->after('description'); // pending, approved, rejected
            $table->timestamp('certificate_received_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('justified_absences', function (Blueprint $table) {
            $table->dropColumn(['article', 'custom_article', 'status', 'certificate_received_at']);
        });
    }
};
