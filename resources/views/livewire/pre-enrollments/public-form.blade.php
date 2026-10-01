<div class="min-h-screen bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10" x-data @validation-failed.window="setTimeout(()=> { const el=document.querySelector('[wire\:model="'+$event.detail.field+'"], [wire\:model.live="'+$event.detail.field+'"], [name="'+$event.detail.field+'"]'); if(el){ el.scrollIntoView({behavior:'smooth',block:'center'}); el.focus(); el.classList.add('input-error'); } }, 100)" @autofill-completed.window="() => { $dispatch('notify', { message: $event.detail.message, type: 'info' }) }">
    <!-- Hero -->
    <div class="hero bg-primary text-primary-content py-10">
        <div class="hero-content text-center flex-col">
            <h1 class="text-4xl font-black">¡Inscribite {{ \App\Services\AcademicCycle::enrollmentCycle() }}! 🎓</h1>
            <p class="text-lg opacity-90">ISPI 4013 Padre Joaquín Bonaldo — Reconquista</p>
            <p class="text-sm opacity-70">4 carreras con futuro • Cupos limitados</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto p-4 -mt-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-error mb-4">
                        <ul class="list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Stepper -->
                <ul class="steps steps-horizontal w-full mb-6">
                    <li class="step {{ $step >= 1 ? 'step-primary' : '' }}">Carrera</li>
                    <li class="step {{ $step >= 2 ? 'step-primary' : '' }}">Datos personales</li>
                    <li class="step {{ $step >= 3 ? 'step-primary' : '' }}">Domicilio</li>
                    <li class="step {{ $step >= 4 ? 'step-primary' : '' }}">Finalizar</li>
                </ul>

                @if (session('pdf_url'))
                    <div class="alert alert-success flex flex-col items-center gap-3 text-center">
                        <p class="font-bold">¡Preinscripción registrada!</p>
                        <p class="text-sm">Descargá tu comprobante para presentar en la institución.</p>
                        <a href="{{ session('pdf_url') }}" class="btn btn-primary" download>📄 Descargar comprobante</a>
                        @php $mats = \App\Models\PreEnrollmentMaterial::where('cycle_id', \App\Services\AcademicCycle::enrollmentCycle())->where(function($q){ $q->whereNull('career_id')->orWhere('career_id', session('preinsc_career_id')); })->get(); @endphp
                        @if ($mats->count())
                            <div class="w-full text-left mt-2">
                                <p class="font-semibold text-sm">Material disponible:</p>
                                <ul class="list-disc list-inside text-sm">
                                    @foreach ($mats as $mat)
                                        <li><a href="{{ Storage::disk('public')->url($mat->file_path) }}" target="_blank" class="link">{{ $mat->title }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
                <form wire:submit="save" novalidate>
                    @if ($step === 5)
                        <div class="text-center py-8">
                            <p class="text-lg">Tu preinscripción ya fue enviada. Usá el botón arriba para descargar el PDF.</p>
                        </div>
                    @elseif ($step === 1)
                        <div class="space-y-4">
                            <h2 class="text-xl font-bold">Elegí tu carrera</h2>
                            <div class="grid gap-3">
                                @foreach ($careers as $c)
                                    <label class="card border-2 cursor-pointer {{ $career_id == $c->id ? 'border-primary bg-primary/5' : 'border-base-300' }} p-4 flex flex-row items-center gap-3">
                                        <input type="radio" wire:model.live="career_id" value="{{ $c->id }}" class="radio radio-primary" />
                                        <span class="font-semibold">{{ $c->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('career_id') <span class="text-error text-sm">{{ $message }}</span> @enderror
                        </div>
                    @elseif ($step === 2)
                        <div class="space-y-3">
                            <h2 class="text-xl font-bold">Datos personales</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <x-select label="Tipo doc" wire:model="doc_type" :options="[['id'=>'DNI','name'=>'DNI'],['id'=>'PAS','name'=>'Pasaporte'],['id'=>'LE','name'=>'LE'],['id'=>'LC','name'=>'LC']]" />
                                <x-input label="Número" wire:model="doc_number" wire:blur="autofill" />
                                <x-input label="Apellido" wire:model="lname" />
                                <x-input label="Nombre/s" wire:model="fname" />
                                <x-input label="Email" wire:model="email" type="email" />
                                <x-input label="Teléfono" wire:model="phone" />
                                <x-select label="Sexo" wire:model="sexo" :options="[['id'=>'F','name'=>'Femenino'],['id'=>'M','name'=>'Masculino'],['id'=>'X','name'=>'Otro']]" option-value="id" option-label="name" />
                                <x-input label="Fecha nacimiento" wire:model="fnacimiento" type="date" />
                                <x-select label="Nacionalidad" wire:model="nacionalidad" :options="collect($nacionalidades)->map(fn($v)=>['id'=>$v,'name'=>$v])" />
                                <x-input label="Localidad nacimiento" wire:model="nacimlocalid" />
                                <x-input label="País nacimiento" wire:model="nacimpais" />
                                <x-select label="Estado civil" wire:model="estadocivil" :options="collect($estadosCiviles)->map(fn($v)=>['id'=>$v,'name'=>$v])" />
                                <x-select label="Pueblo originario" wire:model="puebloorig" :options="[['id'=>'No','name'=>'No'],['id'=>'Si','name'=>'Si']]" />
                                <x-input label="Etnia" wire:model="etnia" />
                                <x-input label="Comunidad" wire:model="comunrefer" />
                            </div>
                        </div>
                    @elseif ($step === 3)
                        <div class="space-y-3">
                            <h2 class="text-xl font-bold">Domicilio y ocupación</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <x-input label="Calle" wire:model="dirStreet" />
                                <x-input label="Número" wire:model="dirNumber" />
                                <x-input label="Piso" wire:model="dirFloor" />
                                <x-input label="Depto" wire:model="dirAptmt" />
                                <x-input label="Manzana" wire:model="dirBlock" />
                                <x-input label="Monoblock" wire:model="dirMonoBlock" />
                                <x-input label="Barrio" wire:model="dirNeighbor" />
                                <x-input label="Localidad *" wire:model="dirCity" />
                                <x-select label="Discapacidad" wire:model="discapacidad" :options="[['id'=>'No','name'=>'No'],['id'=>'Si','name'=>'Si']]" />
                                <x-select label="Tipo discapacidad" wire:model="tipodisc" :options="collect($discapacidades)->map(fn($v)=>['id'=>$v,'name'=>$v])" />
                                <x-select label="Ocupación" wire:model="ocupacion" :options="collect($ocupaciones)->map(fn($v)=>['id'=>$v,'name'=>$v])" />
                                <x-input label="Calle trabajo" wire:model="ocupStreet" />
                                <x-input label="Localidad trabajo" wire:model="ocupCity" />
                                <x-select label="Estado ocup" wire:model="ocupestado" :options="collect($ocupEstados)->map(fn($k,$v)=>['id'=>$k,'name'=>$v])" />
                            </div>
                        </div>
                    @else
                        <div class="space-y-3">
                            <h2 class="text-xl font-bold">Otros datos</h2>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" wire:model="respaspra" class="checkbox" /> Residencia/Pasantía</label>
                                <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" wire:model="regIntern" class="checkbox" /> Internado en establecimiento</label>
                                <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" wire:model="ambitorural" class="checkbox" /> Ámbito rural</label>
                                <label class="label cursor-pointer justify-start gap-2"><input type="checkbox" wire:model="contexEncierro" class="checkbox" /> Contexto encierro</label>
                            </div>
                            <x-select label="Motivo procedencia" wire:model="motivoProcedencia" :options="collect($motivos)->map(fn($v)=>['id'=>$v,'name'=>$v])" />
                            <x-input label="Título secundario" wire:model="titulo" />
                            <x-input label="Otorgado por" wire:model="titotorgado" />
                            <x-input label="Año título" wire:model="tityear" />
                            <x-input label="Observaciones" wire:model="observaciones" />
                            <x-select label="¿Cómo nos conociste?" wire:model="canalinform" :options="collect($canales)->map(fn($v)=>['id'=>$v,'name'=>$v])" />
                            <x-input label="DNI referente (opcional)" wire:model="referido" />
                        </div>
                    @endif

                    <div class="flex justify-between mt-6">
                        @if ($step > 1)
                            <x-button label="Atrás" wire:click="prevStep" type="button" />
                        @else
                            <span></span>
                        @endif
                        @if ($step < 4)
                            <x-button label="Siguiente →" wire:click="nextStep" type="button" class="btn-primary" />
                        @else
                            <x-button label="¡Enviar preinscripción!" type="submit" class="btn-primary" spinner="save" />
                        @endif
                    </div>
                </form>
            </div>
        </div>
        <p class="text-center text-sm opacity-60 mt-4">Tus datos se usan solo para la inscripción oficial SIGAE {{ \App\Services\AcademicCycle::enrollmentCycle() }}.</p>
    </div>
</div>