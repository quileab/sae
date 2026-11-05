<?php

use App\Models\MedicalRecord;
use App\Models\User;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public int $userId;
    public bool $isSelfManagement = false;

    public string $blood_type = '';
    public string $healthcare_provider = '';
    public string $healthcare_number = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';
    public string $emergency_hospital = '';
    public string $doctor_name = '';
    public string $doctor_phone = '';
    public string $allergies = '';
    public string $chronic_conditions = '';
    public string $current_medication = '';
    public string $dietary_restrictions = '';
    public bool $has_physical_fitness_certificate = false;
    public ?string $physical_fitness_expires_at = null;
    public string $observations = '';

    public function mount(int $userId, bool $isSelfManagement = false): void
    {
        $this->userId = $userId;
        $this->isSelfManagement = $isSelfManagement;
        $this->loadRecord();
    }

    public function loadRecord(): void
    {
        $record = MedicalRecord::where('user_id', $this->userId)->first();

        if ($record) {
            $this->blood_type = $record->blood_type ?? '';
            $this->healthcare_provider = $record->healthcare_provider ?? '';
            $this->healthcare_number = $record->healthcare_number ?? '';
            $this->emergency_contact_name = $record->emergency_contact_name ?? '';
            $this->emergency_contact_phone = $record->emergency_contact_phone ?? '';
            $this->emergency_hospital = $record->emergency_hospital ?? '';
            $this->doctor_name = $record->doctor_name ?? '';
            $this->doctor_phone = $record->doctor_phone ?? '';
            $this->allergies = $record->allergies ?? '';
            $this->chronic_conditions = $record->chronic_conditions ?? '';
            $this->current_medication = $record->current_medication ?? '';
            $this->dietary_restrictions = $record->dietary_restrictions ?? '';
            $this->has_physical_fitness_certificate = (bool) $record->has_physical_fitness_certificate;
            $this->physical_fitness_expires_at = $record->physical_fitness_expires_at?->format('Y-m-d');
            $this->observations = $record->observations ?? '';
        }
    }

    public function save(): void
    {
        // IDOR Prevention: If self-managing, user must be the authenticated user
        if ($this->isSelfManagement && $this->userId !== (int) auth()->id()) {
            abort(403, 'No tienes autorización para editar esta ficha médica.');
        }

        $this->validate([
            'blood_type' => 'nullable|string|max:10',
            'healthcare_provider' => 'nullable|string|max:150',
            'healthcare_number' => 'nullable|string|max:100',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_hospital' => 'nullable|string|max:150',
            'doctor_name' => 'nullable|string|max:150',
            'doctor_phone' => 'nullable|string|max:50',
            'allergies' => 'nullable|string',
            'chronic_conditions' => 'nullable|string',
            'current_medication' => 'nullable|string',
            'dietary_restrictions' => 'nullable|string',
            'has_physical_fitness_certificate' => 'boolean',
            'physical_fitness_expires_at' => 'nullable|date',
            'observations' => 'nullable|string',
        ]);

        MedicalRecord::updateOrCreate(
            ['user_id' => $this->userId],
            [
                'blood_type' => $this->blood_type ?: null,
                'healthcare_provider' => $this->healthcare_provider ?: null,
                'healthcare_number' => $this->healthcare_number ?: null,
                'emergency_contact_name' => $this->emergency_contact_name ?: null,
                'emergency_contact_phone' => $this->emergency_contact_phone ?: null,
                'emergency_hospital' => $this->emergency_hospital ?: null,
                'doctor_name' => $this->doctor_name ?: null,
                'doctor_phone' => $this->doctor_phone ?: null,
                'allergies' => $this->allergies ?: null,
                'chronic_conditions' => $this->chronic_conditions ?: null,
                'current_medication' => $this->current_medication ?: null,
                'dietary_restrictions' => $this->dietary_restrictions ?: null,
                'has_physical_fitness_certificate' => $this->has_physical_fitness_certificate,
                'physical_fitness_expires_at' => $this->physical_fitness_expires_at ?: null,
                'observations' => $this->observations ?: null,
            ]
        );

        $this->success('Ficha médica actualizada con éxito.');
    }
}; ?>

