<?php

header('Content-Type: text/plain');
echo "--- PROBE START ---\n";
echo 'PHP Version: '.PHP_VERSION."\n";
echo 'Current File: '.__FILE__."\n";
echo 'Current Dir: '.__DIR__."\n";
echo 'Server Software: '.$_SERVER['SERVER_SOFTWARE']."\n";

$autoload = __DIR__.'/../vendor/autoload.php';
echo "Checking Autoload ($autoload): ".(file_exists($autoload) ? 'FOUND' : 'NOT FOUND')."\n";

$bootstrap = __DIR__.'/../bootstrap/app.php';
echo "Checking Bootstrap ($bootstrap): ".(file_exists($bootstrap) ? 'FOUND' : 'NOT FOUND')."\n";

echo 'Memory Limit: '.ini_get('memory_limit')."\n";
echo 'Display Errors: '.ini_get('display_errors')."\n";
echo "--- PROBE END ---\n";
