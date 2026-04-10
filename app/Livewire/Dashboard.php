<?php

namespace App\Livewire;

use App\Models\Configs;
use App\Models\User;
use App\Models\UserPayments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Mary\Traits\Toast;

#[Lazy]
class Dashboard extends Component
{
    use Toast;

    public $cycleYear = null;

    public $subject_id;

    public $mp_key = null;

    public $mp_token = null;

    public $showCareerWarning = false;

    public function mount(): void
    {
        $this->mp_key = config('mercadopago.public_key');
        $this->mp_token = config('mercadopago.access_token');

        $user = Auth::user();

        // Check if user has no career (only for students)
        if ($user->hasRole('student')) {
            // Eagerly check for careers to avoid N+1 if this was in a loop,
            // but here it's just one check. Still, let's keep it clean.
            if ($user->careers()->count() == 0) {
                $this->showCareerWarning = true;
            }
        }

        // if session cycle is set, use it
        if (session()->has('cycle_id')) {
            $this->cycleYear = session('cycle_id');
        } else {
            // if not, set it to the current year
            $this->cycleYear = date('Y');
            session()->put('cycle_id', $this->cycleYear);
            session()->put('cycle_name', $this->cycleYear);
        }

        if ($this->subjects->isNotEmpty()) {
            $this->subject_id = $this->subjects->first()->id;
        }
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
            return Configs::where('group', 'inscriptions')->get();
        });
    }

    #[Computed]
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

        return UserPayments::where('user_id', Auth::id())
            ->whereRaw('paid < amount')
            ->orderBy('date', 'asc')
            ->first();
    }

    public function saveCycleYear(): void
    {
        $id = $this->cycleYear;
        // emit change
        $this->dispatch('bookmarked', ['type' => 'cycle_id', 'value' => $id]);

        $this->success("Ciclo lectivo cambiado a $id", position: 'toast-bottom toast-end');
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
