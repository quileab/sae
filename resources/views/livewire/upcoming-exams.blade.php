<div>
    <x-card title="Próximas Mesas de Examen" shadow-md class="bg-info/5 border-t-4 border-t-info">
        @if (auth()->user()->hasRole('student') || auth()->user()->hasAnyRole(['admin', 'director', 'administrative']))
            <div class="mb-4">
                <x-select wire:model.live="selectedProfessorId" :options="$this->professors" option-value="id"
                    option-label="full_name" placeholder="Filtrar por profesor..." icon="o-funnel" />
            </div>
        @endif

        @if ($this->exams->isEmpty())
            <x-alert title="No hay mesas de examen próximas." icon="o-information-circle" class="bg-base-100/50" />
        @else
            <div class="space-y-3">
                @foreach ($this->exams as $exam)
                    <div wire:key="{{ $exam->id }}" class="p-4 rounded-lg bg-base-100 border border-base-300 shadow-sm hover:shadow-md transition-shadow">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <div class="col-span-1 border-b md:border-b-0 md:border-r border-base-300 pb-2 md:pb-0">
                                <div class="text-xs font-bold text-info uppercase tracking-wider">Fecha y Hora</div>
                                <p class="font-bold text-lg">
                                    {{ \Carbon\Carbon::parse($exam->start)->translatedFormat('d/m/Y H:i') }} hs</p>
                                @if (auth()->user()->hasRole('teacher'))
                                    <p class="text-warning font-medium italic">{{ $exam->teacher_role }}</p>
                                @endif
                            </div>
                            <div class="col-span-2 md:pl-2">
                                <div class="text-xs font-bold text-base-content/50 uppercase tracking-wider mb-1">Detalles de la Mesa</div>
                                <p><span class="font-bold">Materia:</span> {{ $exam->subject->name ?? 'N/A' }}
                                    <span class="text-xs opacity-70">({{ $exam->subject->id ?? '' }})</span>
                                </p>
                                <p class="text-sm"><span class="font-bold">Carrera:</span>
                                    {{ $exam->subject->career->name ?? 'N/A' }}</p>
                                
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-1 text-xs">
                                    <div><span class="font-bold">Presidente:</span> {{ explode(',', $exam->presidente->full_name ?? 'N/A')[0] }}</div>
                                    <div><span class="font-bold">Vocal 1:</span> {{ explode(',', $exam->vocal1->full_name ?? 'N/A')[0] }}</div>
                                    <div><span class="font-bold">Vocal 2:</span> {{ explode(',', $exam->vocal2->full_name ?? 'N/A')[0] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>