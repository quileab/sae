<?php

namespace App\Providers;

use App\Models\Configs;
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

        // Compartir etiquetas configurables con todas las vistas
        try {
            $labels = Configs::where('group', 'labels')->get()->pluck('value', 'id')->toArray();

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
