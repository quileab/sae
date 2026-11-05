<div>
    <x-header title="Mi Perfil" separator />

    <div class="grid gap-8 lg:grid-cols-2">
        <x-card title="Información Personal" subtitle="Actualiza tus datos de contacto" separator shadow>
            <x-form wire:submit="save">
                <div class="flex justify-center mb-6">
                    <x-file wire:model="photo" accept="image/png, image/jpeg, image/webp">
                        <img src="{{ $photo ? $photo->temporaryUrl() : auth()->user()->avatar_url }}" class="h-40 w-40 rounded-full object-cover border-4 border-base-200" />
                    </x-file>
                </div>
                <div class="grid gap-4">
                    <x-input label="Nombre de Usuario" wire:model="name" icon="o-user" required />
                    <x-input label="Nombre" wire:model="firstname" icon="o-user" />
                    <x-input label="Apellido" wire:model="lastname" icon="o-user" />
                    <x-input label="Email" wire:model="email" icon="o-envelope" required />
                    <x-input label="Teléfono" wire:model="phone" icon="o-phone" />
                    <x-input label="Fecha de Nacimiento" wire:model="birth_date" type="date" icon="o-calendar" hint="Requerido para determinar mayoría de edad" />
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

    {{-- GESTIÓN DE FICHA MÉDICA --}}
    @if(auth()->user()->role === 'student' || auth()->user()->isStaff())
        <div class="mt-8">
            @if(auth()->user()->canManageOwnMedicalRecord())
                @livewire('users.medical-record-manager', [
                    'userId' => auth()->id(),
                    'isSelfManagement' => true,
                ], key('profile-medical-'.auth()->id()))
            @else
                <x-card title="Ficha Médica y Salud Escolar" shadow separator class="border border-warning/30 bg-warning/5">
                    <div class="flex items-start gap-3">
                        <x-icon name="o-information-circle" class="w-8 h-8 text-warning shrink-0 mt-0.5" />
                        <div class="space-y-1">
                            <h4 class="font-bold text-sm text-base-content">Ficha administrada por tutores</h4>
                            <p class="text-xs text-base-content/70 leading-relaxed">
                                Como estudiante menor de edad del nivel secundario, los datos de cobertura médica, grupo sanguíneo, alergias y apto físico deben ser declarados y actualizados por tus padres o tutores legales a través del <strong>Portal Familias</strong> o en la secretaría del establecimiento.
                            </p>
                        </div>
                    </div>
                </x-card>
            @endif
        </div>
    @endif
</div>
