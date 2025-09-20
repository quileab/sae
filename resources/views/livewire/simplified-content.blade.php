<?php

use Livewire\Volt\Component;
use App\Models\Subject;
use App\Models\Unit;

new class extends Component {
    public Subject $subject;
    public $units;

    public function mount(Subject $subject): void
    {
        $this->subject = $subject;
        $this->units = Unit::where('subject_id', $this->subject->id)
            ->where('is_visible', true)
            ->with([
                'topics' => function ($query) {
                    $query->where('is_visible', true)->with([
                        'resources' => function ($query) {
                            $query->where('is_visible', true);
                        }
                    ]);
                }
            ])
            ->get();
    }
}; ?>

<div>
    <x-header title="Contenido de {{ $subject->name }}" subtitle="({{ $subject->id }})" separator>
        <x-slot:actions>
            <x-button label="Volver al Dashboard" icon="o-arrow-left" link="/dashboard" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-4 mt-4">
        @foreach($units as $unit)
            <x-card :title="$unit->name" shadow-md>
                @if($unit->description)
                    <p class="mb-4">{{ $unit->description }}</p>
                @endif
                @if($unit->topics->count() > 0)
                    <ul class="list-none list-inside ml-4">
                        @foreach($unit->topics as $topic)
                            <li>
                                @if($topic->content)
                                    <x-collapse separator>
                                        <x-slot:heading>
                                            <strong class="text-lg text-blue-500">{{ $topic->name }}</strong>
                                        </x-slot:heading>
                                        <x-slot:content>
                                            <div class="prose prose-sm max-w-none ml-4 mb-4">{!! $topic->content !!}</div>
                                        </x-slot:content>
                                    </x-collapse>
                                @endif
                                @if($topic->resources->count() > 0)
                                    <ul class="flex flex-row flex-wrap gap-2 mt-4">
                                        @foreach($topic->resources as $resource)
                                            <li>
                                                @if($resource->url)
                                                    <a href="{{ $resource->url }}" target="_blank"
                                                        class="text-blue-500 hover:text-white border rounded p-2 bg-blue-500/10">{{ $resource->title }}</a>
                                                @else
                                                    {{ $resource->title }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>No hay temas visibles para esta unidad.</p>
                @endif
            </x-card>
        @endforeach
    </div>
</div>