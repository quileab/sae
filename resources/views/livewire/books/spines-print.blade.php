<?php

use Livewire\Volt\Component;
use App\Models\Book;
use App\Services\BookSignatureService;
use Livewire\Attributes\Layout;

new #[Layout('layouts.empty')] class extends Component {
    public array $ids = [];

    public function mount(): void
    {
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor'])) {
            abort(403);
        }

        $raw = request()->query('ids', '');
        if ($raw) {
            $tokens = preg_split('/[\s,\n\r]+/', trim($raw));
            foreach ($tokens as $token) {
                if (is_numeric($token)) {
                    $this->ids[] = (int)$token;
                }
            }
            $this->ids = array_values(array_unique(array_filter($this->ids)));
        }
    }

    public function books(): mixed
    {
        if (empty($this->ids)) {
            return collect();
        }

        return Book::whereIn('id', $this->ids)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function with(): array
    {
        return [
            'books' => $this->books(),
        ];
    }
}; ?>

<div class="p-4 bg-white text-black min-h-screen">
    <!-- Action Bar (Hidden on Print) -->
    <div class="no-print mb-6 p-4 bg-slate-100 rounded-xl border border-slate-300 flex items-center justify-between shadow-xs">
        <div>
            <h1 class="font-bold text-lg text-slate-800">Grilla de Tejuelos Físicos Imprimibles</h1>
            <p class="text-xs text-slate-600">Total: {{ count($books) }} etiqueta(s) de tejuelo de lomo (3x5 cm)</p>
        </div>
        <div class="flex gap-2">
            <x-button label="Volver a la Selección" icon="o-arrow-left" :link="'/books/spines?ids=' . implode(',', $ids)" class="btn-outline btn-sm" />
            <x-button label="Imprimir Ahora" icon="o-printer" class="btn-primary btn-sm" onclick="window.print()" />
        </div>
    </div>

    <!-- Printable Grid container (A4 / Letter layout) -->
    @if(count($books) > 0)
        @php $service = app(BookSignatureService::class); @endphp
        
        <div class="spine-grid">
            @foreach($books as $book)
                @php
                    $label = $service->getSpineLabelData(
                        null,
                        null,
                        $book->author,
                        $book->title,
                        '000',
                        1
                    );

                    if ($book->signature && str_contains($book->signature, '-')) {
                        $parts = explode('-', $book->signature);
                        $label['section'] = $parts[0] ?? $label['section'];
                        $label['cdu'] = $parts[1] ?? $label['cdu'];
                        $label['cutter'] = $parts[2] ?? $label['cutter'];
                        $label['collection'] = $parts[3] ?? '000';
                        $label['exemplar'] = $parts[4] ?? 'E01';
                    }
                @endphp

                <!-- Standard Physical Spine Label Card (3cm width x 5cm height) -->
                <div class="spine-card">
                    <div class="spine-id">#{{ $book->id }}</div>
                    <div>
                        <span class="label-heading">SECCIÓN</span>
                        <span class="spine-section">{{ $label['section'] }}</span>
                    </div>
                    <div>
                        <span class="label-heading">CDU</span>
                        <span class="spine-cdu">{{ $label['cdu'] }}</span>
                    </div>
                    <div>
                        <span class="label-heading">CUTTER</span>
                        <span class="spine-cutter">{{ $label['cutter'] }}</span>
                    </div>
                    <div class="spine-footer">
                        <span>{{ $label['collection'] }}</span>
                        <span class="exemplar-badge">{{ $label['exemplar'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-12 text-center text-gray-500">
            No se seleccionaron libros válidos para la impresión de tejuelos.
        </div>
    @endif

    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        /* Printable Spine Label Grid Styles - Compact Layout without gaps */
        .spine-grid {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 0;
            width: 100%;
        }

        .spine-card {
            width: 3.5cm;
            height: 5cm;
            border: 1px dashed #999;
            margin: -0.5px; /* Bordes pegados contiguos para cortar con guillotina */
            padding: 1.5mm 2.5mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            text-align: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            background: #fff;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .spine-id {
            font-size: 9px;
            font-weight: 800;
            color: #334155; /* slate-700 */
            letter-spacing: 0.5px;
            line-height: 1;
            margin-bottom: 0px;
        }

        .label-heading {
            font-size: 8px;
            font-weight: 900;
            color: #94a3b8; /* slate-400 */
            display: block;
            line-height: 1;
            margin-bottom: 1px;
            letter-spacing: 0.5px;
        }

        .spine-section {
            font-size: 13px;
            font-weight: 800;
            color: #2563eb; /* primary blue */
            display: block;
            line-height: 1.1;
        }

        .spine-cdu {
            font-size: 12px;
            font-weight: 800;
            color: #1e293b; /* slate-800 */
            display: block;
            line-height: 1.1;
        }

        .spine-cutter {
            font-size: 13px;
            font-weight: 800;
            color: #047857; /* emerald-700 */
            display: block;
            line-height: 1.1;
        }

        .spine-footer {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            font-weight: 800;
            color: #475569; /* slate-600 */
            line-height: 1;
        }

        .exemplar-badge {
            color: #b45309; /* amber-700 */
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .spine-card {
                border: 1px solid #aaa !important;
            }
        }
    </style>
</div>
