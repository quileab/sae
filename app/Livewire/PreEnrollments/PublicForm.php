<?php

namespace App\Livewire\PreEnrollments;

use App\Models\Career;
use App\Models\PreEnrollment;
use App\Services\AcademicCycle;
use App\Services\PreEnrollmentPdfService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.public')]
class PublicForm extends Component
{
    public int $step = 1;

    // Step 1: Carrera
    public $career_id = '';

    // Step 2: Datos personales
    public $doc_type = 'DNI';

    public $doc_number = '';

    public $fname = '';

    public $lname = '';

    public $email = '';

    public $phone = '';

    public $sexo = 'F';

    public $fnacimiento = '';

    public $nacionalidad = 'ARGENTINO/A NATIVO/A';

    public $nacimlocalid = '';

    public $nacimpais = 'Argentina';

    public $estadocivil = 'Soltero/a';

    public $puebloorig = 'No';

    public $etnia = '';

    public $comunrefer = '';

    // Step 3: Domicilio
    public $dirStreet = '';

    public $dirNumber = '';

    public $dirFloor = '';

    public $dirAptmt = '';

    public $dirBlock = '';

    public $dirMonoBlock = '';

    public $dirNeighbor = '';

    public $dirCity = '';

    // Discapacidad
    public $discapacidad = 'No';

    public $tipodisc = '-';

    // Ocupación
    public $ocupacion = 'SIN DEFINIR';

    public $ocupStreet = '';

    public $ocupNumber = '';

    public $ocupCity = '';

    public $ocupPhone = '';

    public $ocuphorario = '';

    public $ocupestado = 0;

    // Otros
    public bool $respaspra = false;

    public bool $regIntern = false;

    public bool $regInternFuera = false;

    public bool $ambitorural = false;

    public bool $contexEncierro = false;

    public $motivoProcedencia = 'CAMBIO DE NIVEL';

    public $titulo = '';

    public $titotorgado = '';

    public $tityear = '';

    public $observaciones = '';

    public $canalinform = 'Redes Sociales';

    public $referido = '';

    public function nextStep(): void
    {
        $this->step = min(4, $this->step + 1);
    }

