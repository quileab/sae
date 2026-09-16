<?php

use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentAcademicStatusService;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

new class extends Component {
    #[Url(as: 'subject_id')]
    public $subject_id = null;
    
    public ?Subject $subject = null;

    public function mount($subject_id = null)
    {
        $user = auth()->user();
        
        if ($subject_id) {
            $this->subject_id = $subject_id;
        }

        if (! $this->subject_id) {
            $this->subject_id = session('current_subject_id') ?? ($user->subjects->first()->id ?? null);
        }

        if (! $this->subject_id) {
            return $this->redirect('/class-sessions', navigate: true);
        }

        $this->subject = Subject::findOrFail($this->subject_id);
        
        // Persist subject context
        session()->put('current_subject_id', $this->subject_id);
    }

    #[Computed]
    public function students()
    {
        // Get active enrollments
        $enrollments = Enrollment::where('subject_id', $this->subject_id)
            ->where('status', 'active')
            ->get();
            
        $userIds = $enrollments->pluck('user_id');
        
        $users = User::whereIn('id', $userIds)
            ->where('role', 'student')
            ->orderBy('lastname', 'asc')
            ->orderBy('firstname', 'asc')
            ->get();
            
        $service = app(EnrollmentAcademicStatusService::class);
        $attendanceData = $service->calculate($enrollments);
        
        $studentsList = [];
        
        foreach ($users as $user) {
            $enrollment = $enrollments->where('user_id', $user->id)->first();
            $status = $enrollment ? ($attendanceData[$enrollment->id] ?? null) : null;
            
            $studentsList[] = [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'attendance_percentage' => $status ? $status['attendance'] : 0,
            ];
        }
        
        return collect($studentsList);
    }
}; ?>

<div>
    <x-header title="Directorio de Estudiantes" subtitle="{{ $subject->name }}" separator>
        <x-slot:actions>
            <x-button label="Volver a Sesiones" icon="o-arrow-left" link="/class-sessions" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    @if($this->students->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info shadow-sm">No hay estudiantes matriculados activos en esta materia.</x-alert>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($this->students as $student)
                @php
                    $attendance = $student['attendance_percentage'];
                    $progressColor = 'progress-success';
                    if ($attendance < 50) $progressColor = 'progress-error';
                    elseif ($attendance < 75) $progressColor = 'progress-warning';
                    
                    $initials = mb_strtoupper(mb_substr($student['firstname'], 0, 1, 'UTF-8') . mb_substr($student['lastname'], 0, 1, 'UTF-8'), 'UTF-8');
                @endphp
                
                <x-card wire:key="student-{{ $student['id'] }}" class="shadow-sm border border-base-200 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <img src="{{ $student['avatar_url'] }}" class="w-12 h-12 rounded-full object-cover shrink-0 border border-base-300" />
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h3 class="text-lg font-bold truncate text-base-content" title="{{ $student['lastname'] }}, {{ $student['firstname'] }}">
                                    {{ $student['lastname'] }}, {{ $student['firstname'] }}
                                </h3>
                                <x-button icon="o-chat-bubble-left-ellipsis" link="/chat?user_id={{ $student['id'] }}" class="btn-circle btn-ghost btn-sm text-primary -mt-1 -mr-2" tooltip-left="Chat Interno" />
                            </div>
                            
                            <div class="mt-2 space-y-2 text-sm text-base-content/80">
                                @if($student['phone'])
                                    <div class="flex items-center gap-2 truncate">
                                        <x-icon name="o-phone" class="w-4 h-4 text-primary shrink-0" />
                                        <a href="tel:{{ $student['phone'] }}" class="hover:text-primary transition-colors truncate">{{ $student['phone'] }}</a>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 truncate opacity-50">
                                        <x-icon name="o-phone" class="w-4 h-4 shrink-0" />
                                        <span class="italic">Sin teléfono</span>
                                    </div>
                                @endif
                                
                                @if($student['email'])
                                    <div class="flex items-center gap-2 truncate">
                                        <x-icon name="o-envelope" class="w-4 h-4 text-primary shrink-0" />
                                        <a href="mailto:{{ $student['email'] }}" class="hover:text-primary transition-colors truncate" title="{{ $student['email'] }}">{{ $student['email'] }}</a>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 truncate opacity-50">
                                        <x-icon name="o-envelope" class="w-4 h-4 shrink-0" />
                                        <span class="italic">Sin correo</span>
                                    </div>
                                @endif
                            </div>
                            
                            @php
                                $textColor = 'text-success';
                                if ($attendance < 50) $textColor = 'text-error';
                                elseif ($attendance < 75) $textColor = 'text-warning';
                            @endphp
                            
                            <div class="mt-4">
                                <div class="flex justify-between items-center mb-1 text-xs font-bold uppercase tracking-widest">
                                    <span class="opacity-80">Asistencia</span>
                                    <span class="{{ $textColor }}">{{ $attendance }}%</span>
                                </div>
                                <progress class="progress {{ $progressColor }} w-full" value="{{ $attendance }}" max="100"></progress>
                            </div>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
