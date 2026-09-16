<?php

namespace App\Livewire;

use App\Models\Config as ConfigModel;
use App\Models\User;
use App\Models\UserPayment;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

#[Lazy]
class Dashboard extends Component
{
    use AuthorizesAccess, Toast;

    #[Url]
    public $cycle_id = null;

    public $subject_id;

    public $mp_key = null;

    public $mp_token = null;

    public $showCareerWarning = false;

    public function mount(): void
    {
        $this->mp_key = config('mercadopago.public_key');
        $this->mp_token = config('mercadopago.access_token');

        $user = Auth::user();

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

        if (! $this->subject_id && $this->subjects->isNotEmpty()) {
            $this->subject_id = $this->subjects->first()->id;
        }
    }

    public function updatedSubjectId($value): void
    {
        session()->put('current_subject_id', $value);
        $this->success('Materia seleccionada: '.$this->subjects->find($value)->name, position: 'toast-bottom toast-end');
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
            return User::where('role', 'student')->doesntHave('careers')->count();
        });
    }

    #[Computed]
    public function inscriptionsStatus()
    {
        return Cache::remember('configs_inscriptions', 3600, function () {
            return ConfigModel::where('group', 'inscriptions')->get();
        });
    }

    #[Computed(persist: true)]
    public function subjects()
    {
        return Auth::user()->subjects()->with('career')->get();
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

    public function placeholder()
    {
        return <<<'HTML'
        <div class="flex justify-center items-center h-64">
            <x-loading class="loading-lg text-primary" />
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
