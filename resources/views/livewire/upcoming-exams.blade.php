<div>
    <x-card title="Próximas Mesas de Examen" shadow separator class="bg-base-200">
        @if (auth()->user()->hasRole('student') || auth()->user()->hasAnyRole(['admin', 'director', 'administrative']))
            <div class="mb-4">
                <x-select wire:model.live="selectedProfessorId" :options="$professors" option-value="id"
                    option-label="full_name" placeholder="Filtrar por profesor..." />
            </div>
        @endif

        @if ($exams->isEmpty())
            <x-alert title="No hay mesas de examen próximas." icon="o-information-circle" />
        @else
            <div class="space-y-4">
                @foreach ($exams as $exam)
                    <div wire:key="{{ $exam->id }}" class="p-4 rounded-lg shadow-md bg-gray-800 border border-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="col-span-1">
                                <p class="font-bold text-lg text-primary">
                                    {{ \Carbon\Carbon::parse($exam->start)->translatedFormat('d/m/Y H:i') }} hs</p>
                                @if (auth()->user()->hasRole('teacher'))
                                    <p class="text-warning">{{ $exam->teacher_role }}</p>
                                @endif
                            </div>
                            <div class="col-span-2">
                                <p><span class="font-bold">Materia:</span> {{ $exam->subject->name ?? 'N/A' }}
                                    ({{ $exam->subject->id ?? '' }})
                                </p>
                                <p><span class="font-bold">Carrera:</span>
                                    {{ $exam->subject->career->name ?? 'N/A' }}</p>
                                <p class="mt-2"><span class="font-bold">Presidente:</span>
                                    {{ $exam->presidente->full_name ?? 'N/A' }}</p>
                                <p><span class="font-bold">Vocal 1:</span> {{ $exam->vocal1->full_name ?? 'N/A' }}
                                </p>
                                <p><span class="font-bold">Vocal 2:</span> {{ $exam->vocal2->full_name ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>