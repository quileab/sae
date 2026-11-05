<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds with default system configurations.
     */
    public function run(): void
    {
        $configs = [
            // General / Main
            [
                'id' => 'cycle',
                'group' => 'main',
                'description' => 'Año Ciclo Lectivo',
                'type' => 'text',
                'value' => date('Y'),
            ],
            [
                'id' => 'shortname',
                'group' => 'main',
                'description' => 'Nombre corto',
                'type' => 'text',
                'value' => 'ISPI 4013',
            ],
            [
                'id' => 'longname',
                'group' => 'main',
                'description' => 'Nombre Largo',
                'type' => 'text',
                'value' => 'Instituto Superior Particular Incorporado N° 4013',
            ],
            [
                'id' => 'prefix',
                'group' => 'main',
                'description' => 'Prefijo para archivos generados por el sistema',
                'type' => 'text',
                'value' => '4013-',
            ],
            [
                'id' => 'shift_type',
                'group' => 'main',
                'description' => 'Tipo de jornada escolar',
                'type' => 'text',
                'value' => 'simple',
            ],

            // Labels
            [
                'id' => 'label_career',
                'group' => 'labels',
                'description' => 'Etiqueta para Carrera/Año',
                'type' => 'text',
                'value' => 'Carrera',
            ],
            [
                'id' => 'label_careers',
                'group' => 'labels',
                'description' => 'Etiqueta para Carreras/Años (Plural)',
                'type' => 'text',
                'value' => 'Carreras',
            ],
            [
                'id' => 'label_subject',
                'group' => 'labels',
                'description' => 'Etiqueta para Materia',
                'type' => 'text',
                'value' => 'Materia',
            ],
            [
                'id' => 'label_subjects',
                'group' => 'labels',
                'description' => 'Etiqueta para Materias (Plural)',
                'type' => 'text',
                'value' => 'Materias',
            ],

            // Inscriptions
            [
                'id' => 'mesasExamen',
                'group' => 'inscriptions',
                'description' => 'Inscripciones a Mesas de Examen',
                'type' => 'bool',
                'value' => 'false',
            ],
            [
                'id' => 'modality',
                'group' => 'inscriptions',
                'description' => 'Inscripción a Modalidades',
                'type' => 'bool',
                'value' => 'false',
            ],
            [
                'id' => 'promoDirecta',
                'group' => 'inscriptions',
                'description' => 'Inscripciones a Promoción Directa',
                'type' => 'bool',
                'value' => 'false',
            ],

            // Institute
            [
                'id' => 'exams',
                'group' => 'institute',
                'description' => 'Inscripciones a Mesas de Julio',
                'type' => 'bool',
                'value' => 'false',
            ],
            [
                'id' => 'modalities',
                'group' => 'institute',
                'description' => 'Inscripción a Modalidad de Cursado',
                'type' => 'bool',
                'value' => 'false',
            ],
            [
                'id' => 'inscriptions',
                'group' => 'institute',
                'description' => 'Inscripciones al Ciclo Lectivo',
                'type' => 'bool',
                'value' => 'false',
            ],

            // Pre-enrollment
            [
                'id' => 'pre_enrollment_open',
                'group' => 'pre_enrollment',
                'description' => 'Preinscripción abierta (formulario público)',
                'type' => 'bool',
                'value' => 'true',
            ],

            // Payments
            [
                'id' => 'pre_enrollment_fee',
                'group' => 'payments',
                'description' => 'Monto Arancel de Preinscripción ($)',
                'type' => 'text',
                'value' => '0',
            ],

            // AFIP
            [
                'id' => 'afip_cert',
                'group' => 'afip',
                'description' => 'Nombre del certificado (.crt) para AFIP en storage/app/certs/',
                'type' => 'text',
                'value' => 'qbCert.crt',
            ],
            [
                'id' => 'afip_key',
                'group' => 'afip',
                'description' => 'Nombre de la clave privada (.key) para AFIP en storage/app/certs/',
                'type' => 'text',
                'value' => 'qbPrivate.key',
            ],
            [
                'id' => 'cuit',
                'group' => 'afip',
                'description' => 'CUIT de la institución para facturación electrónica AFIP',
                'type' => 'text',
                'value' => '20000000001',
            ],
            [
                'id' => 'production',
                'group' => 'afip',
                'description' => 'Modo AFIP (Off: Testing/Homologación, On: Producción)',
                'type' => 'bool',
                'value' => 'false',
            ],
        ];

        foreach ($configs as $config) {
            DB::table('configs')->updateOrInsert(
                ['id' => $config['id']],
                array_merge($config, [
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ])
            );
        }

        cache()->flush();
    }
}
