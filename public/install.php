<?php

/**
 * Laravel Shared Hosting Installer
 *
 * Este script permite ejecutar comandos esenciales de Artisan desde el navegador.
 *
 * SEGURIDAD:
 * 1. El script requiere un token para ejecutarse.
 * 2. ELIMINA ESTE ARCHIVO INMEDIATAMENTE DESPUÉS DE LA INSTALACIÓN.
 */
define('INSTALL_TOKEN', 'sae_deploy_2026'); // Cambia esto si deseas más seguridad

// Verificar token
if (! isset($_GET['token']) || $_GET['token'] !== INSTALL_TOKEN) {
    exit('Acceso denegado. Token inválido. Usa: install.php?token='.INSTALL_TOKEN);
}

// 1. Cargar Autoload
$autoload = __DIR__.'/../vendor/autoload.php';
if (! file_exists($autoload)) {
    exit('Error: No se encontró vendor/autoload.php. Asegúrate de haber subido la carpeta vendor.');
}
require $autoload;

// 2. Iniciar Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Función para ejecutar comandos
function runArtisan($command, $parameters = [])
{
    global $kernel;
    echo "<li>Ejecutando: <strong>php artisan $command</strong> ... ";
    try {
        $status = $kernel->call($command, $parameters);
        $output = \Illuminate\Support\Facades\Artisan::output();
        echo ($status === 0 ? '<span style="color:green">ÉXITO</span>' : '<span style="color:red">ERROR</span>').'</li>';
        echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ddd;'>".htmlspecialchars($output).'</pre>';

        return $status === 0;
    } catch (\Exception $e) {
        echo '<span style="color:red">ERROR EXCEPCIÓN</span></li>';
        echo "<pre style='background:#fee; padding:10px; border:1px solid #fcc;'>".htmlspecialchars($e->getMessage()).'</pre>';

        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sae - Instalador</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 20px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeeba; border-radius: 4px; margin-bottom: 20px; }
        .success-msg { background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; margin-top: 20px; }
        h1 { color: #333; }
        pre { white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Proceso de Instalación - SAE</h1>
    
    <div class="warning">
        <strong>¡IMPORTANTE!</strong> Por seguridad, elimina este archivo (<code>install.php</code>) de tu servidor inmediatamente después de que termine el proceso.
    </div>

    <ul>
        <?php
        // A. Crear .env si no existe
        if (! file_exists(__DIR__.'/../.env')) {
            if (file_exists(__DIR__.'/../.env.example')) {
                copy(__DIR__.'/../.env.example', __DIR__.'/../.env');
                echo '<li>Archivo <code>.env</code> creado desde <code>.env.example</code>.</li>';
            } else {
                echo "<li style='color:red'>Error: No se encontró .env.example.</li>";
            }
        } else {
            echo '<li>El archivo <code>.env</code> ya existe.</li>';
        }

// B. Comandos
$steps = true;

if ($steps) {
    $steps = runArtisan('key:generate', ['--force' => true]);
}
if ($steps) {
    $steps = runArtisan('storage:link');
}

// El usuario puede decidir si migrar o no mediante un parámetro
if ($steps && isset($_GET['migrate'])) {
    $steps = runArtisan('migrate', ['--force' => true]);
} else {
    echo '<li><strong>Migración omitida.</strong> (Para ejecutar: <code>install.php?token=...&migrate=1</code>)</li>';
}

if ($steps) {
    $steps = runArtisan('optimize');
}

if ($steps) {
    echo "<div class='success-msg'><strong>Instalación completada correctamente.</strong></div>";
} else {
    echo "<div style='color:red; margin-top:20px;'><strong>Hubo errores durante la instalación.</strong> Revisa los logs o el output de arriba.</div>";
}
?>
    </ul>

    <p><a href="/">Ir a la página de inicio</a></p>
</body>
</html>
