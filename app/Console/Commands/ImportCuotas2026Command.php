<?php

namespace App\Console\Commands;

use App\Models\PaymentPlan;
use App\Models\PaymentPlanDetail;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\UserPayment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportCuotas2026Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-cuotas-2026 
                            {--file=cuotas-pagas2026.csv : Archivo CSV en la raíz del proyecto} 
                            {--dry-run : Ejecuta una simulación sin escribir en la base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea los 3 planes de pago 2026 e importa las cuotas y pagos de estudiantes desde el CSV';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fileName = $this->option('file');
        $filePath = base_path($fileName);
        $dryRun = (bool) $this->option('dry-run');

        if (! File::exists($filePath)) {
            $this->error("El archivo CSV no existe en la ruta: $filePath");

            return 1;
        }

        $this->info($dryRun
            ? '🔍 Ejecutando SIMULACIÓN (dry-run) de importación de cuotas 2026...'
            : '🚀 Iniciando IMPORTACIÓN REAL de cuotas y pagos 2026...');

        // 1. Crear o asegurar los 3 Planes de Pago Maestros
        $plans = $this->ensurePaymentPlans($dryRun);

        // 2. Procesar CSV
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->error('El archivo CSV está vacío.');

            return 1;
        }

        $header = str_getcsv(array_shift($lines));
        $this->line('Procesando '.count($lines).' registros...');

        $foundCount = 0;
        $notFoundCount = 0;
        $notFoundStudents = [];
        $totalPaymentsCreated = 0;
        $totalRecordsCreated = 0;
        $totalAmountCollected = 0;
        $totalDebtsFoundCount = 0;
        $totalDebtAmount = 0;

        $bar = $this->output->createProgressBar(count($lines));
        $bar->start();

        foreach ($lines as $line) {
            $bar->advance();
            $row = str_getcsv($line);

            if (count($row) < 7) {
                continue;
            }

            $apellido = trim($row[0] ?? '');
            $nombres = trim($row[1] ?? '');
            $doc = preg_replace('/[^0-9]/', '', $row[2] ?? '');
            $anioEstudio = trim($row[3] ?? '');
            $deudaAnteriorRaw = $row[5] ?? '';
            $deudaAnterior = (float) preg_replace('/[^0-9.]/', '', $deudaAnteriorRaw);

            if (empty($doc)) {
                $notFoundCount++;
                $notFoundStudents[] = "$apellido, $nombres (Sin DNI)";

                continue;
            }

            // Buscar estudiante por DNI (name o id)
            $user = User::where('role', 'student')
                ->where(function ($query) use ($doc) {
                    $query->where('name', $doc)->orWhere('id', $doc);
                })->first();

            if (! $user) {
                $notFoundCount++;
                $notFoundStudents[] = "$apellido, $nombres (DNI: $doc)";

                continue;
            }

            $foundCount++;

            // Mapeo de meses y montos del CSV:
            // Columna 6: ins- 26
            // Columna 7: 26-mar
            // Columna 8: 26-abr
            // Columna 9: 26-may
            // Columna 10: 26-jun
            // Columna 11: 26-jul
            // Columna 12: 26-ago
            // Columna 13: 26-sept
            // Columna 14: 26-oct
            // Columna 15: 26-nov
            // Columna 16: 26-dic
            $installmentsDef = [
                ['col' => 6,  'date' => '2026-02-10', 'title' => 'Ins-2026',        'default_unpaid' => (str_contains(mb_strtolower($anioEstudio), 'primer') ? 120000 : 100000)],
                ['col' => 7,  'date' => '2026-03-10', 'title' => 'Marzo-2026',      'default_unpaid' => 80000],
                ['col' => 8,  'date' => '2026-04-10', 'title' => 'Abril-2026',      'default_unpaid' => 80000],
                ['col' => 9,  'date' => '2026-05-10', 'title' => 'Mayo-2026',       'default_unpaid' => 80000],
                ['col' => 10, 'date' => '2026-06-10', 'title' => 'Junio-2026',      'default_unpaid' => 80000],
                ['col' => 11, 'date' => '2026-07-10', 'title' => 'Julio-2026',      'default_unpaid' => 80000],
                ['col' => 12, 'date' => '2026-08-10', 'title' => 'Agosto-2026',     'default_unpaid' => 80000],
                ['col' => 13, 'date' => '2026-09-10', 'title' => 'Septiembre-2026', 'default_unpaid' => 80000],
                ['col' => 14, 'date' => '2026-10-10', 'title' => 'Octubre-2026',    'default_unpaid' => 80000],
                ['col' => 15, 'date' => '2026-11-10', 'title' => 'Noviembre-2026',  'default_unpaid' => 80000],
                ['col' => 16, 'date' => '2026-12-10', 'title' => 'Diciembre-2026',  'default_unpaid' => 80000],
            ];

            if ($dryRun) {
                if ($deudaAnterior > 0) {
                    $totalDebtsFoundCount++;
                    $totalDebtAmount += $deudaAnterior;
                }
                foreach ($installmentsDef as $inst) {
                    $rawVal = $row[$inst['col']] ?? '';
                    $cleanVal = (float) preg_replace('/[^0-9.]/', '', $rawVal);
                    $totalPaymentsCreated++;
                    if ($cleanVal > 0) {
                        $totalRecordsCreated++;
                        $totalAmountCollected += $cleanVal;
                    }
                }

                continue;
            }

            // Operación real en base de datos protegida por transacción por estudiante
            DB::transaction(function () use ($user, $row, $installmentsDef, $deudaAnterior, &$totalDebtsFoundCount, &$totalDebtAmount, &$totalPaymentsCreated, &$totalRecordsCreated, &$totalAmountCollected) {
                // Procesar DEUDA ANTERIOR si existe
                if ($deudaAnterior > 0) {
                    $saldo2025 = UserPayment::where('user_id', $user->id)
                        ->whereDate('date', '2025-12-10')
                        ->first();

                    if (! $saldo2025) {
                        UserPayment::create([
                            'user_id' => $user->id,
                            'date' => '2025-12-10',
                            'title' => 'Saldo-2025',
                            'amount' => $deudaAnterior,
                            'paid' => 0,
                        ]);
                    } else {
                        $saldo2025->update([
                            'title' => 'Saldo-2025',
                            'amount' => $deudaAnterior,
                        ]);
                    }

                    $totalDebtsFoundCount++;
                    $totalDebtAmount += $deudaAnterior;
                }

                foreach ($installmentsDef as $inst) {
                    $rawVal = $row[$inst['col']] ?? '';
                    $cleanVal = (float) preg_replace('/[^0-9.]/', '', $rawVal);
                    $dueDate = $inst['date'];
                    $title = $inst['title'];

                    $isPaid = $cleanVal > 0;
                    $installmentAmount = $isPaid ? $cleanVal : $inst['default_unpaid'];
                    $paidAmount = $isPaid ? $cleanVal : 0;

                    // Buscar o crear la cuota en userpayments
                    $userPayment = UserPayment::where('user_id', $user->id)
                        ->whereDate('date', $dueDate)
                        ->first();

                    if (! $userPayment) {
                        $userPayment = UserPayment::create([
                            'user_id' => $user->id,
                            'date' => $dueDate,
                            'title' => $title,
                            'amount' => $installmentAmount,
                            'paid' => $paidAmount,
                        ]);
                        $totalPaymentsCreated++;
                    } else {
                        // Si ya existe la cuota, actualizarla con los datos del CSV
                        $userPayment->update([
                            'title' => $title,
                            'amount' => $installmentAmount,
                            'paid' => $paidAmount,
                        ]);
                    }

                    // Si está pagada, registrar la transacción en paymentrecords si no existe
                    if ($isPaid) {
                        $existingRecord = PaymentRecord::where('user_id', $user->id)
                            ->where('userpayments_id', $userPayment->id)
                            ->first();

                        if (! $existingRecord) {
                            $paymentDate = Carbon::parse($dueDate)->setTime(10, 0, 0);

                            PaymentRecord::create([
                                'user_id' => $user->id,
                                'userpayments_id' => $userPayment->id,
                                'paymentBox' => 1,
                                'description' => "Pago importado CSV $title",
                                'paymentAmount' => $cleanVal,
                                'created_at' => $paymentDate,
                                'updated_at' => $paymentDate,
                            ]);
                            $totalRecordsCreated++;
                            $totalAmountCollected += $cleanVal;
                        }
                    }
                }
            });
        }

        $bar->finish();
        $this->newLine(2);

        // Resumen
        $this->info('✨ Proceso completado exitosamente:');
        $this->table(['Métrica', 'Cantidad'], [
            ['Estudiantes encontrados en SAE', $foundCount],
            ['Estudiantes no encontrados (omitidos)', $notFoundCount],
            ['Estudiantes con Deuda Anterior (Saldo-2025)', $totalDebtsFoundCount],
            ['Monto total de Deuda Anterior registrada', '$ '.number_format($totalDebtAmount, 2, ',', '.')],
            ['Cuotas procesadas / aseguradas en userpayments', $totalPaymentsCreated],
            ['Pagos registrados en paymentrecords', $totalRecordsCreated],
            ['Monto total recaudado importado', '$ '.number_format($totalAmountCollected, 2, ',', '.')],
        ]);

        if (! empty($notFoundStudents)) {
            $this->newLine();
            $this->warn('⚠️  Primeros 15 estudiantes no encontrados en la base de datos (pueden cargarse o asociarse manualmente):');
            foreach (array_slice($notFoundStudents, 0, 15) as $nf) {
                $this->line("   - $nf");
            }
            if (count($notFoundStudents) > 15) {
                $this->line('   ... y '.(count($notFoundStudents) - 15).' más.');
            }
        }

        return 0;
    }

    /**
     * Asegura la creación de los 3 planes de pago principales de 2026.
     */
    protected function ensurePaymentPlans(bool $dryRun): array
    {
        $plansDefinition = [
            [
                'title' => 'Cuotas 2026 (Estándar)',
                'inscripcion' => 100000,
                'mar_jul' => 70000,
                'ago_dic' => 80000,
            ],
            [
                'title' => 'Cuotas 2026 (Hermanos)',
                'inscripcion' => 100000,
                'mar_jul' => 60000,
                'ago_dic' => 70000,
            ],
            [
                'title' => 'Cuotas 2026 (Ingresantes)',
                'inscripcion' => 120000,
                'mar_jul' => 70000,
                'ago_dic' => 80000,
            ],
        ];

        $createdPlans = [];

        foreach ($plansDefinition as $def) {
            $existing = PaymentPlan::where('title', $def['title'])->first();

            if ($existing) {
                $createdPlans[] = $existing;
                $this->line("   ✓ Plan existente: <info>{$def['title']}</info>");

                continue;
            }

            if ($dryRun) {
                $this->line("   [Simulación] Se crearía plan: <info>{$def['title']}</info>");

                continue;
            }

            $plan = PaymentPlan::create(['title' => $def['title']]);

            // Detalle 0: Inscripción
            PaymentPlanDetail::create([
                'plans_master_id' => $plan->id,
                'date' => '2026-02-10',
                'title' => 'Ins-2026',
                'amount' => $def['inscripcion'],
            ]);

            // Detalles 1 a 5: Marzo a Julio
            $meses1 = [
                ['date' => '2026-03-10', 'title' => 'Marzo-2026'],
                ['date' => '2026-04-10', 'title' => 'Abril-2026'],
                ['date' => '2026-05-10', 'title' => 'Mayo-2026'],
                ['date' => '2026-06-10', 'title' => 'Junio-2026'],
                ['date' => '2026-07-10', 'title' => 'Julio-2026'],
            ];
            foreach ($meses1 as $m) {
                PaymentPlanDetail::create([
                    'plans_master_id' => $plan->id,
                    'date' => $m['date'],
                    'title' => $m['title'],
                    'amount' => $def['mar_jul'],
                ]);
            }

            // Detalles 6 a 10: Agosto a Diciembre
            $meses2 = [
                ['date' => '2026-08-10', 'title' => 'Agosto-2026'],
                ['date' => '2026-09-10', 'title' => 'Septiembre-2026'],
                ['date' => '2026-10-10', 'title' => 'Octubre-2026'],
                ['date' => '2026-11-10', 'title' => 'Noviembre-2026'],
                ['date' => '2026-12-10', 'title' => 'Diciembre-2026'],
            ];
            foreach ($meses2 as $m) {
                PaymentPlanDetail::create([
                    'plans_master_id' => $plan->id,
                    'date' => $m['date'],
                    'title' => $m['title'],
                    'amount' => $def['ago_dic'],
                ]);
            }

            $this->info("   + Creado plan: <info>{$def['title']}</info> con sus 11 cuotas.");
            $createdPlans[] = $plan;
        }

        return $createdPlans;
    }
}
