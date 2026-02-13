<?php
// 1. FORZAR DIAGNÓSTICO
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. CONFIGURACIÓN
define('SETUP_TOKEN', 'laravel_deploy_v1');

// 3. CAPTURA DE ERRORES FATALES
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        echo "<div style='background:#fee2e2; color:#991b1b; padding:20px; border:2px solid #ef4444; border-radius:8px; margin:20px; font-family:sans-serif;'>";
        echo '<strong>FATAL ERROR DETECTADO:</strong><br><br>';
        echo '<b>Mensaje:</b> '.htmlspecialchars($error['message']).'<br>';
        echo '<b>Archivo:</b> '.htmlspecialchars($error['file']).'<br>';
        echo '<b>Línea:</b> '.$error['line'].'<br>';
        echo '</div>';
    }
});

// 4. DETECCIÓN DE RUTAS
$rootDir = dirname(__DIR__); // Más seguro que realpath en algunos hostings
$autoload = $rootDir.'/vendor/autoload.php';
$bootstrap = $rootDir.'/bootstrap/app.php';

// 5. VERIFICACIÓN DE ACCESO
if (! isset($_GET['token']) || $_GET['token'] !== SETUP_TOKEN) {
    exit("<div style='padding:20px; font-family:sans-serif;'><h1>Acceso Denegado</h1><p>Por seguridad, debes usar el token: <code>laravel-deployer.php?token=".SETUP_TOKEN.'</code></p></div>');
}

$output = '';
$status_class = 'info';

