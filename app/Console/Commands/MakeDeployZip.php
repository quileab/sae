<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use Symfony\Component\Process\Process;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Str;

class MakeDeployZip extends Command
{
    protected $signature = 'make:deploy-zip {--name=deploy.zip}';
    protected $description = 'Genera un paquete ZIP para despliegue en hosting compartido';

    public function handle()
    {
        $this->info('🚀 Iniciando proceso de empaquetado...');

        // 1. Ejecutar npm run build
        $this->info('📦 Ejecutando npm run build...');
        $process = new Process(['npm', 'run', 'build']);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('❌ Error al ejecutar npm run build.');
            return 1;
        }

        // 2. Definir ruta final. Usaremos un nombre temporal único.
        $zipName = $this->option('name');
        $finalPath = base_path($zipName);
        $tempZipName = 'temp_' . time() . '_' . $zipName;
        $tempPath = base_path($tempZipName);

        // Limpieza previa
        if (file_exists($finalPath)) @unlink($finalPath);
        if (file_exists($tempPath)) @unlink($tempPath);

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE) !== TRUE) {
            $this->error("❌ No se pudo crear el archivo ZIP en la raíz.");
            return 1;
        }

        $this->info("📂 Analizando y agregando archivos...");

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
                Str::startsWith($zipPath, '.git/')) {
                continue;
            }

            // 2. Excluir TODA la carpeta storage y bootstrap/cache
            if (Str::startsWith($zipPath, 'storage/') ||
                Str::startsWith($zipPath, 'bootstrap/cache/')) {
                continue;
            }

            // 3. Excluir archivos ocultos de configuración local (excepto .htaccess)
            $pathParts = explode('/', $zipPath);
            if (collect($pathParts)->contains(fn($part) => Str::startsWith($part, '.') && $part !== '.htaccess')) {
                continue;
            }

            // 4. Excluir archivos de base de datos local (sqlite) y otros archivos ZIP
            if (Str::endsWith($zipPath, '.sqlite') || Str::endsWith($zipPath, '.zip')) {
                continue;
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

        if (!$closed) {
            $this->error("❌ Error: Windows o un Antivirus bloqueó el cierre del archivo ZIP.");
            $this->line("💡 Intenta desactivar temporalmente el Antivirus o cerrar programas que usen la carpeta.");
            if (file_exists($tempPath)) @unlink($tempPath);
            return 1;
        }

        // 3. Renombrar al nombre final
        if (!@rename($tempPath, $finalPath)) {
            $this->error("❌ No se pudo renombrar el archivo a {$zipName}, pero se creó como {$tempZipName}");
            return 1;
        }

        $this->info("✅ ¡Éxito! Paquete generado: {$zipName}");
        $this->line("🚀 Listo para subir a tu hosting compartido.");
        
        return 0;
    }
}
