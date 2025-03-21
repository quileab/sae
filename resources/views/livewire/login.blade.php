<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;

new #[Layout('components.layouts.empty')]       // <-- Here is the `empty` layout
    #[Title('Login')]
    class extends Component {

    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required')]
    public string $password = '';

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
        if (auth()->attempt($credentials)) {
            request()->session()->regenerate();
            return redirect()->intended('/');
        }

        $this->addError('email', 'The provided credentials do not match our records.');
    }
}; ?>

<div class="min-h-screen flex justify-center items-center">
    <div
        class="w-3/4 md:w-1/3 mx-auto bg-slate-900 bg-opacity-40 backdrop-blur-xl rounded-lg shadow-lg shadow-black/50 p-4">
        <x-header title="LOGIN" />
        <x-form wire:submit="login" no-separator>
            <x-input label="E-mail" wire:model="email" icon="o-envelope" inline />
            <x-input label="Password" wire:model="password" type="password" icon="o-key" inline />

            <x-slot:actions>
                {{-- <x-button label="Create an account" class="btn-ghost" link="/register" /> --}}
                <x-button label="Login" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="login" />
            </x-slot:actions>
        </x-form>
    </div>
</div>