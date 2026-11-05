<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SyncConsolidatedMigrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-consolidated-migrations {--force : Forzar la sincronización sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza la tabla de migraciones con las 40 migraciones consolidadas y corrige índices en configs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Sincronizando migraciones consolidadas...');

        // 1. Corregir índice de configs si existe el índice único
        if (Schema::hasTable('configs')) {
            $this->info('🛠️ Verificando índices en tabla configs...');

            $indexes = collect(DB::select('SHOW INDEXES FROM `configs`'))->pluck('Key_name')->unique()->toArray();

            if (in_array('configs_group_unique', $indexes)) {
                $this->warn('⚠️ Detectado índice único configs_group_unique. Eliminándolo...');
                DB::statement('ALTER TABLE `configs` DROP INDEX `configs_group_unique`');
                $this->info('✅ configs_group_unique eliminado.');
            }

            // Re-consultar índices tras eliminar
            $indexes = collect(DB::select('SHOW INDEXES FROM `configs`'))->pluck('Key_name')->unique()->toArray();

            if (! in_array('configs_group_index', $indexes)) {
                $this->info('➕ Creando índice regular configs_group_index en columna group...');
                DB::statement('ALTER TABLE `configs` ADD INDEX `configs_group_index` (`group`)');
                $this->info('✅ configs_group_index creado.');
            } else {
                $this->info('✓ configs_group_index ya presente.');
            }
        }

        // 2. Obtener lista de migraciones consolidadas
        $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn ($path) => basename($path, '.php'))
            ->sort()
            ->values();

        if ($migrationFiles->isEmpty()) {
            $this->error('No se encontraron migraciones en database/migrations.');

            return 1;
        }

        if (! Schema::hasTable('migrations')) {
            $this->error('La tabla migrations no existe.');

            return 1;
        }

        // 3. Registrar migraciones consolidadas
        $this->info('📋 Registrando migraciones consolidadas en la tabla migrations...');

        $existing = DB::table('migrations')->pluck('migration')->toArray();
        $inserted = 0;

        foreach ($migrationFiles as $migrationName) {
            if (! in_array($migrationName, $existing)) {
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => 1,
                ]);
                $inserted++;
            }
        }

        $this->info("✨ Sincronización completada: {$inserted} migraciones consolidadas registradas.");

        return 0;
    }
}