    public function autofill(): void
    {
        if (empty($this->doc_number)) {
            return;
        }

        $existing = PreEnrollment::where('doc_number', $this->doc_number)
            ->where('doc_type', $this->doc_type)
            ->where('cycle_id', AcademicCycle::enrollmentCycle())
            ->latest()
            ->first();

        if ($existing) {
            $payload = $existing->payload;
            $this->fname = $payload['fname'] ?? '';
            $this->lname = $payload['lname'] ?? '';
            $this->email = $existing->email ?? ($payload['email'] ?? '');
            $this->phone = $existing->phone ?? ($payload['phone'] ?? '');
            $this->sexo = $payload['sexo'] ?? 'F';
            $this->fnacimiento = $payload['fnacimiento'] ?? '';
            $this->nacionalidad = $payload['nacionalidad'] ?? 'ARGENTINO/A NATIVO/A';
            $this->nacimlocalid = $payload['nacimlocalid'] ?? '';
            $this->nacimpais = $payload['nacimpais'] ?? 'Argentina';
            $this->estadocivil = $payload['estadocivil'] ?? 'Soltero/a';
            $this->puebloorig = $payload['puebloorig'] ?? 'No';
            $this->etnia = $payload['etnia'] ?? '';
            $this->comunrefer = $payload['comunrefer'] ?? '';
            $this->dirStreet = $payload['dirStreet'] ?? '';
            $this->dirNumber = $payload['dirNumber'] ?? '';
            $this->dirFloor = $payload['dirFloor'] ?? '';
            $this->dirAptmt = $payload['dirAptmt'] ?? '';
            $this->dirBlock = $payload['dirBlock'] ?? '';
            $this->dirMonoBlock = $payload['dirMonoBlock'] ?? '';
            $this->dirNeighbor = $payload['dirNeighbor'] ?? '';
            $this->dirCity = $payload['dirCity'] ?? '';
            $this->discapacidad = $payload['discapacidad'] ?? 'No';
            $this->tipodisc = $payload['tipodisc'] ?? '-';
            $this->ocupacion = $payload['ocupacion'] ?? 'SIN DEFINIR';
            $this->ocupStreet = $payload['ocupStreet'] ?? '';
            $this->ocupNumber = $payload['ocupNumber'] ?? '';
            $this->ocupCity = $payload['ocupCity'] ?? '';
            $this->ocupPhone = $payload['ocupPhone'] ?? '';
            $this->ocuphorario = $payload['ocuphorario'] ?? '';
            $this->ocupestado = $payload['ocupestado'] ?? 0;
            $this->respaspra = $payload['respaspra'] ?? false;
            $this->regIntern = $payload['regIntern'] ?? false;
            $this->regInternFuera = $payload['regInternFuera'] ?? false;
            $this->ambitorural = $payload['ambitorural'] ?? false;
            $this->contexEncierro = $payload['contexEncierro'] ?? false;
            $this->motivoProcedencia = $payload['motivoProcedencia'] ?? 'CAMBIO DE NIVEL';
            $this->titulo = $payload['titulo'] ?? '';
            $this->titotorgado = $payload['titotorgado'] ?? '';
            $this->tityear = $payload['tityear'] ?? '';
            $this->observaciones = $payload['observaciones'] ?? '';
            $this->canalinform = $payload['canalinform'] ?? 'Redes Sociales';
            $this->referido = $payload['referido'] ?? '';

            $this->dispatch('autofill-completed', message: 'Datos cargados de inscripción previa');
        }
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function save(): void
    {
        try {
            $this->validate([
                'career_id' => 'required|exists:careers,id',
                'doc_number' => 'required|string|max:20',
                'fname' => 'required|string|max:100',
                'lname' => 'required|string|max:100',
                'email' => 'required|email',
                'phone' => 'required|string|max:50',
                'fnacimiento' => 'required|date',
                'dirCity' => 'required|string|max:100',
                'titulo' => 'nullable|string|max:250',
            ]);
        } catch (ValidationException $e) {
            $firstField = array_key_first($e->validator->failed());
            $stepMap = [
                'career_id' => 1, 'doc_number' => 2, 'doc_type' => 2, 'fname' => 2, 'lname' => 2,
                'email' => 2, 'phone' => 2, 'sexo' => 2, 'fnacimiento' => 2, 'nacionalidad' => 2,
                'nacimlocalid' => 2, 'nacimpais' => 2, 'estadocivil' => 2,
                'dirStreet' => 3, 'dirNumber' => 3, 'dirCity' => 3, 'discapacidad' => 3, 'ocupacion' => 3,
                'titulo' => 4,
            ];
            $this->step = $stepMap[$firstField] ?? $this->step;
            $this->dispatch('validation-failed', field: $firstField);

            throw $e;
        }

        $payload = collect(get_object_vars($this))->except(['step', 'career_id', 'doc_type', 'doc_number', 'email', 'phone'])->toArray();

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

        $pdfPath = app(PreEnrollmentPdfService::class)->generate($pre);
        $pre->update(['pdf_path' => $pdfPath]);

        session()->flash('success', '¡Preinscripción enviada! Te contactaremos pronto.');
        session()->flash('pdf_url', URL::temporarySignedRoute('preinsc.pdf', now()->addHours(48), ['preEnrollment' => $pre->id]));
        session()->flash('preinsc_career_id', $pre->career_id);
        $this->reset(['doc_number', 'email', 'phone', 'fname', 'lname', 'fnacimiento', 'dirCity', 'titulo']);
        $this->step = 5;
    }

    public function render()
    {
        return view('livewire.pre-enrollments.public-form', [
            'careers' => Career::where('allow_enrollments', true)->orderBy('name')->get(),
            'nacionalidades' => ['ARGENTINO/A NATIVO/A', 'ARGENTINO/A NATURALIZADO/A', 'ARGENTINO/A POR OPCIÓN', 'EXTRANJERO/A'],
            'estadosCiviles' => ['Soltero/a', 'Casado/a', 'Unión de Hecho', 'Divorciado/a', 'Viudo/a'],
            'discapacidades' => ['-', 'AUDITIVA - HIPOACUSIA', 'AUDITIVA - SORDERA', 'MENTAL - INTELECTUAL', 'MOTORA - MOTORA PURA', 'MOTORA - NEURO-MOTORA', 'OTROS - MÁS DE UNA DISCAPACIDAD', 'OTROS - TRASTORNOS DEL ESPECTRO AUTISTA (TEA)', 'VISUAL - CEGUERA', 'VISUAL - DISMINUCIÓN VISUAL'],
            'ocupaciones' => ['SIN DEFINIR', 'AMA DE CASA', 'COMERCIANTE', 'DESOCUPADO', 'DOCENTE', 'EMPLEADO PUBLICO', 'ESTUDIANTE', 'PROFESIONAL', 'TECNICO'],
            'ocupEstados' => [0 => 'En Actividad', 1 => 'Jubilado', 2 => 'Pensionado Contributivo', 3 => 'Pensionado No Contributivo'],
            'motivos' => ['CAMBIO DE NIVEL', 'CAMBIO DE DOMICILIO', 'OTRA CAUSA', 'TRABAJO DEL ALUMNO'],
            'canales' => ['Redes Sociales', 'Por un amigo/a', 'Búsqueda en Google', 'Radio / TV', 'Ya soy estudiante', 'Mi Universidad / Colegio / Empresa'],
        ]);
    }
}
