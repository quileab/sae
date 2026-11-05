<?php

namespace App\Providers;

use App\Models\Config as ConfigModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Consulta pública del estado de preinscripción: límite por DNI y por IP.
        // La landing llama server-side, por lo que todas las consultas legítimas
        // comparten su IP: el límite por DNI es el control anti-abuso real y el
        // límite por IP actúa como red de seguridad ante un cliente desmedido.
        RateLimiter::for('pre-enrollment-status', function (Request $request) {
            return [
                Limit::perMinute(5)->by('dni:'.$request->input('dni')),
                Limit::perMinute(120)->by('ip:'.$request->ip()),
            ];
        });

        // Estado público del formulario de preinscripción (abierto/cerrado): solo por IP.
        // La landing lo cachea, por lo que un límite holgado por IP es suficiente.
        RateLimiter::for('pre-enrollment-form-status', function (Request $request) {
            return Limit::perMinute(120)->by('ip:'.$request->ip());
        });

        // Compartir etiquetas configurables con todas las vistas
        try {
            $labels = ConfigModel::where('group', 'labels')->get()->pluck('value', 'id')->toArray();

            // Valores por defecto si no existen en la DB
            $defaultLabels = [
                'label_career' => 'Carrera',
                'label_careers' => 'Carreras',
                'label_subject' => 'Materia',
                'label_subjects' => 'Materias',
            ];

            View::share('labels', array_merge($defaultLabels, $labels));
        } catch (\Exception $e) {
            // En caso de que la tabla no exista aún (ej. durante migraciones)
        }
    }
}