<div class="mt-4">
    <x-card :title="$isSelfManagement ? 'Mi Ficha Médica y Salud' : 'Ficha Médica y Salud Escolar'" shadow separator>
        <x-slot:menu>
            <div class="flex items-center gap-2">
                @if($has_physical_fitness_certificate)
                    @php
                        $isFitnessValid = empty($physical_fitness_expires_at) || \Carbon\Carbon::parse($physical_fitness_expires_at)->isFuture() || \Carbon\Carbon::parse($physical_fitness_expires_at)->isToday();
                    @endphp
                    @if($isFitnessValid)
                        <span class="badge badge-success text-white badge-xs gap-1 font-semibold">
                            <x-icon name="o-check-badge" class="w-3 h-3" /> Apto Físico Vigente
                        </span>
                    @else
                        <span class="badge badge-error text-white badge-xs gap-1 font-semibold">
                            <x-icon name="o-exclamation-triangle" class="w-3 h-3" /> Apto Físico Vencido
                        </span>
                    @endif
                @else
                    <span class="badge badge-warning badge-xs gap-1 font-semibold">
                        <x-icon name="o-clock" class="w-3 h-3" /> Sin Apto Físico
                    </span>
                @endif
            </div>
        </x-slot:menu>

        <x-form wire:submit.prevent="save" no-separator class="space-y-4">
            {{-- SECCIÓN 1: COBERTURA Y EMERGENCIAS --}}
            <div class="rounded-xl bg-base-200/40 p-4 border border-base-200 space-y-3">
                <div class="flex items-center gap-2 font-bold text-sm text-primary">
                    <x-icon name="o-shield-check" class="w-4 h-4" />
                    <span>Cobertura Médica y Asistencia de Emergencia</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-input
                        label="Obra Social / Prepaga"
                        placeholder="Ej. OSDE, IOMA, Swiss Medical..."
                        icon="o-building-office-2"
                        wire:model="healthcare_provider"
                    />

                    <x-input
                        label="N° de Afiliado / Credencial"
                        placeholder="Ej. 12345678/01"
                        icon="o-identification"
                        wire:model="healthcare_number"
                    />

                    <x-input
                        label="Sanatorio / Hospital de Referencia"
                        placeholder="Ej. Hospital de Niños, Clínica Central"
                        icon="o-map-pin"
                        wire:model="emergency_hospital"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    <x-input
                        label="Contacto de Emergencia Médica"
                        placeholder="Nombre y relación (ej. Abuela Marta)"
                        icon="o-user"
                        wire:model="emergency_contact_name"
                    />

                    <x-input
                        label="Teléfono de Emergencia Inmediata"
                        placeholder="Ej. 351-1234567"
                        icon="o-phone"
                        type="tel"
                        wire:model="emergency_contact_phone"
                    />
                </div>
            </div>

            {{-- SECCIÓN 2: DATOS CLÍNICOS Y TRATAMIENTOS --}}
            <div class="rounded-xl bg-base-200/40 p-4 border border-base-200 space-y-3">
                <div class="flex items-center gap-2 font-bold text-sm text-primary">
                    <x-icon name="o-heart" class="w-4 h-4" />
                    <span>Perfil Clínico y Alergias</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-select
                        label="Grupo Sanguíneo y Factor"
                        icon="o-beaker"
                        :options="[
                            ['id' => '0+', 'name' => '0 Positivo (0+)'],
                            ['id' => '0-', 'name' => '0 Negativo (0-)'],
                            ['id' => 'A+', 'name' => 'A Positivo (A+)'],
                            ['id' => 'A-', 'name' => 'A Negativo (A-)'],
                            ['id' => 'B+', 'name' => 'B Positivo (B+)'],
                            ['id' => 'B-', 'name' => 'B Negativo (B-)'],
                            ['id' => 'AB+', 'name' => 'AB Positivo (AB+)'],
                            ['id' => 'AB-', 'name' => 'AB Negativo (AB-)'],
                        ]"
                        option-value="id"
                        option-label="name"
                        placeholder="Seleccionar grupo sanguíneo..."
                        wire:model="blood_type"
                    />

                    <x-input
                        label="Médico de Cabecera / Pediatra"
                        placeholder="Ej. Dr. Martínez"
                        icon="o-user-plus"
                        wire:model="doctor_name"
                    />

                    <x-input
                        label="Teléfono del Médico"
                        placeholder="Ej. 351-9876543"
                        icon="o-phone"
                        type="tel"
                        wire:model="doctor_phone"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-textarea
                        label="Alergias (medicamentos, alimentos, picaduras)"
                        placeholder="Detallar alergias conocidas (ej. Penicilina, maní, polen)..."
                        wire:model="allergies"
                        rows="2"
                    />

                    <x-textarea
                        label="Enfermedades Crónicas / Antecedentes"
                        placeholder="Ej. Asma bronquial, Diabetes, Epilepsia, Celiaquía..."
                        wire:model="chronic_conditions"
                        rows="2"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-textarea
                        label="Medicación Habitual o de Urgencia"
                        placeholder="Detallar fármacos diarios o indicados ante crisis..."
                        wire:model="current_medication"
                        rows="2"
                    />

                    <x-textarea
                        label="Restricciones Dietarias / Comedor Escolar"
                        placeholder="Ej. Celíaco, intolerancia a la lactosa, vegetariano..."
                        wire:model="dietary_restrictions"
                        rows="2"
                    />
                </div>
            </div>

            {{-- SECCIÓN 3: APTO FÍSICO Y OBSERVACIONES --}}
            <div class="rounded-xl bg-base-200/40 p-4 border border-base-200 space-y-3">
                <div class="flex items-center gap-2 font-bold text-sm text-primary">
                    <x-icon name="o-document-check" class="w-4 h-4" />
                    <span>Aptitud Física{{ $isSelfManagement ? ' y Cuidados Particulares' : ' y Observaciones para Preceptores' }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center bg-base-100 p-3 rounded-lg border border-base-200">
                    <x-toggle
                        label="Certificado de Apto Físico Entregado"
                        hint="Acreditación médica obligatoria para educación física"
                        right
                        wire:model.live="has_physical_fitness_certificate"
                        class="toggle-success"
                    />

                    <x-input
                        label="Fecha de Vencimiento del Apto"
                        type="date"
                        icon="o-calendar"
                        wire:model="physical_fitness_expires_at"
                        :disabled="!$has_physical_fitness_certificate"
                    />
                </div>

                <x-textarea
                    :label="$isSelfManagement ? 'Observaciones o Indicaciones Especiales' : 'Observaciones Generales / Recomendaciones'"
                    :placeholder="$isSelfManagement ? 'Indicaciones adicionales que la institución o docentes deban conocer ante cualquier urgencia...' : 'Información adicional relevante para docentes, directivos o preceptores...'"
                    wire:model="observations"
                    rows="2"
                />
            </div>

            <x-slot:actions>
                <div class="flex justify-end gap-2 w-full">
                    <x-button
                        label="Guardar Ficha Médica"
                        icon="o-check"
                        class="btn-primary"
                        type="submit"
                        spinner="save"
                    />
                </div>
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
