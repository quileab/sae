<?php

use App\Enums\RoleGroups;
use App\Enums\UserRole;
use App\Models\DailyAttendance;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Grade;
use App\Models\User;
use App\Models\UserPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

new class extends Component {
    use Toast;

    public ?int $selectedStudentId = null;
    public string $activeTab = 'asistencias';

    // Staff search mode (when an admin/director tests the portal)
    public string $staffStudentSearch = '';

    // Payment state
    public ?string $mpCheckoutUrl = null;
    public ?int $payingPaymentId = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->role === UserRole::Tutor->value) {
            $firstStudent = $user->students()->first();
            if ($firstStudent) {
                $this->selectedStudentId = $firstStudent->id;
            }
        } elseif ($user->role === UserRole::Student->value) {
            $this->selectedStudentId = $user->id;
        }
    }

    public function students(): Collection
    {
        $user = Auth::user();

        if ($user->role === UserRole::Tutor->value) {
            return $user->students;
        }

        if ($user->isStaff()) {
            if (strlen(trim($this->staffStudentSearch)) >= 2) {
                return User::where('role', UserRole::Student->value)
                    ->where(function ($q) {
                        $q->where('firstname', 'like', "%{$this->staffStudentSearch}%")
                            ->orWhere('lastname', 'like', "%{$this->staffStudentSearch}%")
                            ->orWhere('name', 'like', "%{$this->staffStudentSearch}%");
                    })
                    ->limit(10)
                    ->get();
            }

            return User::where('role', UserRole::Student->value)
                ->when($this->selectedStudentId, fn ($q) => $q->where('id', $this->selectedStudentId))
                ->limit(5)
                ->get();
        }

        return collect([$user]);
    }

    public function selectStudent(int $id): void
    {
        $this->selectedStudentId = $id;
        $this->mpCheckoutUrl = null;
        $this->payingPaymentId = null;
    }

    public function selectedStudent(): ?User
    {
        if (! $this->selectedStudentId) {
            return null;
        }

        return User::with(['careers', 'enrollments.subject.career', 'medicalRecord'])->find($this->selectedStudentId);
    }

    public function attendances(): Collection
    {
        if (! $this->selectedStudentId) {
            return collect();
        }

        return DailyAttendance::where('user_id', $this->selectedStudentId)
            ->latest('date')
            ->limit(30)
            ->get();
    }

    public function grades(): Collection
    {
        if (! $this->selectedStudentId) {
            return collect();
        }

        return Grade::where('user_id', $this->selectedStudentId)
            ->with(['classSession.subject.career'])
            ->latest()
            ->limit(30)
            ->get();
    }

    public function enrollments(): Collection
    {
        if (! $this->selectedStudentId) {
            return collect();
        }

        return Enrollment::where('user_id', $this->selectedStudentId)
            ->with('subject.career')
            ->get();
    }

    public function payments(): Collection
    {
        if (! $this->selectedStudentId) {
            return collect();
        }

        return UserPayment::where('user_id', $this->selectedStudentId)
            ->orderBy('date', 'asc')
            ->get();
    }

    public function studentAnnouncements(): Collection
    {
        $student = $this->selectedStudent();
        if (! $student) {
            return collect();
        }

        return Event::announcements()
            ->published()
            ->forUser($student)
            ->latest('published_at')
            ->limit(20)
            ->get();
    }

    public function acknowledgeAnnouncement(int $announcementId): void
    {
        $announcement = Event::announcements()->find($announcementId);
        if ($announcement) {
            $announcement->acknowledgeFor(Auth::user());
            $this->success('Has confirmado la recepción y lectura de este comunicado.');
        }
    }

    public function payWithMercadoPago(int $paymentId): void
    {
        $payment = UserPayment::where('user_id', $this->selectedStudentId)->find($paymentId);
        if (! $payment) {
            $this->error('Cuota no encontrada.');

            return;
        }

        $pendingAmount = (float) $payment->amount - (float) $payment->paid;
        if ($pendingAmount <= 0) {
            $this->info('Esta cuota ya se encuentra saldada.');

            return;
        }

        $accessToken = config('mercadopago.access_token');
        if (empty($accessToken)) {
            $this->error('La pasarela de pago no está configurada actualmente.');

            return;
        }

        try {
            MercadoPagoConfig::setAccessToken($accessToken);
            $client = new PreferenceClient;

            $student = $this->selectedStudent();
            $preference = $client->create([
                'items' => [
                    [
                        'id' => (string) $payment->id,
                        'title' => $payment->title ?: 'Cuota Escolar',
                        'quantity' => 1,
                        'unit_price' => $pendingAmount,
                        'currency_id' => 'ARS',
                    ],
                ],
                'payer' => [
                    'name' => Auth::user()->firstname,
                    'surname' => Auth::user()->lastname,
                    'email' => Auth::user()->email,
                ],
                'external_reference' => "up-{$payment->id}",
                'back_urls' => [
                    'success' => route('mercadopago.success'),
                    'failure' => route('mercadopago.failure'),
                    'pending' => route('mercadopago.pending'),
                ],
                'auto_return' => 'approved',
            ]);

            $this->payingPaymentId = $payment->id;
            $this->mpCheckoutUrl = $preference->init_point;
            $this->success('Enlace de pago generado con éxito.');
        } catch (\Throwable $e) {
            $this->error('Error al generar la preferencia de pago: '.$e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    {{-- HEADER --}}
    <x-header title="Portal Familias" subtitle="Seguimiento de cursada, asistencia, cuotas y circulares escolares" separator progress-indicator>
        @if(auth()->user()->isStaff())
            <x-slot:middle class="!justify-end flex items-center gap-2">
                <span class="badge badge-warning badge-sm">Vista Staff</span>
                <x-input 
                    placeholder="Buscar estudiante..." 
                    wire:model.live.debounce.300ms="staffStudentSearch" 
                    icon="o-magnifying-glass" 
                    class="input-sm" 
                    clear 
                />
            </x-slot:middle>
        @endif
    </x-header>

    @php
        $availableStudents = $this->students();
        $student = $this->selectedStudent();
    @endphp

    {{-- SELECTOR DE ESTUDIANTE --}}
    @if($availableStudents->isEmpty() && auth()->user()->role === App\Enums\UserRole::Tutor->value)
        <x-alert icon="o-exclamation-triangle" class="alert-warning shadow-lg">
            <div>
                <h3 class="font-bold">No tienes estudiantes vinculados</h3>
                <div class="text-xs">Tu cuenta de tutor aún no tiene alumnos asociados. Comunícate con la administración de la institución para que vinculen a tu representado.</div>
            </div>
        </x-alert>
    @else
        @if($availableStudents->count() > 1 || auth()->user()->isStaff())
            <div class="flex items-center gap-3 overflow-x-auto pb-2">
                <span class="text-xs font-semibold text-base-content/70 whitespace-nowrap">Estudiante:</span>
                @foreach ($availableStudents as $st)
                    <button 
                        type="button" 
                        wire:click="selectStudent({{ $st->id }})" 
                        class="btn btn-sm {{ $selectedStudentId === $st->id ? 'btn-primary' : 'btn-outline' }} gap-2"
                    >
                        <x-icon name="o-academic-cap" class="w-4 h-4" />
                        <span>{{ $st->fullname }}</span>
                        <span class="text-xs opacity-75">({{ $st->name }})</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if($student)
            {{-- TARJETA RESUMEN DEL ALUMNO --}}
            <x-card class="bg-base-100 border border-base-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <x-avatar :image="$student->avatar_url" class="!w-14 !h-14" />
                        <div>
                            <h2 class="text-xl font-bold">{{ $student->fullname }}</h2>
                            <p class="text-xs text-base-content/70">
                                DNI: <span class="font-semibold">{{ $student->name }}</span> | 
                                Email: {{ $student->email }}
                                @if($student->phone) | Tel: {{ $student->phone }} @endif
                            </p>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @forelse ($student->careers as $c)
                                    <span class="badge badge-primary badge-xs">{{ $c->name }}</span>
                                @empty
                                    <span class="badge badge-ghost badge-xs">Sin carrera asignada</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @php
                        $pendingPayments = $this->payments()->filter(fn($p) => (float)$p->amount > (float)$p->paid);
                        $totalDebt = $pendingPayments->sum(fn($p) => (float)$p->amount - (float)$p->paid);
                        $attendancesList = $this->attendances();
                        $totalAtt = $attendancesList->count();
                        $presentCount = $attendancesList->filter(fn($a) => in_array(strtolower($a->status), ['present', 'presente', 'p']))->count();
                        $attRate = $totalAtt > 0 ? round(($presentCount / $totalAtt) * 100) : 100;
                    @endphp

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-center">
                        <div class="bg-base-200/50 p-2.5 rounded-xl border border-base-200">
                            <div class="text-xs text-base-content/60">Asistencia</div>
                            <div class="text-lg font-black {{ $attRate >= 75 ? 'text-success' : 'text-error' }}">{{ $attRate }}%</div>
                            <div class="text-[10px] text-base-content/50">{{ $presentCount }}/{{ $totalAtt }} días</div>
                        </div>
                        <div class="bg-base-200/50 p-2.5 rounded-xl border border-base-200">
                            <div class="text-xs text-base-content/60">Materias</div>
                            <div class="text-lg font-black text-primary">{{ $this->enrollments()->count() }}</div>
                            <div class="text-[10px] text-base-content/50">Inscriptas</div>
                        </div>
                        <div class="col-span-2 md:col-span-1 bg-base-200/50 p-2.5 rounded-xl border border-base-200">
                            <div class="text-xs text-base-content/60">Cuotas Pendientes</div>
                            <div class="text-lg font-black {{ $totalDebt > 0 ? 'text-warning' : 'text-success' }}">
                                ${{ number_format($totalDebt, 0, ',', '.') }}
                            </div>
                            <div class="text-[10px] text-base-content/50">{{ $pendingPayments->count() }} cuotas</div>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- TABS DE DETALLE --}}
            <x-tabs wire:model="activeTab" class="bg-base-100 rounded-xl shadow-sm border border-base-200">
                {{-- TAB ASISTENCIAS --}}
                <x-tab name="asistencias" label="Asistencias" icon="o-calendar-days">
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-base">Registro de Asistencias Recientes</h3>
                            <span class="text-xs text-base-content/60">Últimos 30 registros</span>
                        </div>

                        @if($attendancesList->isNotEmpty())
                            <div class="overflow-x-auto border border-base-200 rounded-lg">
                                <table class="table table-sm w-full">
                                    <thead class="bg-base-200/60">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Observación / Justificación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($attendancesList as $att)
                                            <tr class="hover:bg-base-200/30">
                                                <td class="font-semibold">
                                                    {{ \Carbon\Carbon::parse($att->date)->format('d/m/Y') }}
                                                    <span class="text-xs text-base-content/50 block font-normal">{{ \Carbon\Carbon::parse($att->date)->translatedFormat('l') }}</span>
                                                </td>
                                                <td>
                                                    @php
                                                        $stLower = strtolower($att->status);
                                                        $badge = match(true) {
                                                            in_array($stLower, ['present', 'presente', 'p']) => ['badge-success text-white', 'Presente'],
                                                            in_array($stLower, ['absent', 'ausente', 'a']) => ['badge-error text-white', 'Ausente'],
                                                            in_array($stLower, ['late', 'tardanza', 'tarde', 't']) => ['badge-warning', 'Tardanza'],
                                                            in_array($stLower, ['justified', 'justificada', 'j']) => ['badge-info text-white', 'Justificada'],
                                                            default => ['badge-ghost', $att->status],
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $badge[0] }} badge-sm">{{ $badge[1] }}</span>
                                                </td>
                                                <td class="text-xs text-base-content/70">
                                                    {{ $att->observations ?: '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8 text-base-content/50 border-2 border-dashed border-base-300 rounded-xl">
                                <x-icon name="o-calendar-days" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                <p class="text-xs">No hay registros de asistencia cargados recientemente para este alumno.</p>
                            </div>
                        @endif
                    </div>
                </x-tab>

                {{-- TAB CALIFICACIONES & MATERIAS --}}
                <x-tab name="calificaciones" label="Materias y Calificaciones" icon="o-academic-cap">
                    <div class="p-4 space-y-6">
                        <div>
                            <h3 class="font-bold text-base mb-3">Materias Inscriptas</h3>
                            @php $enrList = $this->enrollments(); @endphp
                            @if($enrList->isNotEmpty())
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach ($enrList as $enr)
                                        <div class="p-3 rounded-xl border border-base-200 bg-base-200/20 flex items-center justify-between">
                                            <div>
                                                <div class="font-bold text-sm">{{ $enr->subject->name ?? 'Materia' }}</div>
                                                <div class="text-xs text-base-content/60">{{ $enr->subject->career->name ?? '' }}</div>
                                            </div>
                                            <div class="text-right">
                                                @if($enr->final_grade)
                                                    <div class="text-sm font-black text-primary">Nota: {{ $enr->final_grade }}</div>
                                                    <div class="text-[10px] text-base-content/50">{{ $enr->final_grade_date ? \Carbon\Carbon::parse($enr->final_grade_date)->format('d/m/Y') : 'Final' }}</div>
                                                @else
                                                    <span class="badge badge-outline badge-sm capitalize">{{ $enr->status }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-base-content/50 italic">No registra inscripciones a materias en curso.</p>
                            @endif
                        </div>

                        <div>
                            <h3 class="font-bold text-base mb-3">Últimas Calificaciones y Evaluaciones</h3>
                            @php $gradesList = $this->grades(); @endphp
                            @if($gradesList->isNotEmpty())
                                <div class="overflow-x-auto border border-base-200 rounded-lg">
                                    <table class="table table-sm w-full">
                                        <thead class="bg-base-200/60">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Materia</th>
                                                <th>Tema / Sesión</th>
                                                <th class="text-right">Calificación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($gradesList as $gr)
                                                <tr class="hover:bg-base-200/30">
                                                    <td class="text-xs whitespace-nowrap">{{ $gr->created_at->format('d/m/Y') }}</td>
                                                    <td class="font-semibold text-sm">{{ $gr->classSession->subject->name ?? '-' }}</td>
                                                    <td class="text-xs text-base-content/70">{{ $gr->classSession->topic ?? 'Clase' }}</td>
                                                    <td class="text-right font-black text-primary text-base">
                                                        {{ $gr->grade }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-6 text-base-content/50 border border-dashed border-base-300 rounded-xl">
                                    <p class="text-xs">No hay calificaciones registradas aún en el sistema.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-tab>

                {{-- TAB CUOTAS Y MERCADO PAGO --}}
                <x-tab name="pagos" label="Cuotas & Pagos" icon="o-banknotes">
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-base">Estado de Cuenta y Cuotas</h3>
                                <p class="text-xs text-base-content/60">Abona de forma segura en línea a través de Mercado Pago.</p>
                            </div>
                            @if($mpCheckoutUrl)
                                <a href="{{ $mpCheckoutUrl }}" target="_blank" class="btn btn-sm btn-success text-white gap-2">
                                    <x-icon name="o-credit-card" class="w-4 h-4" />
                                    <span>Completar Pago en Mercado Pago</span>
                                </a>
                            @endif
                        </div>

                        @php $paymentsList = $this->payments(); @endphp
                        @if($paymentsList->isNotEmpty())
                            <div class="overflow-x-auto border border-base-200 rounded-lg">
                                <table class="table table-sm w-full">
                                    <thead class="bg-base-200/60">
                                        <tr>
                                            <th>Vencimiento</th>
                                            <th>Concepto</th>
                                            <th>Monto</th>
                                            <th>Pagado</th>
                                            <th>Estado</th>
                                            <th class="w-24 text-right">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($paymentsList as $pay)
                                            @php
                                                $pending = (float)$pay->amount - (float)$pay->paid;
                                                $isPaid = $pending <= 0;
                                            @endphp
                                            <tr class="hover:bg-base-200/30">
                                                <td class="font-semibold text-xs whitespace-nowrap">
                                                    {{ $pay->date ? $pay->date->format('d/m/Y') : '-' }}
                                                </td>
                                                <td class="font-medium text-sm">{{ $pay->title ?: 'Cuota' }}</td>
                                                <td class="text-sm font-semibold">${{ number_format($pay->amount, 2, ',', '.') }}</td>
                                                <td class="text-sm text-base-content/70">${{ number_format($pay->paid, 2, ',', '.') }}</td>
                                                <td>
                                                    @if($isPaid)
                                                        <span class="badge badge-success text-white badge-xs">Al día</span>
                                                    @elseif($pay->paid > 0)
                                                        <span class="badge badge-warning badge-xs">Pago parcial</span>
                                                    @else
                                                        <span class="badge badge-error text-white badge-xs">Pendiente</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    @if(! $isPaid)
                                                        <x-button 
                                                            label="Pagar" 
                                                            icon="o-credit-card" 
                                                            class="btn-primary btn-xs" 
                                                            wire:click="payWithMercadoPago({{ $pay->id }})" 
                                                            spinner="payWithMercadoPago({{ $pay->id }})" 
                                                        />
                                                    @else
                                                        <span class="text-xs text-success flex items-center justify-end gap-1">
                                                            <x-icon name="o-check-circle" class="w-4 h-4" /> Pagado
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8 text-base-content/50 border-2 border-dashed border-base-300 rounded-xl">
                                <x-icon name="o-banknotes" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                <p class="text-xs">No hay cuotas ni planes de pago asignados a este estudiante.</p>
                            </div>
                        @endif
                    </div>
                </x-tab>

                {{-- TAB CIRCULARES & AVISOS --}}
                <x-tab name="circulares" label="Avisos & Circulares" icon="o-megaphone">
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-bold text-base">Comunicaciones Institucionales</h3>
                            <a href="{{ route('announcements.index') }}" class="text-xs text-primary underline">Ver muro completo</a>
                        </div>

                        @php $annList = $this->studentAnnouncements(); @endphp
                        @if($annList->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($annList as $ann)
                                    @php
                                        $isAck = $ann->isAcknowledgedBy(auth()->user());
                                        $priorityClass = match($ann->priority) {
                                            'urgent' => 'border-l-4 border-l-error bg-error/5',
                                            'important' => 'border-l-4 border-l-warning bg-warning/5',
                                            default => 'border border-base-200 bg-base-100',
                                        };
                                    @endphp
                                    <div class="p-4 rounded-xl shadow-xs {{ $priorityClass }} space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    @if($ann->priority === 'urgent')
                                                        <span class="badge badge-error text-white badge-xs">Urgente</span>
                                                    @elseif($ann->priority === 'important')
                                                        <span class="badge badge-warning badge-xs">Importante</span>
                                                    @endif
                                                    <h4 class="font-bold text-base">{{ $ann->title }}</h4>
                                                </div>
                                                <div class="text-[11px] text-base-content/50">
                                                    Publicado por {{ $ann->user->fullname ?? 'Dirección' }} el {{ $ann->published_at ? $ann->published_at->format('d/m/Y H:i') : $ann->created_at->format('d/m/Y H:i') }}
                                                </div>
                                            </div>

                                            @if($ann->requires_acknowledgment)
                                                @if($isAck)
                                                    <span class="badge badge-success text-white badge-sm flex items-center gap-1">
                                                        <x-icon name="o-check" class="w-3.5 h-3.5" /> Acuse recibido
                                                    </span>
                                                @else
                                                    <x-button 
                                                        label="Acusar Recibo" 
                                                        icon="o-check-circle" 
                                                        class="btn-warning btn-sm" 
                                                        wire:click="acknowledgeAnnouncement({{ $ann->id }})" 
                                                        spinner="acknowledgeAnnouncement({{ $ann->id }})" 
                                                    />
                                                @endif
                                            @endif
                                        </div>

                                        <div class="text-xs text-base-content/80 whitespace-pre-line leading-relaxed">
                                            {{ $ann->description }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-base-content/50 border-2 border-dashed border-base-300 rounded-xl">
                                <x-icon name="o-megaphone" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                                <p class="text-xs">No hay avisos ni circulares vigentes dirigidas a este estudiante.</p>
                            </div>
                        @endif
                    </div>
                </x-tab>

                {{-- TAB SALUD ESCOLAR --}}
                <x-tab name="salud" label="Salud Escolar" icon="o-heart">
                    @php $med = $student->medicalRecord; @endphp
                    <div class="p-4 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-base-200 pb-3">
                            <div>
                                <h3 class="font-bold text-base flex items-center gap-2">
                                    <x-icon name="o-shield-check" class="w-5 h-5 text-primary" />
                                    <span>Ficha Médica y Salud Escolar</span>
                                </h3>
                                <p class="text-xs text-base-content/60">Información para atención de emergencias y actividades físicas escolares</p>
                            </div>

                            @if($med?->has_physical_fitness_certificate)
                                @if($med->isPhysicalFitnessValid())
                                    <span class="badge badge-success text-white badge-sm gap-1 self-start sm:self-auto">
                                        <x-icon name="o-check-badge" class="w-3.5 h-3.5" /> Apto Físico Vigente
                                    </span>
                                @else
                                    <span class="badge badge-error text-white badge-sm gap-1 self-start sm:self-auto">
                                        <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5" /> Apto Físico Vencido
                                    </span>
                                @endif
                            @else
                                <span class="badge badge-warning badge-sm gap-1 self-start sm:self-auto">
                                    <x-icon name="o-clock" class="w-3.5 h-3.5" /> Sin Apto Físico Registrado
                                </span>
                            @endif
                        </div>

                        @if($med)
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {{-- COBERTURA MÉDICA --}}
                                <div class="bg-base-200/40 p-3.5 rounded-xl border border-base-200 space-y-2">
                                    <div class="font-bold text-xs text-primary uppercase tracking-wider flex items-center gap-1.5">
                                        <x-icon name="o-building-office-2" class="w-4 h-4" />
                                        <span>Cobertura y Obra Social</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Prestador:</span>
                                        <span class="font-semibold">{{ $med->healthcare_provider ?: 'No especificado' }}</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">N° de Afiliado / Credencial:</span>
                                        <span class="font-mono text-xs">{{ $med->healthcare_number ?: '-' }}</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Sanatorio de Referencia:</span>
                                        <span>{{ $med->emergency_hospital ?: '-' }}</span>
                                    </div>
                                </div>

                                {{-- DATOS CLÍNICOS --}}
                                <div class="bg-base-200/40 p-3.5 rounded-xl border border-base-200 space-y-2">
                                    <div class="font-bold text-xs text-primary uppercase tracking-wider flex items-center gap-1.5">
                                        <x-icon name="o-heart" class="w-4 h-4" />
                                        <span>Datos Clínicos</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Grupo Sanguíneo:</span>
                                        @if($med->blood_type)
                                            <span class="badge badge-error text-white font-bold">{{ $med->blood_type }}</span>
                                        @else
                                            <span class="text-base-content/40 italic text-xs">No informado</span>
                                        @endif
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Médico de Cabecera:</span>
                                        <span>{{ $med->doctor_name ?: '-' }}</span>
                                        @if($med->doctor_phone)
                                            <span class="text-xs text-primary block">Tel: {{ $med->doctor_phone }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Apto Físico Vence:</span>
                                        <span>{{ $med->physical_fitness_expires_at ? $med->physical_fitness_expires_at->format('d/m/Y') : 'Sin fecha fijada' }}</span>
                                    </div>
                                </div>

                                {{-- AUXILIO Y EMERGENCIA --}}
                                <div class="bg-base-200/40 p-3.5 rounded-xl border border-base-200 space-y-2">
                                    <div class="font-bold text-xs text-error uppercase tracking-wider flex items-center gap-1.5">
                                        <x-icon name="o-phone-arrow-up-right" class="w-4 h-4" />
                                        <span>Contacto de Urgencia</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Contacto:</span>
                                        <span class="font-semibold">{{ $med->emergency_contact_name ?: 'Familiares directos' }}</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="text-base-content/60 text-xs block">Teléfono de Auxilio:</span>
                                        @if($med->emergency_contact_phone)
                                            <a href="tel:{{ $med->emergency_contact_phone }}" class="btn btn-xs btn-error text-white gap-1 mt-0.5">
                                                <x-icon name="o-phone" class="w-3 h-3" /> {{ $med->emergency_contact_phone }}
                                            </a>
                                        @else
                                            <span class="text-base-content/40 italic text-xs">Sin teléfono directo</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- ALERTAS Y OBSERVACIONES --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <div class="p-3.5 rounded-xl border border-warning/30 bg-warning/5 space-y-1.5">
                                    <div class="font-bold text-xs text-warning flex items-center gap-1.5">
                                        <x-icon name="o-exclamation-triangle" class="w-4 h-4" />
                                        <span>Alergias y Enfermedades Crónicas</span>
                                    </div>
                                    <div class="text-xs">
                                        <strong>Alergias:</strong> {{ $med->allergies ?: 'Sin alergias conocidas registradas' }}
                                    </div>
                                    <div class="text-xs">
                                        <strong>Patologías / Asma / Diabetes:</strong> {{ $med->chronic_conditions ?: 'Ninguna declarada' }}
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-xl border border-info/30 bg-info/5 space-y-1.5">
                                    <div class="font-bold text-xs text-info flex items-center gap-1.5">
                                        <x-icon name="o-information-circle" class="w-4 h-4" />
                                        <span>Medicación y Alimentación</span>
                                    </div>
                                    <div class="text-xs">
                                        <strong>Medicación Habitual:</strong> {{ $med->current_medication ?: 'No toma medicación fija' }}
                                    </div>
                                    <div class="text-xs">
                                        <strong>Dieta / Comedor:</strong> {{ $med->dietary_restrictions ?: 'Sin restricciones dietarias' }}
                                    </div>
                                </div>
                            </div>

                            @if($med->observations)
                                <div class="bg-base-200/50 p-3 rounded-xl border border-base-200 text-xs text-base-content/80">
                                    <strong class="block text-base-content mb-0.5">Observaciones de la Institución:</strong>
                                    {{ $med->observations }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-10 text-base-content/50 border-2 border-dashed border-base-300 rounded-xl">
                                <x-icon name="o-heart" class="w-10 h-10 mx-auto mb-2 opacity-30" />
                                <h4 class="font-bold text-sm">Ficha Médica no completada</h4>
                                <p class="text-xs max-w-sm mx-auto mt-1">La institución aún no ha registrado los antecedentes médicos o cobertura para este estudiante.</p>
                            </div>
                        @endif
                    </div>
                </x-tab>
            </x-tabs>
        @endif
    @endif
</div>
