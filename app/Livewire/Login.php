<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.empty')]
#[Title('Login')]
class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required')]
    public string $password = '';

    public bool $remember = false;

    public function mount()
    {
        if (auth()->user()) {
            return redirect('/');
        }
        // set cycle_id cycle_id_name session to current year
        session()->put('cycle_id', date('Y'));
        session()->put('cycle_id_name', date('Y'));
    }

    public function login()
    {
        $credentials = $this->validate();
        if (auth()->attempt($credentials, $this->remember)) {
            request()->session()->regenerate();

            return redirect()->intended('/');
        }

        $this->addError('email', 'The provided credentials do not match our records.');
    }

    public function render()
    {
        return view('livewire.login');
    }
}
