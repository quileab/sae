<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        // then: function ($router) {
        //     $router->middleware('roles', App\Http\Middleware\CheckRoles::class);
        // }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/mercadopago/webhook'
        ]);

        //si es un middleware GLOBAL
        $middleware->alias([
            'roles' => App\Http\Middleware\CheckRoles::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
