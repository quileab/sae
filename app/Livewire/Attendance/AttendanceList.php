<?php

namespace App\Livewire\Attendance;

use App\Models\Career;
use App\Models\Config;
use App\Models\DailyAttendance;
use App\Models\Event;
use App\Models\JustifiedAbsence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.attendance')]
class AttendanceList extends Component
{
    use Toast;

    public ?int $careerId = null;

    public string $date = '';

    /** @var Collection<int, User> */
    public Collection|array $students = [];

    /** @var array<int, array<string, mixed>> */
    public array $attendances = [];

    public array $justifications = [];

    public array $recessEvents = [];

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403);
        }

        $this->date = Carbon::today()->toDateString();

        $careers = $this->accessibleCareers;
        if (! $this->careerId && $careers->isNotEmpty()) {
            $this->careerId = $careers->first()->id;
        }

        if ($this->careerId) {
            $this->loadStudents();
            $this->loadAttendances();
        }
    }

    #[Computed]
    public function accessibleCareers(): Collection
    {
        $user = auth()->user();
        $query = Career::where('allow_enrollments', true)
            ->where('allow_evaluations', true);

        if ($user->hasAnyRole(['admin', 'director', 'administrative'])) {
            return $query->orderBy('id')->get();
        }

        return $user->careers()
            ->where('allow_enrollments', true)
            ->where('allow_evaluations', true)
            ->orderBy('id')->get();
    }

    public function updatedCareerId(): void
    {
        $this->loadStudents();
        $this->loadAttendances();
    }

    public function updatedDate(): void
    {
        $this->loadAttendances();
    }

    public function loadStudents(): void
    {
        $this->students = User::query()
            ->where('role', 'student')
            ->where('enabled', true)
            ->whereHas('careers', fn ($q) => $q->where('careers.id', $this->careerId))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();
    }

    public function loadAttendances(): void
    {
        $records = DailyAttendance::query()
            ->where('career_id', $this->careerId)
            ->whereDate('date', $this->date)
            ->get()
            ->keyBy('user_id');

        $this->attendances = [];
        foreach ($this->students as $student) {
            $record = $records->get($student['id']);
            $this->attendances[$student['id']] = [
                'status' => $record?->status ?? 'present',
                'note' => $record?->note ?? '',
            ];
        }

        $this->loadJustificationsAndRecess();
    }

    public function loadJustificationsAndRecess(): void
    {
        // 1. Load active recess events
        $this->recessEvents = Event::whereDate('start', '<=', $this->date)
            ->whereDate('end', '>=', $this->date)
            ->where(function ($query) {
                $query->where('title', 'like', '%receso%')
                    ->orWhere('title', 'like', '%feriado%')
                    ->orWhere('description', 'like', '%receso%')
                    ->orWhere('description', 'like', '%feriado%');
            })
            ->get()
            ->toArray();

        // 2. Load active justifications for loaded students
        if (! empty($this->students)) {
            $userIds = $this->students instanceof \Illuminate\Support\Collection
                ? $this->students->pluck('id')->toArray()
                : array_column($this->students, 'id');

            $this->justifications = JustifiedAbsence::whereDate('start_date', '<=', $this->date)
                ->whereDate('end_date', '>=', $this->date)
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id')
                ->toArray();
        } else {
            $this->justifications = [];
        }
    }

    public function setStatus(int $userId, string $status): void
    {
        if (isset($this->attendances[$userId])) {
            $this->attendances[$userId]['status'] = $status;
        }
    }

    public function save(): void
    {
        // IDOR Prevention: Ensure user has access to this career
        if (! $this->accessibleCareers->contains('id', $this->careerId)) {
            abort(403, 'No tienes permiso para registrar asistencia en esta carrera.');
        }

        $shiftType = Config::find('shift_type')?->value ?? 'simple';
        $recordedBy = auth()->id();

        foreach ($this->attendances as $userId => $data) {
            DailyAttendance::updateOrCreate(
                [
                    'career_id' => $this->careerId,
                    'user_id' => $userId,
                    'date' => $this->date,
                ],
                [
                    'recorded_by' => $recordedBy,
                    'status' => $data['status'],
                    'absence_value' => DailyAttendance::calculateAbsenceValue($data['status'], $shiftType),
                    'note' => $data['note'] ?: null,
                ]
            );
        }

        $this->success('Asistencia guardada.');
    }

    /** @return array<int, float> */
    #[Computed]
    public function absenceTotals(): array
    {
        if (empty($this->students)) {
            return [];
        }

        $userIds = $this->students instanceof \Illuminate\Support\Collection
            ? $this->students->pluck('id')->toArray()
            : array_column($this->students, 'id');

        return DailyAttendance::query()
            ->where('career_id', $this->careerId)
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(absence_value) as total')
            ->pluck('total', 'user_id')
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.attendance.attendance-list', [
            'careers' => $this->accessibleCareers,
            'totals' => $this->absenceTotals,
        ]);
    }
}
