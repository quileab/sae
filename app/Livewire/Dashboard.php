<?php

namespace App\Livewire;

use App\Models\Config as ConfigModel;
use App\Models\Event;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserPayment;
use App\Services\SubjectOptions;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Dashboard extends Component
{
    use AuthorizesAccess, Toast;

    #[Url]
    public $cycle_id = null;

    public $subject_id;

    /** @var array<int, array{id: int, name: string}> */
    public array $subjectOptions = [];

    public $mp_key = null;

    public $mp_token = null;

    public $showCareerWarning = false;

    public function mount(): void
    {
        $this->mp_key = config('mercadopago.public_key');
        $this->mp_token = config('mercadopago.access_token');

        $user = Auth::user();

        if ($user && $user->role === 'tutor') {
            $this->redirect(route('family.portal'), navigate: true);

            return;
        }

        // Cycle ID initialization (URL > Session > Default)
        if (! $this->cycle_id) {
            $this->cycle_id = session('cycle_id') ?? $this->getCycleId();
        }

        // Check if user has no career (only for students)
        if ($user->hasRole('student')) {
            if (! $user->careers()->exists()) {
                $this->showCareerWarning = true;
            }
        }

        // Subject context persistence
        $this->subject_id = session('current_subject_id');
        $this->subjectOptions = app(SubjectOptions::class)->search($user, '', null, [$this->subject_id]);

        if (! $this->subject_id || ! in_array((int) $this->subject_id, array_column($this->subjectOptions, 'id'), true)) {
            $this->subject_id = $this->subjectOptions[0]['id'] ?? null;
        }
    }

    public function searchSubjects(string $value = ''): void
    {
        $this->subjectOptions = app(SubjectOptions::class)->search(
            Auth::user(),
            $value,
            null,
            [$this->subject_id],
        );
    }

    public function updatedSubjectId($value): void
    {
        session()->put('current_subject_id', $value);
        $name = Subject::find($value)?->name ?? 'Materia';
        $this->success('Materia seleccionada: '.$name, position: 'toast-bottom toast-end');
    }

    #[Computed]
    public function cycleYear()
    {
        return $this->cycle_id ?: $this->getCycleId();
    }

    #[Computed]
    public function usersWithoutCareerCount()
    {
        if (Auth::user()->hasRole('student')) {
            return 0;
        }

        return Cache::remember('users_without_career_count', 300, function () {
            return User::where('role', 'student')->where('status', 'active')->doesntHave('careers')->count();
        });
    }

    #[Computed]
    public function studentStats(): array
    {
        if (! Auth::user()?->isAdministrative()) {
            return [
                'total' => 0,
                'without_career' => 0,
                'with_attendance' => 0,
                'enabled' => 0,
                'disabled' => 0,
            ];
        }

        return Cache::remember('student_stats_'.now()->format('Y_m_d_H'), 300, function () {
            $startOfMonth = now()->startOfMonth()->toDateString();
            $endOfMonth = now()->endOfMonth()->toDateString();

            $baseStudentQuery = User::where('role', 'student')->where('status', 'active');

            $totalStudents = (clone $baseStudentQuery)->count();
            $withoutCareer = (clone $baseStudentQuery)->doesntHave('careers')->count();
            $enabled = (clone $baseStudentQuery)->where('enabled', true)->count();
            $disabled = (clone $baseStudentQuery)->where('enabled', false)->count();

            $attendanceQuery = (clone $baseStudentQuery)
                ->whereHas('careers')
                ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                    $query->whereHas('grades', function ($gradeQ) use ($startOfMonth, $endOfMonth) {
                        $gradeQ->where('attendance', '>', 0)
                            ->whereHas('classSession', function ($sessionQ) use ($startOfMonth, $endOfMonth) {
                                $sessionQ->whereBetween('date', [$startOfMonth, $endOfMonth]);
                            });
                    })->orWhereExists(function ($attQ) use ($startOfMonth, $endOfMonth) {
                        $attQ->select(DB::raw(1))
                            ->from('daily_attendances')
                            ->whereColumn('daily_attendances.user_id', 'users.id')
                            ->whereBetween('daily_attendances.date', [$startOfMonth, $endOfMonth])
                            ->whereIn('daily_attendances.status', ['present', 'late', 'half_absent']);
                    });
                });

            $activeWithAttendance = (clone $attendanceQuery)->count();

            return [
                'total' => $totalStudents,
                'without_career' => $withoutCareer,
                'with_attendance' => $activeWithAttendance,
                'enabled' => $enabled,
                'disabled' => $disabled,
            ];
        });
    }

    #[Computed]
    public function inscriptionsStatus()
    {
        return ConfigModel::getGroup('inscriptions');
    }

    #[Computed]
    public function recentAnnouncements()
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return Event::query()
            ->announcements()
            ->published()
            ->forUser($user)
            ->with(['user', 'career', 'subject'])
            ->latest('published_at')
            ->limit(4)
            ->get();
    }

    #[Computed]
    public function nextPayment()
    {
        if (! Auth::user()->hasRole('student')) {
            return null;
        }

        return UserPayment::where('user_id', Auth::id())
            ->whereRaw('paid < amount')
            ->orderBy('date', 'asc')
            ->first();
    }

    public function saveCycleYear(): void
    {
        session()->put('cycle_id', $this->cycle_id);
        $this->dispatch('cycle-updated', cycle: $this->cycle_id);
        $this->success("Ciclo lectivo aplicado: $this->cycle_id", position: 'toast-bottom toast-end');
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
