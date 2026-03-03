<?php

namespace App\Livewire\Attendance;

use App\Models\Career;
use App\Models\Configs;
use App\Models\DailyAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.attendance')]
class Index extends Component
{
    use Toast;

    public ?int $careerId = null;

    public string $date = '';

    /** @var array<int, array<string, mixed>> */
    public array $students = [];

    /** @var array<int, array<string, mixed>> */
    public array $attendances = [];

    public function mount(): void
    {
        $this->date = Carbon::today()->toDateString();

        $careers = $this->getAccessibleCareers();

        if ($careers->isNotEmpty()) {
            $this->careerId = $careers->first()->id;
            $this->loadStudents();
            $this->loadAttendances();
        }
    }

    protected function getAccessibleCareers(): Collection
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'director', 'administrative'])) {
            return Career::orderBy('id')->get();
        }

        return $user->careers()->orderBy('id')->get();
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
            ->get(['id', 'firstname', 'lastname'])
            ->toArray();
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
    }

    public function setStatus(int $userId, string $status): void
    {
        if (isset($this->attendances[$userId])) {
            $this->attendances[$userId]['status'] = $status;
        }
    }

    public function save(): void
    {
        $shiftType = Configs::find('shift_type')?->value ?? 'simple';
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
    public function absenceTotals(): array
    {
        return DailyAttendance::query()
            ->where('career_id', $this->careerId)
            ->whereIn('user_id', array_column($this->students, 'id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(absence_value) as total')
            ->pluck('total', 'user_id')
            ->toArray();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.attendance.index', [
            'careers' => $this->getAccessibleCareers(),
            'totals' => $this->absenceTotals(),
        ]);
    }
}
