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
            // Enlace al examen/original que este recuperatorio reemplaza en el promedio EV
            $table->unsignedBigInteger('recovered_grade_id')->nullable()->after('type');

            $table->foreign('recovered_grade_id')
                ->references('id')
                ->on('grades')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['recovered_grade_id']);
            $table->dropColumn('recovered_grade_id');
        });
    }
};
