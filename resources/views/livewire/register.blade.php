
<div>
    <x-form wire:submit="register" no-separator>
        <x-input label="Apellido" wire:model="lastname" icon="o-user" inline />
        <x-input label="Nombre/s" wire:model="name" icon="o-user" inline />
        <x-input label="Dirección" wire:model="address" icon="o-map-pin" inline />
        <x-input label="Ciudad" wire:model="city" icon="o-map-pin" inline />
        <x-input label="Código Postal" wire:model="postal_code" icon="o-hashtag" inline />
        <x-input label="Teléfono" wire:model="phone" icon="o-phone" inline />
        <x-input label="E-mail" wire:model="email" icon="o-envelope" inline />
        <x-input label="Password" wire:model="password" type="password" icon="o-key" inline />
        <x-input label="Confirm Password" wire:model="password_confirmation" type="password" icon="o-key" inline />
 
        <x-slot:actions>
            <x-button label="Login" class="btn-ghost" link="/login" />
            <x-button label="Register" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="register" />
        </x-slot:actions>
    </x-form>
</div>
