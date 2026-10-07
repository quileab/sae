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
    @php
        $detailed = App\Services\Evaluations\EvaluationContent::gradeTableDesignDetailed(
            $block,
            $answer,
            (float) ($block['points'] ?? 1)
        );
        $number = fn (float $value): string => rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
        $label = fn (?string $name): string => strtoupper(str_replace('_', ' ', (string) $name));
    @endphp

    <div class="space-y-3 text-xs">
        {{-- Per-dimension breakdown, so the teacher sees why points were lost --}}
        <div class="flex flex-wrap gap-1">
            @foreach($detailed['breakdown'] as $dimension)
                <span @class([
                    'badge badge-xs gap-1 font-normal',
                    'badge-success' => $dimension['total'] > 0 && $dimension['earned'] >= $dimension['total'],
                    'badge-warning' => $dimension['earned'] < $dimension['total'],
                    'badge-ghost' => $dimension['total'] == 0,
                ])>
                    {{ $dimension['label'] }}:
                    <span class="font-mono">{{ $number($dimension['earned']) }}</span>
                    <span class="opacity-60">/ {{ $number($dimension['total']) }}</span>
                </span>
            @endforeach
        </div>

        @foreach(($answer['tables'] ?? []) as $table)
            @php $verdict = $detailed['per_table'][$table['name'] ?? ''] ?? null; @endphp
            <div class="border border-base-300 rounded overflow-hidden">
                <div class="bg-base-200 px-2 py-1 flex items-center justify-between gap-2">
                    <span class="font-mono font-bold">{{ $label($table['name'] ?? '') }}</span>
                    @if($verdict === null)
                        <span class="badge badge-ghost badge-xs">tabla extra</span>
                    @else
                        <span class="flex gap-1">
                            <span class="badge badge-xs {{ ($verdict['missing'] || ! $verdict['columns']) ? 'badge-error' : 'badge-success' }}"
                                  title="{{ ($verdict['missing'] || ! $verdict['columns']) ? 'Distribución incorrecta' : 'Distribución correcta' }}">dist</span>
                            @if($verdict['pk'] !== null)
                                <span class="badge badge-xs {{ $verdict['pk'] ? 'badge-success' : 'badge-error' }}"
                                      title="{{ $verdict['pk'] ? 'PK correcta' : 'PK incorrecta' }}">pk</span>
                            @endif
                            @if($verdict['fks'] !== null)
                                <span class="badge badge-xs {{ $verdict['fks'] ? 'badge-success' : 'badge-error' }}"
                                      title="{{ $verdict['fks'] ? 'FK correctas' : 'FK incorrectas' }}">fk</span>
                            @endif
                        </span>
                    @endif
                </div>
                <table class="table table-xs font-mono">
                    <tbody>
                        @foreach($table['columns'] ?? [] as $column)
                            <tr>
                                <td class="w-16 text-center {{ in_array(strtoupper($column['type'] ?? ''), ['PK', 'FK'], true) ? 'font-bold' : 'opacity-40' }}">
                                    [{{ $column['type'] ?? '' }}]
                                </td>
                                <td>{{ $column['name'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        @if(! empty($answer['relations']))
            <div>
                <div class="font-bold uppercase text-base-content/60 mb-1">Relaciones</div>
                <ul class="space-y-1">
                    @foreach($answer['relations'] as $relation)
                        @php
                            $clave = $relation['clave'] ?? null;
                            $expected = collect($block['answer_key']['relations'] ?? [])->firstWhere('clave', $clave);
                            $ok = $detailed['per_relation'][$clave] ?? false;
                            $name = $expected
                                ? $label($expected['from'] ?? '').' → '.$label($expected['to'] ?? '')
                                : $label($relation['from'] ?? $clave);
                        @endphp
                        <li class="flex flex-wrap items-center gap-1">
                            <x-icon name="{{ $ok ? 'o-check-circle' : 'o-x-circle' }}"
                                    class="w-3.5 h-3.5 shrink-0 {{ $ok ? 'text-success' : 'text-error' }}" />
                            <span class="font-mono">{{ $name }}</span>
                            <code class="font-mono">{{ $relation['type'] ?: '— sin tipo —' }}</code>
                            @unless($ok && $expected)
                                <span class="opacity-60">esperaba {{ $expected['type'] ?? '—' }}</span>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@else
    <pre class="text-xs whitespace-pre-wrap">{{ json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif