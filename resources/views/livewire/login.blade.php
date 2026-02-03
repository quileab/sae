<div class="min-h-screen flex justify-center items-center">
    <div
        class="w-3/4 md:w-1/3 mx-auto bg-slate-900/70 backdrop-blur-xl rounded-lg shadow-lg shadow-black/50 p-4">
        <div class="flex items-center justify-center gap-4 mb-6">
            <img src="{{ asset('imgs/logo.png') }}" alt="Logo" class="w-16 h-16 object-contain">
            <h1 class="text-2xl font-bold text-white uppercase">{{ config('app.name') }}</h1>
        </div>
        <x-header title="INGRESO" />
        <x-form wire:submit="login" no-separator>
            <x-input label="Email" wire:model="email" icon="o-envelope" inline />
            <div x-data="{ show: false }">
                <x-input label="Contraseña" wire:model="password" type="password" icon="o-key" inline x-bind:type="show ? 'text' : 'password'">
                    <x-slot:append>
                        <x-button icon="o-eye" class="btn-ghost btn-sm" @click="show = !show" x-show="!show" />
                        <x-button icon="o-eye-slash" class="btn-ghost btn-sm" @click="show = !show" x-show="show" x-cloak />
                    </x-slot:append>
                </x-input>
            </div>
            <x-slot:actions>
                <div class="flex items-center justify-between w-full">
                    <x-checkbox label="Recuérdame" wire:model="remember" />
                    <x-button label="INGRESAR" type="submit" icon="o-arrow-right-end-on-rectangle" class="btn-primary" spinner="login" />
                </div>
            </x-slot:actions>
        </x-form>
    </div>
</div>