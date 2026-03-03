<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configs')->updateOrInsert(
            ['id' => 'shift_type'],
            [
                'group' => 'main',
                'description' => 'Tipo de jornada escolar',
                'type' => 'text',
                'value' => 'simple',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('configs')->where('id', 'shift_type')->delete();
    }
};
