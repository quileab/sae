<?php

use App\Models\PreEnrollment;
use App\Services\AcademicCycle;
use App\Services\SecondaryPreEnrollmentPdfService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public')] class extends Component {
    public int $step = 1;
    public int $career_id = 1; // NO SETEADO
    public ?int $pre_enrollment_id = null;

    // Verificación 2FA (seguridad para modificación)
    public bool $requires_verification = false;
    public string $verification_phone = '';
    public ?string $verification_error = null;
    public bool $is_verified = false;
    public ?int $pending_pre_enrollment_id = null;

    // Alumno
    public $doc_type = 'DNI';
    public $doc_number = '';
    public $fname = '';
    public $lname = '';
    public $email = '';
    public $phone = '';
    public $phoneprovider = 'P';
    public $sexo = 'F';
    public $fnacimiento = '';
    public $nacionalidad = 'ARGENTINO/A NATIVO/A';
    public $nacimlocalid = '';
    public $nacimpais = 'Argentina';
    public $estadocivil = 'Soltero/a';
    public $puebloorig = 'No';
    public $etnia = '';
    public $comunrefer = '';
    public $dirStreet = '';
    public $dirNumber = '';
    public $dirFloor = '';
    public $dirAptmt = '';
    public $dirBlock = '';
    public $dirMonoBlock = '';
    public $dirNeighbor = '';
    public $dirCity = '';
    public $discapacidad = 'No';
    public $tipodisc = '-';
    public $cud = 'No';
    public $cud_expiry_date = '';
    public $disability_school = '';
    public $disability_school_start_date = '';

    // Procedencia
    public $escuela = '';
    public $division = '';
    public $turno = 'Mañana';
    public $repitente = 'No';

    // Padre/Madre 1
    public $padremadre1_firma = false;
    public $fname1 = '';
    public $lname1 = '';
    public $doctipo1 = 'DNI';
    public $docnum1 = '';
    public $email1 = '';
    public $phone1 = '';
    public $phoneprovider1 = 'P';
    public $sexo1 = 'F';
    public $fnacimiento1 = '';
    public $nacionalidad1 = 'ARGENTINO/A NATIVO/A';
    public $nacimlocalid1 = '';
    public $nacimpais1 = 'Argentina';
    public $estadocivil1 = 'Soltero/a';
    public $puebloorig1 = 'No';
    public $etnia1 = '';
    public $comunrefer1 = '';
    public $fallecido1 = 'No';
    public $fechFall1 = '';
    public $nivelInstr1 = 'Secundario Completo';
    public $dirStreet1 = '';
    public $dirNumber1 = '';
    public $dirFloor1 = '';
    public $dirAptmt1 = '';
    public $dirBlock1 = '';
    public $dirMonoBlock1 = '';
    public $dirNeighbor1 = '';
    public $dirCity1 = '';
    public $ocupacion1 = 'SIN DEFINIR';
    public $ocupStreet1 = '';
    public $ocupNumber1 = '';
    public $ocupCity1 = '';
    public $ocupPhone1 = '';
    public $ocuphorario1 = '';
    public $ocupestado1 = 0;

    // Padre/Madre 2
    public $padremadre2_firma = false;
    public $fname2 = '';
    public $lname2 = '';
    public $doctipo2 = 'DNI';
    public $docnum2 = '';
    public $email2 = '';
    public $phone2 = '';
    public $phoneprovider2 = 'P';
    public $sexo2 = 'M';
    public $fnacimiento2 = '';
    public $nacionalidad2 = 'ARGENTINO/A NATIVO/A';
    public $nacimlocalid2 = '';
    public $nacimpais2 = 'Argentina';
    public $estadocivil2 = 'Soltero/a';
    public $puebloorig2 = 'No';
    public $etnia2 = '';
    public $comunrefer2 = '';
    public $fallecido2 = 'No';
    public $fechFall2 = '';
    public $nivelInstr2 = 'Secundario Completo';
    public $dirStreet2 = '';
    public $dirNumber2 = '';
    public $dirFloor2 = '';
    public $dirAptmt2 = '';
    public $dirBlock2 = '';
    public $dirMonoBlock2 = '';
    public $dirNeighbor2 = '';
    public $dirCity2 = '';
    public $ocupacion2 = 'SIN DEFINIR';
    public $ocupStreet2 = '';
    public $ocupNumber2 = '';
    public $ocupCity2 = '';
    public $ocupPhone2 = '';
    public $ocuphorario2 = '';
    public $ocupestado2 = 0;

    // Tutor
    public $tutor_firma = false;
    public $tutorencargado = 'Tutor';
    public $parentesco = '';
    public $fname3 = '';
    public $lname3 = '';
    public $doctipo3 = 'DNI';
    public $docnum3 = '';
    public $email3 = '';
    public $phone3 = '';
    public $sexo3 = 'F';
    public $fnacimiento3 = '';
    public $nacionalidad3 = 'ARGENTINO/A NATIVO/A';
    public $nacimlocalid3 = '';
    public $nacimpais3 = 'Argentina';
    public $estadocivil3 = 'Soltero/a';
    public $puebloorig3 = 'No';
    public $etnia3 = '';
    public $comunrefer3 = '';
    public $nivelInstr3 = 'Secundario Completo';
    public $dirStreet3 = '';
    public $dirNumber3 = '';
    public $dirFloor3 = '';
    public $dirAptmt3 = '';
    public $dirBlock3 = '';
    public $dirMonoBlock3 = '';
    public $dirNeighbor3 = '';
    public $dirCity3 = '';
    public $ocupacion3 = 'SIN DEFINIR';
    public $ocupStreet3 = '';
    public $ocupNumber3 = '';
    public $ocupCity3 = '';
    public $ocupPhone3 = '';
    public $ocuphorario3 = '';
    public $ocupestado3 = 0;
    
    // Extra
    public $copaleche = false;
    public $atencHospitIns = false;
    public $salacinco = false;
    public $regIntern = false;
    public $regInternFuera = false;
    public $atencHospit = false;
    public $ambitorural = false;
    public $contexEncierro = false;
    public $menorJud = false;
    public $cen_det_prov = '';
    public $observaciones = '';
    public $motivoProcedencia = 'CAMBIO DE NIVEL';

    // Schools Search
    public array $schoolsSearch = [];

    public function searchSchool(string $value = ''): void
    {
        $this->schoolsSearch = [];
        $selected = $this->escuela;

        // Si ya hay una seleccionada, la agregamos para que el componente no la pierda
        if ($selected && strlen($value) === 0) {
            $this->schoolsSearch[] = ['id' => $selected, 'name' => $selected];
            return;
        }

        if (strlen($value) < 2) {
            return;
        }

        $value = strtoupper($value);
        $schools = cache()->remember('csv_escuelas_simple_v2', 3600, function () {
            $data = [];
            $filePath = resource_path('data/escuelas-simple.csv');
            if (!file_exists($filePath)) {
                $filePath = storage_path('app/escuelas-simple.csv');
            }
            if (file_exists($filePath)) {
                $file = fopen($filePath, 'r');
                if ($file) {
                    fgetcsv($file, 1000, ';'); // skip header
                    while (($row = fgetcsv($file, 1000, ';')) !== false) {
                        $name = trim($row[0] ?? '');
                        $city = trim($row[1] ?? '');
                        if ($name !== '') {
                            $data[] = ['name' => $name, 'city' => $city];
                        }
                    }
                    fclose($file);
                }
            }
            return $data;
        });

        $count = 0;
        foreach ($schools as $item) {
            $name = $item['name'];
            $city = $item['city'];
            if (str_contains(strtoupper($name), $value) || (is_numeric($value) && str_contains($name, $value))) {
                $this->schoolsSearch[] = [
                    'id' => $name,
                    'name' => $name . ($city ? ' (' . $city . ')' : '')
                ];
                $count++;
                if ($count >= 15) {
                    break;
                }
            }
        }
    }

    public function mount(): void
    {
        $this->searchSchool();
    }

    public function clearSchool(): void
    {
        $this->escuela = '';
        $this->schoolsSearch = [];
    }

    public function updatedPadremadre1Firma($value): void
    {
        if ($value) {
            $this->padremadre2_firma = false;
            $this->tutor_firma = false;
        }
    }

    public function updatedPadremadre2Firma($value): void
    {
        if ($value) {
            $this->padremadre1_firma = false;
            $this->tutor_firma = false;
        }
    }

    public function updatedTutorFirma($value): void
    {
        if ($value) {
            $this->padremadre1_firma = false;
            $this->padremadre2_firma = false;
        }
    }

    public function nextStep(): void
    {
        $this->step = min(5, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    protected function formFields(): array
    {
        return [
            'fname', 'lname', 'sexo', 'fnacimiento', 'nacionalidad', 'nacimlocalid', 'nacimpais',
            'estadocivil', 'puebloorig', 'etnia', 'comunrefer', 'phoneprovider',
            'dirStreet', 'dirNumber', 'dirFloor', 'dirAptmt', 'dirBlock', 'dirMonoBlock', 'dirNeighbor', 'dirCity',
            'discapacidad', 'tipodisc', 'cud', 'cud_expiry_date', 'disability_school', 'disability_school_start_date',
            'escuela', 'division', 'turno', 'repitente',
            'padremadre1_firma', 'fname1', 'lname1', 'doctipo1', 'docnum1', 'email1', 'phone1', 'phoneprovider1',
            'sexo1', 'fnacimiento1', 'nacionalidad1', 'nacimlocalid1', 'nacimpais1', 'estadocivil1',
            'puebloorig1', 'etnia1', 'comunrefer1', 'fallecido1', 'fechFall1', 'nivelInstr1',
            'dirStreet1', 'dirNumber1', 'dirFloor1', 'dirAptmt1', 'dirBlock1', 'dirMonoBlock1', 'dirNeighbor1', 'dirCity1',
            'ocupacion1', 'ocupStreet1', 'ocupNumber1', 'ocupCity1', 'ocupPhone1', 'ocuphorario1', 'ocupestado1',
            'padremadre2_firma', 'fname2', 'lname2', 'doctipo2', 'docnum2', 'email2', 'phone2', 'phoneprovider2',
            'sexo2', 'fnacimiento2', 'nacionalidad2', 'nacimlocalid2', 'nacimpais2', 'estadocivil2',
            'puebloorig2', 'etnia2', 'comunrefer2', 'fallecido2', 'fechFall2', 'nivelInstr2',
            'dirStreet2', 'dirNumber2', 'dirFloor2', 'dirAptmt2', 'dirBlock2', 'dirMonoBlock2', 'dirNeighbor2', 'dirCity2',
            'ocupacion2', 'ocupStreet2', 'ocupNumber2', 'ocupCity2', 'ocupPhone2', 'ocuphorario2', 'ocupestado2',
            'tutor_firma', 'tutorencargado', 'parentesco', 'fname3', 'lname3', 'doctipo3', 'docnum3', 'email3', 'phone3',
            'sexo3', 'fnacimiento3', 'nacionalidad3', 'nacimlocalid3', 'nacimpais3', 'estadocivil3',
            'puebloorig3', 'etnia3', 'comunrefer3', 'nivelInstr3',
            'dirStreet3', 'dirNumber3', 'dirFloor3', 'dirAptmt3', 'dirBlock3', 'dirMonoBlock3', 'dirNeighbor3', 'dirCity3',
            'ocupacion3', 'ocupStreet3', 'ocupNumber3', 'ocupCity3', 'ocupPhone3', 'ocuphorario3', 'ocupestado3',
            'copaleche', 'atencHospitIns', 'salacinco', 'regIntern', 'regInternFuera', 'atencHospit',
            'ambitorural', 'contexEncierro', 'menorJud', 'cen_det_prov', 'observaciones', 'motivoProcedencia'
        ];
    }

    public function autofill(): void
    {
        if (empty($this->doc_number)) {
            $this->cancelVerification();
            return;
        }

        // Si ya está verificado para este mismo ID, no volver a desafiar innecesariamente
        if ($this->is_verified && $this->pre_enrollment_id) {
            $current = PreEnrollment::find($this->pre_enrollment_id);
            if ($current && $current->doc_number === $this->doc_number && $current->doc_type === $this->doc_type) {
                return;
            }
        }

        $existing = PreEnrollment::where('doc_number', $this->doc_number)
            ->where('doc_type', $this->doc_type)
            ->where('cycle_id', AcademicCycle::enrollmentCycle())
            ->latest()
            ->first();

        if ($existing) {
            $this->pending_pre_enrollment_id = $existing->id;
            $this->requires_verification = true;
            $this->is_verified = false;
            $this->pre_enrollment_id = null;
            $this->verification_phone = '';
            $this->verification_error = null;
        } else {
            $this->cancelVerification();
        }
    }

    public function verifyPhone(): void
    {
        $this->verification_error = null;
        $entered = trim($this->verification_phone);

        if (strlen($entered) !== 4 || !ctype_digit($entered)) {
            $this->verification_error = 'Ingrese exactamente los 4 últimos dígitos numéricos.';
            return;
        }

        if (!$this->pending_pre_enrollment_id) {
            $this->cancelVerification();
            return;
        }

        $existing = PreEnrollment::find($this->pending_pre_enrollment_id);
        if (!$existing) {
            $this->cancelVerification();
            return;
        }

        $cleanPhone = preg_replace('/\D/', '', (string) ($existing->phone ?? ''));
        $expected = substr($cleanPhone, -4);

        if ($cleanPhone !== '' && $expected === $entered) {
            $this->pre_enrollment_id = $existing->id;
            $this->career_id = $existing->career_id ?? 1;
            $this->email = $existing->email ?? '';
            $this->phone = $existing->phone ?? '';

            $payload = $existing->payload ?? [];
            foreach ($this->formFields() as $field) {
                if (array_key_exists($field, $payload)) {
                    $this->$field = $payload[$field];
                }
            }

            if (!empty($payload['email'])) $this->email = $payload['email'];
            if (!empty($payload['phone'])) $this->phone = $payload['phone'];

            if (!empty($this->escuela)) {
                $this->searchSchool();
            }

            $this->is_verified = true;
            $this->requires_verification = false;
            $this->pending_pre_enrollment_id = null;
            $this->verification_phone = '';
            $this->verification_error = null;

            session()->flash('autofill_notice', 'Identidad verificada. Se cargaron los datos de la preinscripción existente para su modificación.');
            $this->dispatch('autofill-completed', message: 'Datos cargados para modificación');
        } else {
            $this->verification_error = 'Los 4 dígitos ingresados no coinciden con el teléfono registrado en la preinscripción.';
        }
    }

    public function cancelVerification(): void
    {
        $this->requires_verification = false;
        $this->pending_pre_enrollment_id = null;
        $this->verification_phone = '';
        $this->verification_error = null;
        $this->is_verified = false;
        $this->pre_enrollment_id = null;
    }

    public function save(): void
    {
        $fieldsToNormalize = ['fname', 'lname', 'fname1', 'lname1', 'fname2', 'lname2', 'fname3', 'lname3'];
        foreach ($fieldsToNormalize as $field) {
            if (!empty($this->$field)) {
                $this->$field = mb_convert_case(mb_strtolower(trim($this->$field), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            }
        }

        try {
            $this->validate([
                'doc_number' => 'required|string|max:20',
                'fname' => 'required|string|max:100',
                'lname' => 'required|string|max:100',
                'email' => 'required|email|max:100',
                'phone' => 'required|string|max:50',
                'fnacimiento' => 'required|date',
                'dirCity' => 'required|string|max:100',
                'escuela' => 'required|string|max:150',
                'division' => 'required|string|max:10',
                'email1' => 'nullable|email|max:100',
                'email2' => 'nullable|email|max:100',
                'email3' => 'nullable|email|max:100',
                'fnacimiento1' => 'nullable|date',
                'fnacimiento2' => 'nullable|date',
                'fnacimiento3' => 'nullable|date',
            ]);
        } catch (ValidationException $e) {
            $firstField = array_key_first($e->validator->failed());
            $stepMap = [
                'doc_number' => 1, 'fname' => 1, 'lname' => 1, 'email' => 1, 'phone' => 1, 'fnacimiento' => 1,
                'escuela' => 2, 'division' => 2,
                'fname1' => 3, 'docnum1' => 3, 'email1' => 3, 'fnacimiento1' => 3,
                'fname2' => 4, 'docnum2' => 4, 'fnacimiento2' => 4,
                'fname3' => 4, 'docnum3' => 4, 'fnacimiento3' => 4,
                'dirCity' => 5,
            ];
            $this->step = $stepMap[$firstField] ?? $this->step;
            $this->dispatch('validation-failed', field: $firstField);

            throw $e;
        }

        $payload = [];
        foreach ($this->formFields() as $field) {
            $payload[$field] = $this->$field;
        }
        $payload['email'] = $this->email;
        $payload['phone'] = $this->phone;
        $payload['level'] = 'secondary';

        if ($this->pre_enrollment_id) {
            $pre = PreEnrollment::find($this->pre_enrollment_id);
            if ($pre) {
                $pre->update([
                    'career_id' => $this->career_id,
                    'doc_type' => $this->doc_type,
                    'doc_number' => $this->doc_number,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'payload' => $payload,
                ]);
            } else {
                $pre = PreEnrollment::create([
                    'cycle_id' => AcademicCycle::enrollmentCycle(),
                    'career_id' => $this->career_id,
                    'doc_type' => $this->doc_type,
                    'doc_number' => $this->doc_number,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'payload' => $payload,
                    'status' => 'submitted',
                ]);
            }
        } else {
            $pre = PreEnrollment::create([
                'cycle_id' => AcademicCycle::enrollmentCycle(),
                'career_id' => $this->career_id,
                'doc_type' => $this->doc_type,
                'doc_number' => $this->doc_number,
                'email' => $this->email,
                'phone' => $this->phone,
                'payload' => $payload,
                'status' => 'submitted',
            ]);
        }

        $pdfPath = app(SecondaryPreEnrollmentPdfService::class)->generate($pre);
        $pre->update(['pdf_path' => $pdfPath]);

        session()->flash('success', $this->pre_enrollment_id ? '¡Preinscripción actualizada exitosamente!' : '¡Preinscripción enviada! Te contactaremos pronto.');
        session()->flash('pdf_url', URL::temporarySignedRoute('preinsc.pdf', now()->addHours(48), ['preEnrollment' => $pre->id]));
        $this->reset(['doc_number', 'email', 'phone', 'fname', 'lname', 'fnacimiento', 'dirCity', 'pre_enrollment_id']);
        $this->step = 6;
    }

    public function with(): array
    {
        $ocupacionesList = [
            "ADMINISTRADOR DE GRANDES ESTANCIAS", "ALTO FUNCIONARIO", "AMA DE CASA", "AMA DE CASA CON CUOTA ALIMENTARIA",
            "ARTESANO", "BOYERO", "BRASERO", "CAPATAZ DE EMPRESA RURAL", "CAPATAZ DE ESTANCIA", "CHANGARÍN", "COMERCIANTE",
            "DEPORTISTA", "DESOCUPADO", "DOCENTE", "EJECUTIVO", "EMPLEADO ADMINISTRATIVO", "EMPLEADO COMUNAL",
            "EMPLEADO PÚBLICO", "EMPRESARIO", "GERENTE",
            "GRAN ARRENDATARIO PROFESIONAL", "GRAN EMPRESARIO DE COMERCIO", "GRAN EMPRESARIO DE INDUSTRIA",
            "GRAN EMPRESARIO DE SERVICIOS", "GRAN PROPIETARIO RURAL", "JEFE DE HOGAR DESOCUPADO",
            "JEFE INTERMEDIO EN ADMIN. PRIVADA", "JEFE INTERMEDIO EN ADMIN. PÚBLICA", "MEDIANO ARRENDATARIO",
            "MEDIANO PROPIETARIO RURAL", "MEDIERO Y OTRAS FORMAS DE ARRENDAMIENTO", "MILITAR", "OBRERO CALIFICADO",
            "OBRERO NO CALIFICADO", "OFICIO CUENTA PROPIA SIN LOCAL NI PEÓN", "PEQUEÑO ARRENDATARIO",
            "PEQUEÑO COMERCIANTE AL MENUDEO", "PEQUEÑO PROPIETARIO MINIFUNDISTA", "PRODUCTOR AGROPECUARIO",
            "PROFESIONAL", "PROFESIONAL CTA. PROP C/LOCAL Y PERSONAL", "SERVICIO DE MAESTRANZA", "SERVICIO DOMÉSTICO",
            "SIN DEFINIR", "TÉCNICO", "TRANSPORTISTA", "VENDEDOR AMBULANTE"
        ];
        
        $opciones = array_map(function($o) {
            return ['id' => $o, 'name' => $o];
        }, $ocupacionesList);

        return [
            'listaOcupaciones' => $opciones,
            'empresasTelefonia' => [
                ['id' => 'P', 'name' => 'Personal'],
                ['id' => 'M', 'name' => 'Movistar'],
                ['id' => 'C', 'name' => 'Claro'],
                ['id' => 'T', 'name' => 'Tuenti'],
                ['id' => 'O', 'name' => 'Otra'],
            ],
            'listaTiposDocumento' => [
                ['id' => 'DNI', 'name' => 'DNI'],
                ['id' => 'PAS', 'name' => 'Pasaporte'],
                ['id' => 'LE', 'name' => 'LE'],
                ['id' => 'LC', 'name' => 'LC'],
            ],
            'listaEstadosOcupacion' => [
                ['id' => 0, 'name' => 'En Actividad'],
                ['id' => 1, 'name' => 'Jubilado'],
                ['id' => 2, 'name' => 'Pensionado Contributivo'],
                ['id' => 3, 'name' => 'Pensionado No Contributivo'],
            ],
            'listaParentescos' => [
                ['id' => 'Abuelo/a', 'name' => 'Abuelo/a'],
                ['id' => 'Tío/a', 'name' => 'Tío/a'],
                ['id' => 'Hermano/a', 'name' => 'Hermano/a'],
                ['id' => 'Primo/a', 'name' => 'Primo/a'],
                ['id' => 'Familiar', 'name' => 'Familiar'],
                ['id' => 'Tutor legal', 'name' => 'Tutor legal'],
                ['id' => 'Otro', 'name' => 'Otro'],
            ],
        ];
    }
};
?>

<div class="min-h-screen py-8 px-4 bg-cover bg-center bg-fixed relative" style="background-image: url('https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1920&q=80');">
    {{-- Overlay para legibilidad y consistencia con el tema --}}
    <div class="absolute inset-0 bg-base-300/80 backdrop-blur-xs"></div>

    <div class="relative max-w-4xl mx-auto p-4 sm:p-8 bg-base-100/95 backdrop-blur-md rounded-2xl shadow-2xl border border-base-content/10">
        @if($step === 6)
            <div class="text-center py-12">
                <x-icon name="o-check-circle" class="w-24 h-24 mx-auto text-success" />
                <h2 class="text-3xl font-bold mt-6">¡Preinscripción completada!</h2>
                <p class="mt-2 text-lg text-base-content/70">{{ session('success') }}</p>
                @if(session('pdf_url'))
                    <div class="mt-8 flex justify-center gap-4">
                        <x-button label="Descargar PDF" icon="o-document-arrow-down" class="btn-primary" link="{{ session('pdf_url') }}" external />
                <x-button label="Nueva Preinscripción" icon="o-arrow-path" class="btn-outline" wire:click="$set('step', 1)" />
                    </div>
                @endif
            </div>
        @else
            <div x-data="{ step: $wire.entangle('step') }">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-center text-primary">Preinscripciones Secundario {{ App\Services\AcademicCycle::enrollmentCycle() }}</h1>
                    <p class="text-center text-base-content/70 mt-1">Complete el formulario siendo lo más preciso posible.</p>

                    <!-- Stepper -->
                    <ul class="steps steps-horizontal w-full mt-6">
                        <li class="step" :class="step >= 1 && 'step-primary'">Alumno</li>
                        <li class="step" :class="step >= 2 && 'step-primary'">Procedencia</li>
                        <li class="step" :class="step >= 3 && 'step-primary'">Padre/Madre 1</li>
                        <li class="step" :class="step >= 4 && 'step-primary'">Padre 2 / Tutor</li>
                        <li class="step" :class="step >= 5 && 'step-primary'">Otros Datos</li>
                    </ul>
                </div>

                <form wire:submit="save" novalidate>
                <div x-show="step === 1" x-cloak>

                    <div class="space-y-4">
                        <h3 class="text-lg font-bold border-b pb-2">Datos Personales del Alumno</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-select label="Tipo Documento" wire:model.live="doc_type" :options="$listaTiposDocumento" option-value="id" option-label="name" />
                            <x-input label="Nº de Documento" wire:model.blur="doc_number" wire:blur="autofill" wire:keydown.enter="autofill" type="number" hint="Al salir del campo o presionar Enter se buscará si ya posee una inscripción" required />
                        </div>

                        {{-- Desafío de seguridad 2FA si existe una preinscripción previa --}}
                        @if ($requires_verification)
                            <div class="p-4 rounded-xl border border-warning/40 bg-warning/10 space-y-3">
                                <div class="flex items-start gap-3">
                                    <x-icon name="o-shield-exclamation" class="w-6 h-6 text-warning shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="font-bold text-sm text-base-content">Se detectó una inscripción existente</h4>
                                        <p class="text-xs text-base-content/80 mt-0.5">
                                            Para proteger sus datos personales y habilitar la modificación del registro, ingrese los <strong>últimos 4 dígitos del teléfono celular</strong> registrado previamente:
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row items-center gap-3 pt-1">
                                    <div class="w-full sm:w-48">
                                        <x-input 
                                            placeholder="Ej: 1234" 
                                            wire:model="verification_phone" 
                                            wire:keydown.enter="verifyPhone"
                                            maxlength="4" 
                                            class="input-sm text-center font-mono tracking-widest text-base"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2 w-full sm:w-auto">
                                        <x-button label="Verificar y Cargar" icon="o-check" class="btn-warning btn-sm" wire:click="verifyPhone" />
                                        <x-button label="Cancelar" class="btn-ghost btn-sm" wire:click="cancelVerification" />
                                    </div>
                                </div>

                                @if ($verification_error)
                                    <div class="text-xs font-semibold text-error flex items-center gap-1.5 pt-1">
                                        <x-icon name="o-x-circle" class="w-4 h-4 shrink-0" />
                                        <span>{{ $verification_error }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($is_verified && $pre_enrollment_id)
                            <div class="alert alert-success py-2 text-sm flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-check-badge" class="w-5 h-5 text-success" />
                                    <span>Identidad verificada. Modo de edición habilitado para esta inscripción.</span>
                                </div>
                                <span class="badge badge-success text-xs font-semibold">Modo edición</span>
                            </div>
                        @elseif (session('autofill_notice'))
                            <div class="alert alert-info py-2 text-sm flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-information-circle" class="w-5 h-5" />
                                    <span>{{ session('autofill_notice') }}</span>
                                </div>
                                <span class="badge badge-warning text-xs">Modo edición</span>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="Apellido" wire:model="lname" required />
                            <x-input label="Nombre/s"  wire:model="fname" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="E-Mail" type="email" wire:model="email" required />
                            <x-input label="Teléfono" type="tel" wire:model="phone" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-select label="Empresa Celular" wire:model="phoneprovider" :options="$empresasTelefonia" option-value="id" option-label="name" />
                            <x-select label="Sexo" wire:model="sexo" :options="[['id'=>'F','name'=>'Femenino'],['id'=>'M','name'=>'Masculino'],['id'=>'X','name'=>'Otro']]" option-value="id" option-label="name" />
                            <x-input label="Fecha de Nacimiento" type="date" wire:model="fnacimiento" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-input label="Nacionalidad"  wire:model="nacionalidad" />
                            <x-input label="Localidad de Nacimiento" wire:model="nacimlocalid" />
                            <x-input label="País de Nacimiento" wire:model="nacimpais" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-select label="Estado Civil" wire:model="estadocivil" :options="[['id'=>'Soltero/a','name'=>'Soltero/a'],['id'=>'Casado/a','name'=>'Casado/a'],['id'=>'Unión de Hecho','name'=>'Unión de Hecho'],['id'=>'Divorciado/a','name'=>'Divorciado/a'],['id'=>'Viudo/a','name'=>'Viudo/a']]" option-value="id" option-label="name" />
                            <x-select label="Discapacidad" wire:model="discapacidad" :options="[['id'=>'No','name'=>'No'],['id'=>'Si','name'=>'Si']]" option-value="id" option-label="name" />
                        </div>
                    </div>
</div>
                <div x-show="step === 2" x-cloak>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold border-b pb-2">Escuela de la que Proviene</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="relative">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="font-semibold text-sm">Proviene de Escuela</label>
                                    @if($escuela)
                                        <button type="button" wire:click="clearSchool" class="text-xs text-error hover:underline flex items-center gap-1 cursor-pointer">
                                            <x-icon name="o-x-mark" class="w-3.5 h-3.5" />
                                            <span>Cambiar / Limpiar</span>
                                        </button>
                                    @endif
                                </div>
                                <x-choices 
                                    wire:model="escuela" 
                                    :options="$schoolsSearch" 
                                    search-function="searchSchool" 
                                    option-label="name" 
                                    option-value="id" 
                                    placeholder="Escriba para buscar..."
                                    no-result-text="No se encontraron escuelas"
                                    single 
                                    searchable
                                    required
                                />
                            </div>
                            <x-input label="División" wire:model="division" placeholder="A, B, C..." required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-select label="Turno" wire:model="turno" :options="[['id'=>'Mañana','name'=>'Mañana'],['id'=>'Tarde','name'=>'Tarde']]" option-value="id" option-label="name" />
                            <x-select label="Repitente" wire:model="repitente" :options="[['id'=>'No','name'=>'No'],['id'=>'Si','name'=>'Si']]" option-value="id" option-label="name" />
                        </div>
                    </div>
</div>
                <div x-show="step === 3" x-cloak>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h3 class="text-lg font-bold">Datos del Padre / Madre 1</h3>
                            <x-checkbox label="Firmará los formularios" wire:model.live="padremadre1_firma" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <x-input label="Apellido" wire:model="lname1" />
                            <x-input label="Nombre/s"  wire:model="fname1" />
                            <x-select label="Sexo" wire:model="sexo1" :options="[['id'=>'F','name'=>'Femenino'],['id'=>'M','name'=>'Masculino'],['id'=>'X','name'=>'Otro']]" option-value="id" option-label="name" />
                            <x-select label="Estado Civil" wire:model="estadocivil1" :options="[['id'=>'Soltero/a','name'=>'Soltero/a'],['id'=>'Casado/a','name'=>'Casado/a'],['id'=>'Unión de Hecho','name'=>'Unión de Hecho'],['id'=>'Divorciado/a','name'=>'Divorciado/a'],['id'=>'Viudo/a','name'=>'Viudo/a']]" option-value="id" option-label="name" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <x-select label="Tipo Doc." wire:model="doctipo1" :options="$listaTiposDocumento" option-value="id" option-label="name" />
                            <x-input label="Nº de Documento" wire:model="docnum1" type="number" />
                            <x-input label="Fecha de Nacimiento" type="date" wire:model="fnacimiento1" />
                            <x-input label="E-Mail" type="email" wire:model="email1" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-input label="Teléfono" type="tel" wire:model="phone1" />
                            <x-select label="Empresa Celular" wire:model="phoneprovider1" :options="$empresasTelefonia" option-value="id" option-label="name" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-select label="Ocupación" wire:model="ocupacion1" :options="$listaOcupaciones" option-value="id" option-label="name" />
                            <x-select label="Estado Ocupacional" wire:model="ocupestado1" :options="$listaEstadosOcupacion" option-value="id" option-label="name" />
                            <x-input label="Lugar de Trabajo" wire:model="ocupCity1" />
                        </div>
                    </div>
</div>
                <div x-show="step === 4" x-cloak>
                    <div class="space-y-8">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b pb-2">
                                <h3 class="text-lg font-bold">Datos del Padre / Madre 2</h3>
                                <x-checkbox label="Firmará los formularios" wire:model.live="padremadre2_firma" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <x-input label="Apellido" wire:model="lname2" />
                                <x-input label="Nombre/s"  wire:model="fname2" />
                                <x-select label="Sexo" wire:model="sexo2" :options="[['id'=>'F','name'=>'Femenino'],['id'=>'M','name'=>'Masculino'],['id'=>'X','name'=>'Otro']]" option-value="id" option-label="name" />
                                <x-select label="Estado Civil" wire:model="estadocivil2" :options="[['id'=>'Soltero/a','name'=>'Soltero/a'],['id'=>'Casado/a','name'=>'Casado/a'],['id'=>'Unión de Hecho','name'=>'Unión de Hecho'],['id'=>'Divorciado/a','name'=>'Divorciado/a'],['id'=>'Viudo/a','name'=>'Viudo/a']]" option-value="id" option-label="name" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <x-select label="Tipo Doc." wire:model="doctipo2" :options="$listaTiposDocumento" option-value="id" option-label="name" />
                                <x-input label="Nº de Documento" wire:model="docnum2" type="number" />
                                <x-input label="Fecha de Nacimiento" type="date" wire:model="fnacimiento2" />
                                <x-input label="Teléfono" type="tel" wire:model="phone2" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <x-select label="Empresa Celular" wire:model="phoneprovider2" :options="$empresasTelefonia" option-value="id" option-label="name" />
                                <x-select label="Ocupación" wire:model="ocupacion2" :options="$listaOcupaciones" option-value="id" option-label="name" />
                                <x-select label="Estado Ocupacional" wire:model="ocupestado2" :options="$listaEstadosOcupacion" option-value="id" option-label="name" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-input label="Lugar de Trabajo" wire:model="ocupCity2" />
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b pb-2">
                                <h3 class="text-lg font-bold">Datos del Tutor</h3>
                                <x-checkbox label="Firmará los formularios" wire:model.live="tutor_firma" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <x-input label="Apellido" wire:model="lname3" />
                                <x-input label="Nombre/s"  wire:model="fname3" />
                                <x-select label="Sexo" wire:model="sexo3" :options="[['id'=>'F','name'=>'Femenino'],['id'=>'M','name'=>'Masculino'],['id'=>'X','name'=>'Otro']]" option-value="id" option-label="name" />
                                <x-select label="Estado Civil" wire:model="estadocivil3" :options="[['id'=>'Soltero/a','name'=>'Soltero/a'],['id'=>'Casado/a','name'=>'Casado/a'],['id'=>'Unión de Hecho','name'=>'Unión de Hecho'],['id'=>'Divorciado/a','name'=>'Divorciado/a'],['id'=>'Viudo/a','name'=>'Viudo/a']]" option-value="id" option-label="name" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <x-select label="Tipo Doc." wire:model="doctipo3" :options="$listaTiposDocumento" option-value="id" option-label="name" />
                                <x-input label="Nº de Documento" wire:model="docnum3" type="number" />
                                <x-input label="Fecha de Nacimiento" type="date" wire:model="fnacimiento3" />
                                <x-select label="Parentesco" wire:model="parentesco" :options="$listaParentescos" option-value="id" option-label="name" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <x-input label="Teléfono" type="tel" wire:model="phone3" />
                                <x-select label="Ocupación" wire:model="ocupacion3" :options="$listaOcupaciones" option-value="id" option-label="name" />
                                <x-select label="Estado Ocupacional" wire:model="ocupestado3" :options="$listaEstadosOcupacion" option-value="id" option-label="name" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-input label="Lugar de Trabajo" wire:model="ocupCity3" />
                                <x-select label="Tipo de Responsable" wire:model="tutorencargado" :options="[['id'=>'Tutor','name'=>'Tutor'],['id'=>'Encargado','name'=>'Encargado']]" option-value="id" option-label="name" />
                            </div>
                        </div>
                    </div>
</div>
                <div x-show="step === 5" x-cloak>
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold border-b pb-2">Domicilio del Alumno</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-input label="Calle" wire:model="dirStreet" />
                            <x-input label="Número"  wire:model="dirNumber" />
                            <x-input label="Localidad" wire:model="dirCity" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <x-input label="Piso" wire:model="dirFloor" />
                            <x-input label="Depto." wire:model="dirAptmt" />
                            <x-input label="Barrio" wire:model="dirNeighbor" />
                        </div>
                        
                        <h3 class="text-lg font-bold border-b pb-2 mt-6">Otros Datos</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <x-checkbox label="Copa de Leche" wire:model="copaleche" />
                            <x-checkbox label="Cursó Sala de 5" wire:model="salacinco" />
                            <x-checkbox label="Atención Hospitalaria/Dom. (Durante esta inscripción)" wire:model="atencHospitIns" />
                            <x-checkbox label="Atención Hospitalaria/Dom. (Año anterior)" wire:model="atencHospit" />
                            <x-checkbox label="Posee régimen de internado en el establecimiento" wire:model="regIntern" />
                            <x-checkbox label="Posee régimen de internado fuera del establecimiento" wire:model="regInternFuera" />
                            <x-checkbox label="Proviene de ámbito rural" wire:model="ambitorural" />
                            <x-checkbox label="Menor judicializado" wire:model="menorJud" />
                            <x-checkbox label="Alumno en contexto de encierro" wire:model="contexEncierro" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                            <x-input label="Centro de detención del que proviene" wire:model="cen_det_prov" />
                        </div>
                        <div class="mt-4">
                            <x-textarea label="Observaciones" wire:model="observaciones" />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-between items-center">
                    <div>
                        <x-button label="Anterior" icon="o-arrow-left" @click="step--" x-show="step > 1" class="btn-primary" />
                    </div>
                    <div>
                        <x-button label="Siguiente" icon-right="o-arrow-right" @click="step++" x-show="step < 5" class="btn-primary" />
                    <x-button label="Finalizar Preinscripción" icon="o-check" type="submit" class="btn-success" spinner="save" x-show="step === 5" />
                    </div>
                </div>
            </form>
            </div>
        @endif
    </div>
</div>


