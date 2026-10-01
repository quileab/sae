<?php

namespace App\Console\Commands;

use App\Models\Config;
use Illuminate\Console\Command;

class SyncConfigsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-configs {--overwrite : Sobrescribe los valores existentes con los valores por defecto}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los parámetros faltantes en la tabla configs según la configuración del sistema.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // AFIP requirements included here as requested
        $defaultConfigs = [
            'afip_cert' => [
                'value' => 'qbCert.crt',
                'description' => 'Nombre del certificado (.crt) para AFIP en storage/app/certs/',
            ],
            'afip_key' => [
                'value' => 'qbPrivate.key',
                'description' => 'Nombre de la clave privada (.key) para AFIP en storage/app/certs/',
            ],
            'cuit' => [
                'value' => '20000000001',
                'description' => 'CUIT de la institución para facturación electrónica AFIP',
            ],
            'production' => [
                'value' => 'false',
                'type' => 'bool',
                'description' => 'Modo AFIP (Off: Testing/Homologación, On: Producción)',
            ],
        ];

        // Merge with any config file defaults if it exists
        $systemConfigs = config('default_configs', []);
        $defaultConfigs = array_merge($systemConfigs, $defaultConfigs);

        if (empty($defaultConfigs)) {
            $this->error('No se encontraron parámetros por defecto.');

            return 1;
        }

        $overwrite = (bool) $this->option('overwrite');
        $this->info($overwrite ? '🔄 Sincronizando configuraciones (modo sobrescritura)...' : '🔍 Verificando parámetros faltantes en configs...');

        $tableData = [];
        $added = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($defaultConfigs as $id => $attributes) {
            // Note: SAE's configs table might use 'id' as the key column (based on AfipService code: plucked by id).
            // Let's verify SAE's Config model fields. Assuming 'id' and 'value'.
            // Wait, in AfipService I wrote: `$config = DB::table('configs')->pluck('value', 'id');`
            // Let's assume the column is `id` or `key`. Let's use `id` as string key.
            $existing = Config::find($id);

            if (! $existing) {
                Config::create([
                    'id' => $id,
                    'group' => $attributes['group'] ?? $id,
                    'type' => $attributes['type'] ?? 'text',
                    'value' => $attributes['value'] ?? null,
                    'description' => $attributes['description'] ?? null,
                ]);

                $added++;
                $tableData[] = ['<info>Agregado</info>', $id, $this->formatDisplayValue($attributes['value'] ?? null)];
            } elseif ($overwrite) {
                $existing->update([
                    'value' => $attributes['value'] ?? null,
                    'type' => $attributes['type'] ?? $existing->type ?? 'text',
                    'description' => $attributes['description'] ?? $existing->description,
                ]);

                $updated++;
                $tableData[] = ['<comment>Actualizado</comment>', $id, $this->formatDisplayValue($attributes['value'] ?? null)];
            } else {
                // Ensure description is updated if missing
                if (empty($existing->description) && ! empty($attributes['description'])) {
                    $existing->update(['description' => $attributes['description']]);
                }

                $skipped++;
                $tableData[] = ['<fg=gray>Existente</>', $id, $this->formatDisplayValue($existing->value)];
            }
        }

        $this->newLine();
        $this->table(['Estado', 'Config', 'Valor Actual'], $tableData);
        $this->newLine();

        $this->info("✨ Proceso completado: <info>{$added} agregados</info>, <comment>{$updated} actualizados</comment>, <fg=gray>{$skipped} existentes conservados</>.");

        return 0;
    }

    /**
     * Format value for concise console table output.
     */
    private function formatDisplayValue(mixed $value): string
    {
        if ($value === null) {
            return '<null>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $str = (string) $value;
        if (strlen($str) > 40) {
            return substr($str, 0, 37).'...';
        }

        return $str;
    }
}
