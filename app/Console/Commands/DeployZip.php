<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployZip extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:deploy-zip 
                            {--no-build : Skip running npm run build}
                            {--no-optimize : Skip running php artisan optimize}
                            {--include-env : Incluir el archivo .env actual}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un archivo ZIP con los archivos necesarios para un despliegue en hosting compartido.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando preparación del paquete de despliegue...');

        if (! $this->option('no-build')) {
            $this->info('Ejecutando npm run build...');
            $result = shell_exec('npm run build');
            if (str_contains((string) $result, 'error')) {
                $this->error('Error al ejecutar npm run build. Abortando.');

                return self::FAILURE;
            }
        }

        if (! $this->option('no-optimize')) {
            $this->info('Ejecutando php artisan optimize...');
            $this->call('optimize');
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $zipName = "deploy_{$timestamp}.zip";
        $zipPath = base_path($zipName);

        $this->info("Creando archivo ZIP: {$zipName}...");

        if (! class_exists('ZipArchive')) {
            $this->error('La extensión ZipArchive de PHP no está disponible.');

            return self::FAILURE;
        }

        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error("No se pudo crear el archivo ZIP en {$zipPath}");

            return self::FAILURE;
        }

        $rootPath = base_path();
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $excludedPaths = [
            '.git/',
            '.github/',
            '.vscode/',
            '.gemini/',
            '.gemini.rem/',
            '.opencode/',
            'node_modules/',
            'tests/',
            'storage/logs/',
            'storage/framework/cache/data/',
            'storage/framework/sessions/',
            'storage/framework/views/',
            'phpunit.xml',
            '.editorconfig',
            'package-lock.json',
            'composer.lock',
            'respaldo_servidor.sql',
            'manual_usuario.md',
        ];

        if (! $this->option('include-env')) {
            $excludedPaths[] = '.env';
        }

        $count = 0;
        foreach ($files as $name => $file) {
            // Saltarse directorios
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);

            // Verificar exclusiones
            foreach ($excludedPaths as $excluded) {
                // Si termina en /, es un directorio
                if (str_ends_with($excluded, '/')) {
                    if (str_starts_with($relativePath, $excluded)) {
                        continue 2;
                    }
                } elseif ($relativePath === $excluded) {
                    continue 2;
                }
            }

            // Excluir cualquier archivo ZIP o SQL en la raíz o subdirectorios
            if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), ['zip', 'sql', 'rar', '7z'])) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
            $count++;

            if ($count % 100 === 0) {
                $this->info("Agregados {$count} archivos...");
            }
        }

        $zip->close();

        $this->info("¡Listo! Se han empaquetado {$count} archivos en {$zipName}");
        $this->info('Puedes descargar este archivo y subirlo a tu hosting compartido.');

        return self::SUCCESS;
    }
}
