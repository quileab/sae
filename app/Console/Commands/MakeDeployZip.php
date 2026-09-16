<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use ZipArchive;

class MakeDeployZip extends Command
{
    protected $signature = 'make:deploy-zip {--name=deploy.zip} {--no-build} {--no-optimize} {--include-env}';

    protected $description = 'Genera un paquete ZIP para despliegue en hosting compartido';

    public function handle()
    {
        $this->info('🚀 Iniciando proceso de empaquetado...');

        if (! $this->option('no-build')) {
            // 1. Ejecutar npm run build
            $this->info('📦 Ejecutando npm run build...');
            $process = new Process(['npm', 'run', 'build']);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error('❌ Error al ejecutar npm run build.');

                return 1;
            }
        }

        // 2. Definir ruta final. Usaremos un nombre temporal único.
        $zipName = $this->option('name');

        // Handle name with timestamp if not provided or to avoid conflicts
        if ($zipName === 'deploy.zip' && ! $this->option('no-optimize')) {
            $zipName = 'deploy_'.now()->format('Y-m-d_His').'.zip';
        }

        $finalPath = base_path($zipName);
        $tempZipName = 'temp_'.time().'_'.$zipName;
        $tempPath = base_path($tempZipName);

        // Limpieza previa
        if (file_exists($finalPath)) {
            @unlink($finalPath);
        }
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE) !== true) {
            $this->error('❌ No se pudo crear el archivo ZIP en la raíz.');

            return 1;
        }

        $this->info('📂 Analizando y agregando archivos...');

        $rootPath = realpath(base_path());
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $count = 0;
        foreach ($files as $name => $file) {
            $filePath = $file->getRealPath();
            $relativePath = ltrim(str_replace($rootPath, '', $filePath), DIRECTORY_SEPARATOR);

            // Convert Windows separators to Linux separators for ZIP internal structure
            $zipPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            // --- REGLAS DE EXCLUSIÓN ESTRICTAS ---

            // 1. Excluir carpetas pesadas y de desarrollo
            if (Str::startsWith($zipPath, 'vendor/') ||
                Str::startsWith($zipPath, 'node_modules/') ||
                Str::startsWith($zipPath, '.git/') ||
                Str::startsWith($zipPath, 'tests/')) {
                continue;
            }

            // 2. Excluir TODA la carpeta storage y bootstrap/cache, y public/hot
            if (Str::startsWith($zipPath, 'storage/') ||
                Str::startsWith($zipPath, 'bootstrap/cache/') ||
                $zipPath === 'public/hot') {
                continue;
            }

            // 3. Excluir archivos ocultos de configuración local (excepto .htaccess y .env.example)
            $pathParts = explode('/', $zipPath);
            if (collect($pathParts)->contains(fn ($part) => Str::startsWith($part, '.') && $part !== '.htaccess' && $part !== '.env.example')) {
                // Special check for .env
                if ($zipPath === '.env' && $this->option('include-env')) {
                    // Include it
                } else {
                    continue;
                }
            }

            // 4. Excluir archivos de base de datos local (sqlite) y otros archivos ZIP (excepto el que estamos creando)
            if (Str::endsWith($zipPath, '.sqlite') || (Str::endsWith($zipPath, '.zip') && ! Str::startsWith($zipPath, 'deploy_'))) {
                if ($zipPath !== $zipName) {
                    continue;
                }
            }

            // 5. No incluirse a sí mismo
            if ($zipPath === $tempZipName || $zipPath === $zipName) {
                continue;
            }

            $zip->addFile($filePath, $zipPath);
            $count++;
        }

        $this->info("🤐 Comprimiendo {$count} archivos... (esto puede tardar)");

        // El error Permission Denied suele ser aquí. Intentamos capturarlo.
        try {
            $closed = $zip->close();
        } catch (\Exception $e) {
            $closed = false;
        }

        if (! $closed) {
            $this->error('❌ Error: Windows o un Antivirus bloqueó el cierre del archivo ZIP.');
            $this->line('💡 Intenta desactivar temporalmente el Antivirus o cerrar programas que usen la carpeta.');
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return 1;
        }

        // 3. Renombrar al nombre final
        if (! @rename($tempPath, $finalPath)) {
            $this->error("❌ No se pudo renombrar el archivo a {$zipName}, pero se creó como {$tempZipName}");

            return 1;
        }

        $this->info("✅ ¡Éxito! Paquete generado: {$zipName}");
        $this->line('🚀 Listo para subir a tu hosting compartido.');

        return 0;
    }
}
