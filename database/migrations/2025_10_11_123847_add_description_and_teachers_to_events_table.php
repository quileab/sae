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
        Schema::table('events', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->foreignId('presidente_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('vocal1_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('vocal2_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropForeign(['presidente_id']);
            $table->dropColumn('presidente_id');
            $table->dropForeign(['vocal1_id']);
            $table->dropColumn('vocal1_id');
            $table->dropForeign(['vocal2_id']);
            $table->dropColumn('vocal2_id');
        });
    }
};