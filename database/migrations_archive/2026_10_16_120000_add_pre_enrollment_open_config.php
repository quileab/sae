<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Interruptor global para habilitar/cerrar el formulario público de preinscripción.
     *
     * La tabla `configs` tiene un índice único sobre `group`, por lo que este flag
     * usa su propio grupo (`pre_enrollment`). Se administra desde /config-manager.
     */
    public function up(): void
    {
        if (! DB::table('configs')->where('id', 'pre_enrollment_open')->exists()) {
            DB::table('configs')->insert([
                'id' => 'pre_enrollment_open',
                'group' => 'pre_enrollment',
                'description' => 'Preinscripción abierta (formulario público)',
                'type' => 'bool',
                'value' => 'true',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        cache()->forget('config.pre_enrollment_open');
    }

    public function down(): void
    {
        DB::table('configs')->where('id', 'pre_enrollment_open')->delete();

        cache()->forget('config.pre_enrollment_open');
    }
};
