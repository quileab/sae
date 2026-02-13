<?php

use Illuminate\Support\Facades\File;

test('it excludes .env by default but includes .env.example', function () {
    $this->artisan('make:deploy-zip', [
        '--no-build' => true,
        '--no-optimize' => true,
    ])->assertExitCode(0);

    $allZips = File::glob(base_path('deploy_*.zip'));
    $latestZip = end($allZips);

    $zip = new \ZipArchive;
    $zip->open($latestZip);

    $filesInZip = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filesInZip[] = $zip->getNameIndex($i);
    }
    $zip->close();

    // El .env NO debe estar, pero el .env.example SÍ
    expect($filesInZip)->not->toContain('.env');
    expect($filesInZip)->toContain('.env.example');

    // Limpiar
    File::delete($latestZip);
});

test('it includes .env when --include-env flag is used', function () {
    $this->artisan('make:deploy-zip', [
        '--no-build' => true,
        '--no-optimize' => true,
        '--include-env' => true,
    ])->assertExitCode(0);

    $allZips = File::glob(base_path('deploy_*.zip'));
    $latestZip = end($allZips);

    $zip = new \ZipArchive;
    $zip->open($latestZip);

    $filesInZip = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filesInZip[] = $zip->getNameIndex($i);
    }
    $zip->close();

    // Ambos deben estar
    expect($filesInZip)->toContain('.env');
    expect($filesInZip)->toContain('.env.example');

    // Limpiar
    File::delete($latestZip);
});

test('it excludes unnecessary files from the zip', function () {
    $this->artisan('make:deploy-zip', [
        '--no-build' => true,
        '--no-optimize' => true,
    ])->assertExitCode(0);

    $allZips = File::glob(base_path('deploy_*.zip'));
    $latestZip = end($allZips);

    $zip = new \ZipArchive;
    $zip->open($latestZip);

    $filesInZip = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filesInZip[] = $zip->getNameIndex($i);
    }
    $zip->close();

    // Verificar exclusiones (usando prefijos para directorios si es necesario,
    // pero el comando ahora usa correspondencia exacta para archivos)
    expect($filesInZip)->not->toContain('.git/config');
    expect($filesInZip)->not->toContain('node_modules/vite/package.json');
    expect($filesInZip)->not->toContain('tests/Feature/ExampleTest.php');

    // Verificar inclusiones importantes
    expect($filesInZip)->toContain('composer.json');
    expect($filesInZip)->toContain('public/index.php');

    // Limpiar
    File::delete($latestZip);
});
