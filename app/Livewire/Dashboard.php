<?php

namespace App\Livewire;

use App\Models\UserPayments;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class Dashboard extends Component
{
    use Toast;

    public $cycleYear = null;

    public $inscriptionsStatus = null;

    public $subjects = [];

    public $subject_id;

    public ?UserPayments $nextPayment = null;

    public $mp_key = null;

    public $mp_token = null;

    public $showCareerWarning = false;

    public $usersWithoutCareerCount = 0;

    public function mount(): void
    {
        $this->mp_key = config('mercadopago.public_key');
        $this->mp_token = config('mercadopago.access_token');

        // Check if user has no career (only for students)
        if (Auth::user()->hasRole('student')) {
            if (Auth::user()->careers()->count() == 0) {
                $this->showCareerWarning = true;
            }
        } else {
            // For elevated permissions, count students without career
            $this->usersWithoutCareerCount = \App\Models\User::where('role', 'student')->doesntHave('careers')->count();
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

        $this->inscriptionsStatus = \App\Models\Configs::where('group', 'inscriptions')->get();
        $this->subjects = \App\Models\User::find(Auth::user()->id)->subjects()->with('career')->get();
        if (! $this->subjects->isEmpty()) {
            $this->subject_id = $this->subjects->first()->id;
        }

        if (Auth::user()->hasRole('student')) {
            $this->nextPayment = UserPayments::where('user_id', Auth::id())
                ->whereRaw('paid < amount')
                ->orderBy('date', 'asc')
                ->first();
        }
    }

    public function saveCycleYear(): void
    {
        // session()->put('cycle', $this->cycleYear);
        // session()->put('cycle_name', $this->cycleYear);
        $id = $this->cycleYear;
        // emit change
        $this->dispatch('bookmarked', ['type' => 'cycle_id', 'value' => $id]);

        $this->success("Ciclo lectivo cambiado a $id", position: 'toast-bottom toast-end');
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
