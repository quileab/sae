<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
        });

        Schema::table('careers', function (Blueprint $table) {
            $table->string('level', 20)->default('tertiary')->after('resolution'); // 'tertiary', 'secondary'
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });

        Schema::table('careers', function (Blueprint $table) {
            $table->dropIndex(['careers_level_index']);
            $table->dropColumn('level');
        });
    }
};
