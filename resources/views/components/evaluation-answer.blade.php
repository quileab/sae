@props([
    'block' => [],
    'answer' => null,
])

@php
    $type = $block['type'] ?? '';
    $empty = $answer === null || $answer === '' || $answer === [];

    // Map an option key back to its label so the teacher reads "Parcial" not "A".
    $optionText = function ($key) use ($block) {
        foreach ($block['options'] ?? [] as $option) {
            if (($option['key'] ?? null) === $key) {
                return $option['text'] ?? $key;
            }
        }

        return $key;
    };
@endphp

@if($empty)
    <span class="italic text-base-content/50">Sin respuesta</span>
@elseif($type === 'multiple_choice' || $type === 'true_false')
    <span class="font-semibold">{{ is_array($answer) ? implode(', ', array_map($optionText, $answer)) : $optionText($answer) }}</span>
@elseif($type === 'checkboxes')
    <ul class="list-disc list-inside">
        @foreach((array) $answer as $selected)
            <li>{{ $optionText($selected) }}</li>
        @endforeach
    </ul>
@elseif($type === 'matching')
    <ul class="space-y-1">
        @foreach((array) $answer as $index => $match)
            @php $prompt = $block['pairs'][$index]['prompt'] ?? "Par {$index}"; @endphp
            <li><span class="font-semibold">{{ $prompt }}</span> → {{ $match ?: '—' }}</li>
        @endforeach
    </ul>
@elseif($type === 'short_text')
    <span class="whitespace-pre-wrap">{{ $answer }}</span>
@elseif($type === 'open_text')
    @php $variantTitle = null; @endphp
    @if(! empty($answer['variant']))
        @foreach($block['variants'] ?? [] as $variant)
            @if(($variant['key'] ?? null) === $answer['variant'])
                @php $variantTitle = $variant['title'] ?? $variant['key']; @endphp
            @endif
        @endforeach
    @endif
    @if($variantTitle)
        <div class="badge badge-ghost badge-xs mb-1">Variante: {{ $variantTitle }}</div>
    @endif
    <span class="whitespace-pre-wrap">{{ $answer['text'] ?? '' }}</span>
@elseif($type === 'table_design')
    <div class="space-y-3 text-xs">
        @foreach(($answer['tables'] ?? []) as $table)
            <div>
                <div class="font-bold">{{ $table['name'] ?? '' }}</div>
                <table class="table table-xs mt-1">
                    <thead>
                        <tr>
                            <th>PK</th>
                            <th>FK</th>
                            <th>Columna</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($table['columns'] ?? [] as $column)
                            <tr>
                                <td>{{ ($column['type'] ?? '') === 'PK' ? '🔑' : '' }}</td>
                                <td>{{ ($column['type'] ?? '') === 'FK' ? '🔗' : '' }}</td>
                                <td>{{ $column['name'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        @if(! empty($answer['relations']))
            <div>
                <div class="font-bold mt-2">Relaciones</div>
                <ul class="list-disc list-inside">
                    @foreach($answer['relations'] as $relation)
                        <li>{{ $relation['from'] ?? '?' }} → {{ $relation['to'] ?? '?' }} ({{ $relation['type'] ?? '?' }})</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@else
    <pre class="text-xs whitespace-pre-wrap">{{ json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif