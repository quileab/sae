<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            // regular: asistencia diaria, evaluation: examen/parcial, practical_work: TP
            $table->string('type', 20)->default('regular')->after('attendance');
        });

        // Migración de datos existentes basada en comentarios
        DB::table('grades')->where('comments', 'like', 'ev%')->update(['type' => 'evaluation']);
        DB::table('grades')->where('comments', 'like', 'tp%')->update(['type' => 'practical_work']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