// 6. LÓGICA DE ACCIONES (SOLO SI HAY POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'env':
                if (file_exists($rootDir.'/.env.example') && ! file_exists($rootDir.'/.env')) {
                    copy($rootDir.'/.env.example', $rootDir.'/.env');
                    $output = "Archivo .env creado desde .env.example\n";
                } else {
                    $output = "El archivo .env ya existe o no hay .env.example\n";
                }
                break;

            case 'key':
                $envPath = $rootDir.'/.env';
                if (! file_exists($envPath)) {
                    throw new Exception('Crea primero el archivo .env');
                }
                $content = file_get_contents($envPath);
                $key = 'base64:'.base64_encode(random_bytes(32));
                $content = str_contains($content, 'APP_KEY=') ? preg_replace('/^APP_KEY=.*$/m', "APP_KEY=$key", $content) : $content."\nAPP_KEY=$key\n";
                file_put_contents($envPath, $content);
                $output = "APP_KEY generada y guardada manualmente.\n";
                break;

            case 'htaccess':
                $htaccessPath = $rootDir.'/.htaccess';
                $rules = "\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_URI} !^/public/\n    RewriteRule ^(.*)$ public/$1 [L]\n</IfModule>";
                file_put_contents($htaccessPath, $rules, FILE_APPEND);
                $output = "Reglas de redirección añadidas al .htaccess de la raíz.\n";
                break;

            case 'save_env':
                $envPath = $rootDir.'/.env';
                if (isset($_POST['env_content'])) {
                    $content = $_POST['env_content'];
                    // Normalizar a LF (Linux) retirando cualquier rastro de CR (\r)
                    $content = str_replace("\r", '', $content);

                    // Asegurar que el contenido sea UTF-8 (sin BOM para evitar problemas en Laravel)
                    if (function_exists('mb_convert_encoding')) {
                        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                    }

                    if (file_put_contents($envPath, $content)) {
                        $output = "Archivo .env guardado con éxito.\n";
                        $output .= "Formato forzado: UTF-8 sin BOM / Finales de línea LF (Linux).\n";

                        // Diagnóstico del archivo guardado
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_ENCODING);
                            $encoding = finfo_file($finfo, $envPath);
                            finfo_close($finfo);
                            $output .= 'Codificación detectada por el servidor: '.strtoupper($encoding)."\n";
                        }
                    } else {
                        throw new Exception('No se pudo escribir en el archivo .env.');
                    }
                }
                break;

            case 'self_destruct':
                unlink(__FILE__);
                exit('<h1>Script Eliminado</h1>');

            default:
                // CARGA DE LARAVEL PARA ACCIONES DE ARTISAN
                if (! file_exists($autoload) || ! file_exists($bootstrap)) {
                    throw new Exception("Laravel no detectado en: $rootDir");
                }

                require_once $autoload;
                $app = require_once $bootstrap;
                \Illuminate\Support\Facades\Facade::setFacadeApplication($app);

                // Asegurar directorios antes de cualquier comando de Artisan
                $requiredDirs = [
                    $rootDir.'/storage/logs',
                    $rootDir.'/storage/framework/cache',
                    $rootDir.'/storage/framework/cache/data',
                    $rootDir.'/storage/framework/sessions',
                    $rootDir.'/storage/framework/views',
                ];
                foreach ($requiredDirs as $dir) {
                    if (! file_exists($dir)) {
                        mkdir($dir, 0755, true);
                        $output .= 'Sistema: Directorio creado: '.basename($dir)."\n";
                    }
                }

                $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

                if ($action === 'migrate') {
                    $exitCode = $kernel->call('migrate', ['--force' => true]);
                    $cmdOutput = \Illuminate\Support\Facades\Artisan::output();
                    $output .= "MIGRACIONES (Código: $exitCode):\n".($cmdOutput ?: 'Ejecutado correctamente (sin cambios pendientes o salida vacía).');
                }

                if ($action === 'optimize') {
                    $output .= "Solicitando optimización de caches...\n";
                    $exitCode = $kernel->call('optimize');
                    $cmdOutput = \Illuminate\Support\Facades\Artisan::output();
                    $output .= "OPTIMIZACIÓN (Código: $exitCode):\n".($cmdOutput ?: '¡Éxito! La configuración y rutas han sido cacheadas (Laravel no devolvió texto extra, pero el proceso terminó bien).');
                }

                if ($action === 'link') {
                    $target = $rootDir.'/storage/app/public';
                    $link = __DIR__.'/storage';
                    if (is_link($link)) {
                        unlink($link);
                    }
                    if (is_dir($link)) {
                        rename($link, $link.'_bak_'.time());
                    }
                    if (! file_exists($target)) {
                        mkdir($target, 0755, true);
                    }
                    symlink($target, $link);
                    $output = "Enlace de storage creado manualmente de $target a $link.\n";
                }

                if ($action === 'up') {
                    $downFile = $rootDir.'/storage/framework/down';
                    if (file_exists($downFile)) {
                        unlink($downFile);
                    }
                    $output = "Modo mantenimiento desactivado.\n";
                }

                if ($action === 'check') {
                    $dirs = [
                        $rootDir.'/storage',
                        $rootDir.'/storage/logs',
                        $rootDir.'/storage/framework',
                        $rootDir.'/bootstrap/cache',
                    ];
                    foreach ($dirs as $dir) {
                        if (file_exists($dir)) {
                            $output .= basename($dir).': '.(is_writable($dir) ? '✅ Escribible' : '❌ NO ESCRIBIBLE').' ('.substr(sprintf('%o', fileperms($dir)), -4).")\n";
                        } else {
                            $output .= basename($dir).": ❌ NO EXISTE (Intentando crear...)\n";
                        }
                    }
                }

                if ($action === 'logs') {
                    $logFile = $rootDir.'/storage/logs/laravel.log';
                    if (file_exists($logFile)) {
                        $lines = explode("\n", file_get_contents($logFile));
                        $output = "ÚLTIMOS LOGS:\n".implode("\n", array_slice($lines, -20));
                    } else {
                        $output = "No se encontró el archivo de log en $logFile";
                    }
                }

                if ($action === 'clear_cache') {
                    $cacheFiles = glob($rootDir.'/bootstrap/cache/*.php');
                    foreach ($cacheFiles as $file) {
                        if (basename($file) !== 'packages.php' && basename($file) !== 'services.php') {
                            unlink($file);
                        }
                    }
                    $output = "Cache de bootstrap limpiado manualmente (config.php, routes-v7.php, etc).\n";
                }
                break;
        }
        $status_class = 'success';
    } catch (Throwable $e) {
        $output = 'ERROR FATAL: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine();
        $status_class = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Universal Laravel Deployer</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f0f2f5; color: #1c1e21; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .info-box { background: #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .card { border: 1px solid #edf2f7; padding: 15px; border-radius: 8px; text-align: center; }
        button { background: #4a5568; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 5px; font-weight: 600; }
        button:hover { background: #2d3748; }
        button.danger { background: #e53e3e; }
        pre { background: #1a202c; color: #a0aec0; padding: 20px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; font-size: 13px; }
        .success { border: 1px solid #c6f6d5; background: #f0fff4; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { border: 1px solid #fed7d7; background: #fff5f5; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Universal Laravel Deployer</h1>
        
        <div class="info-box">
            <b>Ruta Raíz:</b> <code><?php echo $rootDir; ?></code><br>
            <b>PHP:</b> <code><?php echo PHP_VERSION; ?></code>
        </div>

        <?php if ($output) { ?>
            <div class="<?php echo $status_class; ?>">
                <strong>Resultado:</strong>
                <pre><?php echo htmlspecialchars($output); ?></pre>
            </div>
        <?php } ?>

        <div class="grid" style="margin-top:20px;">
            <div class="card">
                <h3>Vitals</h3>
                <form method="POST"><input type="hidden" name="action" value="env"><button>1. Generar .env</button></form>
                <form method="POST"><input type="hidden" name="action" value="htaccess"><button>2. Root Support</button></form>
                <form method="POST"><input type="hidden" name="action" value="key"><button>3. Generar APP_KEY</button></form>
            </div>
            <div class="card">
                <h3>Storage</h3>
                <form method="POST"><input type="hidden" name="action" value="link"><button>4. Storage:link</button></form>
                <form method="POST"><input type="hidden" name="action" value="up"><button style="background:#d69e2e">Desact. Mantenimiento</button></form>
            </div>
            <div class="card">
                <h3>App</h3>
                <form method="POST"><input type="hidden" name="action" value="migrate"><button>5. Migraciones</button></form>
                <form method="POST"><input type="hidden" name="action" value="optimize"><button>6. Optimizar Todo</button></form>
                <form method="POST"><input type="hidden" name="action" value="check"><button style="background:#4a5568">Verificar Permisos</button></form>
                <form method="POST"><input type="hidden" name="action" value="logs"><button style="background:#4a5568">Ver Últimos Logs</button></form>
                <form method="POST" onsubmit="return confirm('¿Limpiar cache de bootstrap manualmente?');">
                    <input type="hidden" name="action" value="clear_cache">
                    <button style="background:#9b2c2c">Limpieza de Emergencia (Cache)</button>
                </form>
            </div>
            <div class="card" style="border-color:#feb2b2">
                <h3 style="color:#c53030">Seguridad</h3>
                <form method="POST" onsubmit="return confirm('¿Borrar script?');">
                    <input type="hidden" name="action" value="self_destruct">
                    <button class="danger">Auto-Destrucción</button>
                </form>
            </div>
            <div class="card" style="grid-column: span 2; margin-top: 10px;">
                <h3>⚙️ Editor de .env</h3>
                <?php
                $envContent = '';
$envPath = $rootDir.'/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
}
?>
                <form method="POST">
                    <input type="hidden" name="action" value="save_env">
                    <textarea name="env_content" style="width:100%; height:250px; font-family:monospace; padding:10px; border-radius:4px; border:1px solid #cbd5e0;"><?php echo htmlspecialchars($envContent); ?></textarea>
                    <button type="submit" style="margin-top:10px; background:#48bb78;">Guardar Cambios en .env</button>
                </form>
                <p style="font-size:11px; color:#718096; margin-top:5px;">Edita aquí los datos de base de datos y otras variables de entorno.</p>
            </div>
        </div>
    </div>
</body>
</html>
