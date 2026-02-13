<div>
    <x-header title="Mi Perfil" separator />

    <div class="grid gap-8 lg:grid-cols-2">
        <x-card title="Información Personal" subtitle="Actualiza tus datos de contacto" separator shadow>
            <x-form wire:submit="save">
                <div class="grid gap-4">
                    <x-input label="Nombre de Usuario" wire:model="name" icon="o-user" required />
                    <x-input label="Nombre" wire:model="firstname" icon="o-user" />
                    <x-input label="Apellido" wire:model="lastname" icon="o-user" />
                    <x-input label="Email" wire:model="email" icon="o-envelope" required />
                    <x-input label="Teléfono" wire:model="phone" icon="o-phone" />
                </div>

                <x-slot:actions>
                    <x-button label="Guardar Cambios" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                </x-slot:actions>
            </x-form>
        </x-card>

        <x-card title="Seguridad" subtitle="Cambia tu contraseña" separator shadow>
            <x-form wire:submit="updatePassword">
                <div class="grid gap-4">
                    <x-input label="Nueva Contraseña" wire:model="password" type="password" icon="o-key" required />
                    <x-input label="Confirmar Contraseña" wire:model="password_confirmation" type="password" icon="o-key" required />
                </div>

                <x-slot:actions>
                    <x-button label="Actualizar Contraseña" type="submit" icon="o-lock-closed" class="btn-primary" spinner="updatePassword" />
                </x-slot:actions>
            </x-form>
        </x-card>
    </div>
</div>
