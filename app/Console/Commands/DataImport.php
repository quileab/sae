<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DataImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:import {file?} {--skip=migrations,sessions,cache,cache_locks,password_reset_tokens : Tablas a omitir}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importador universal de datos SAE con detección dinámica y limpieza selectiva';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = $this->argument('file');

        if (! $fileName) {
            $sqlFiles = collect(File::glob(base_path('*.sql')))
                ->map(fn (string $path) => '/'.basename($path))
                ->sort()
                ->values();

            if ($sqlFiles->isNotEmpty()) {
                $this->line('   Archivos disponibles: <info>'.$sqlFiles->implode(' ').'</info>');
            } else {
                $this->warn('   No se encontraron archivos .sql en la raíz del proyecto.');
            }

            $fileName = $this->ask('¿Cuál es el nombre del archivo a importar?', 'source.sql');
        }

        $filePath = base_path($fileName);
        $skipTables = explode(',', $this->option('skip'));

        if (! File::exists($filePath)) {
            $this->error("✘ Archivo base no encontrado: $filePath");

            return 1;
        }

        $dbName = DB::connection()->getDatabaseName();

        $this->newLine();
        $this->info("🚀 Iniciando migración universal desde: $fileName en la base de datos: $dbName");
        $this->newLine();

        if (! $this->confirm('⚠️  Esto puede sobrescribir datos en las tablas detectadas. ¿Continuar?', true)) {
            $this->info('Operación cancelada.');

            return 0;
        }

        // Desactivar checks de llaves foráneas para permitir la importación masiva
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $chunksProcessed = 0;
        $errors = 0;
        $truncatedTables = [];

        $filesToProcess = [$filePath];
        $extraFiles = File::glob(base_path('source_*.sql'));
        if (! empty($extraFiles)) {
            sort($extraFiles);
            $filesToProcess = array_merge($filesToProcess, $extraFiles);
        }

        foreach ($filesToProcess as $currentFilePath) {
            $baseName = basename($currentFilePath);
            $isIncremental = (str_starts_with($baseName, 'source_') && $baseName !== 'source.sql');

            $this->warn("📄 Procesando: $baseName ".($isIncremental ? '[INCREMENTAL]' : '[BASE]'));
            $lastTableInFile = '';

            $handle = fopen($currentFilePath, 'r');
            if ($handle) {
                $buffer = '';
                $insideInsert = false;
                $currentTable = '';
                $shouldSkipCurrent = false;

                while (($line = fgets($handle)) !== false) {
                    $trimmedLine = trim($line);

                    if (! $insideInsert) {
                        if (stripos($trimmedLine, 'INSERT INTO') === 0) {
                            if (preg_match('/INSERT INTO `?(\w+)`?/i', $trimmedLine, $matches)) {
                                $currentTable = $matches[1];

                                if (in_array($currentTable, $skipTables)) {
                                    $shouldSkipCurrent = true;
                                    $insideInsert = true; // Entramos para saltar el bloque hasta el ;

                                    continue;
                                }

                                if (Schema::hasTable($currentTable)) {
                                    $shouldSkipCurrent = false;
                                    $insideInsert = true;
                                    $buffer = $line;

                                    // Usar REPLACE INTO para evitar errores de duplicado
                                    $buffer = preg_replace('/INSERT INTO/i', 'REPLACE INTO', $buffer);

                                    // Truncar SOLO si es el archivo BASE y no se ha truncado ya
                                    if (! $isIncremental && ! in_array($currentTable, $truncatedTables)) {
                                        DB::table($currentTable)->truncate();
                                        $truncatedTables[] = $currentTable;
                                    }

                                    if ($currentTable !== $lastTableInFile) {
                                        $this->newLine();
                                        $this->output->write("   ⚡ <comment>$currentTable</comment> ");
                                        $lastTableInFile = $currentTable;
                                    }
                                }
                            }
                        }
                    } else {
                        if (! $shouldSkipCurrent) {
                            $buffer .= $line;
                        }
                    }

                    // Se asume que el INSERT termina en ;
                    if ($insideInsert && str_ends_with($trimmedLine, ';')) {
                        if (! $shouldSkipCurrent && ! empty(trim($buffer))) {
                            try {
                                DB::unprepared($buffer);
                                $chunksProcessed++;

                                // Lógica post-inserción específica para SAE:
                                if ($currentTable === 'grades') {
                                    $this->reclassifyGrades();
                                    $this->resetAttendanceForNonUnitClasses();
                                }

                                $this->output->write('<info>.</info>');
                            } catch (\Exception $e) {
                                $errors++;
                                $this->newLine();
                                $this->error("   ✘ Error en $currentTable: ".substr($e->getMessage(), 0, 150));
                            }
                        }

                        $buffer = '';
                        $insideInsert = false;
                        $shouldSkipCurrent = false;
                    }
                }
                fclose($handle);
                $this->newLine();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info('✨ Importación Finalizada.');
        $this->line("   ✅ Total bloques procesados: <info>$chunksProcessed</info>");
        if ($errors > 0) {
            $this->line("   ⚠️  Errores encontrados: <error>$errors</error>");
        }
        $this->newLine();

        return 0;
    }

    /**
     * Reclasifica los tipos de notas según los comentarios.
     */
    private function reclassifyGrades()
    {
        if (! Schema::hasColumn('grades', 'type')) {
            return;
        }

        DB::table('grades')
            ->where('type', 'regular')
            ->where('comments', 'like', 'ev%')
            ->update(['type' => 'evaluation']);

        DB::table('grades')
            ->where('type', 'regular')
            ->where('comments', 'like', 'tp%')
            ->update(['type' => 'practical_work']);
    }

    private function resetAttendanceForNonUnitClasses()
    {
        DB::table('grades')
            ->join('class_sessions', 'grades.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.unit', '=', '0')
            ->update(['grades.attendance' => 0]);
    }
}
