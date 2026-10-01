<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use ZipArchive;

class MakeVendorDeployZip extends Command
{
    protected $signature = 'make:vendor-deploy-zip 
                            {--name=vendor.zip : Nombre del archivo ZIP de salida} 
                            {--skip-composer : Omitir composer install --no-dev y comprimir el vendor actual} 
                            {--restore-dev : Restaurar dependencias de desarrollo (composer install) al finalizar}';

    protected $description = 'Genera un paquete ZIP del directorio vendor optimizado para producción';

    public function handle(): int
    {
        $this->info('🚀 Iniciando proceso de empaquetado de vendor...');

        $vendorPath = base_path('vendor');

        if (! is_dir($vendorPath)) {
            $this->error('❌ El directorio vendor no existe. Ejecuta composer install primero.');

            return 1;
        }

        $ranNoDev = false;

        // 1. Optimizar composer para producción si no se omitió
        if (! $this->option('skip-composer')) {
            $this->info('📦 Optimizando vendor para producción (composer install --no-dev --optimize-autoloader)...');

            $process = new Process(['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction']);
            $process->setTimeout(600);
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->error('❌ Error al ejecutar composer install --no-dev.');

                return 1;
            }

            $ranNoDev = true;
        }

        // 2. Definir rutas del archivo ZIP
        $zipName = $this->option('name');
        if ($zipName === 'vendor.zip') {
            $zipName = 'vendor_'.now()->format('Y-m-d_His').'.zip';
        }

        $finalPath = base_path($zipName);
        $tempZipName = 'temp_'.time().'_'.$zipName;
        $tempPath = base_path($tempZipName);

        // Limpieza de archivos previos
        if (file_exists($finalPath)) {
            @unlink($finalPath);
        }
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        $this->info("🤐 Comprimiendo archivos de vendor para {$zipName}...");

        $sevenZipPath = $this->find7ZipPath();

        if ($sevenZipPath) {
            $this->info("⚡ 7-Zip detectado ({$sevenZipPath}). Usando compresión ultra-rápida y confiable...");
            $process = new Process([
                $sevenZipPath,
                'a',
                '-tzip',
                '-mx=7',
                $finalPath,
                'vendor',
                '-xr!vendor/**/.git',
                '-xr!vendor/**/.git/*',
            ], base_path());

            $process->setTimeout(900);
            $process->run(function ($type, $buffer) {
                // Keep output clean but informative
                if (Str::contains($buffer, ['Scanning', 'Archive size', 'Everything is Ok', 'Files read'])) {
                    $this->output->write($buffer);
                }
            });

            if (! $process->isSuccessful() || ! file_exists($finalPath)) {
                $this->error('❌ Error al ejecutar 7-Zip: ' . $process->getErrorOutput());
                return 1;
            }
        } else {
            $this->line('ℹ️ 7-Zip no detectado. Utilizando ZipArchive nativo de PHP...');
            $zip = new ZipArchive;
            if ($zip->open($tempPath, ZipArchive::CREATE) !== true) {
                $this->error('❌ No se pudo crear el archivo ZIP.');

                return 1;
            }

            $this->info('📂 Analizando y agregando archivos de vendor...');

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($vendorPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            $count = 0;
            $rootPath = base_path();

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $relativePath = ltrim(str_replace($rootPath, '', $filePath), DIRECTORY_SEPARATOR);

                // Separadores Linux para el ZIP
                $zipPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                // Excluir repositorios .git internos de paquetes
                if (Str::contains($zipPath, '/.git/') || Str::endsWith($zipPath, '/.git')) {
                    continue;
                }

                $zip->addFile($filePath, $zipPath);
                $count++;
            }

            $this->info("🤐 Comprimiendo {$count} archivos de vendor... (esto puede tardar)");

            try {
                $closed = $zip->close();
            } catch (\Exception $e) {
                $closed = false;
            }

            if (! $closed) {
                $this->error('❌ Error: Windows o un Antivirus bloqueó el cierre del archivo ZIP.');
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }

                return 1;
            }

            if (! @rename($tempPath, $finalPath)) {
                $this->error("❌ No se pudo renombrar el archivo a {$zipName}, pero se creó como {$tempZipName}");

                return 1;
            }
        }

        $sizeMb = round(filesize($finalPath) / 1024 / 1024, 2);
        $this->info("✅ ¡Éxito! Paquete generado: {$zipName} ({$sizeMb} MB)");
        $this->line("🚀 Contiene la carpeta 'vendor/' lista para descomprimir en la raíz de tu hosting.");

        // 3. Restaurar dependencias de desarrollo si se solicitó o si se ejecutó --no-dev
        if ($ranNoDev && $this->option('restore-dev')) {
            $this->info('🔄 Restaurando dependencias de desarrollo (composer install)...');
            $restoreProcess = new Process(['composer', 'install', '--no-interaction']);
            $restoreProcess->setTimeout(600);
            $restoreProcess->run();
            $this->info('✨ Entorno local restaurado con paquetes dev.');
        }

        return 0;
    }

    private function find7ZipPath(): ?string
    {
        $candidates = [
            '7z',
            'C:\\Program Files\\7-Zip\\7z.exe',
            'C:\\Program Files (x86)\\7-Zip\\7z.exe',
        ];

        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate]);
                $process->run();
                if ($process->isSuccessful() || $process->getExitCode() === 0) {
                    return $candidate;
                }
            } catch (\Exception $e) {
                // continuar probando otros candidatos
            }
        }

        return null;
    }
}
