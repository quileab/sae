@extends('print.layouts.report')

@section('title', 'Resumen de Pagos - '.$user->full_name)

@section('styles')
    hr {
        height: 1px;
        border: 0;
        border-top: 1px solid #ccc;
        margin: 1rem 0;
    }

    h2 { margin-bottom: 0.5rem; }
    h4 { margin-bottom: 1rem; color: #666; }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1.5rem;
    }

    table td, table th {
        border: 1px solid #ddd;
        padding: 0.6rem;
        text-align: left;
    }

    table th {
        background-color: #f4f4f4;
        font-weight: bold;
        border-bottom: 2px solid #333;
    }

    .right { text-align: right; }
    .font-mono { font-family: 'Courier New', Courier, monospace; }
    .font-bold { font-weight: bold; }

    .text-success { color: #166534; }
    .text-error { color: #b91c1c; }
    .bg-gray-50 { background-color: #f9fafb; }

    .year-section {
        margin-bottom: 2rem;
    }
    .year-title {
        font-size: 1.2rem;
        font-weight: bold;
        border-bottom: 2px solid #2d63c8;
        padding-bottom: 0.3rem;
        margin-bottom: 1rem;
        color: #2d63c8;
    }
    .installments-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .installment-item {
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 8px;
        width: 100px;
        text-align: center;
        background-color: #fff;
    }
    .installment-item.fully-paid {
        border-color: #166534;
        background-color: #f0fdf4;
    }
    .installment-item.pending {
        border-color: #b91c1c;
        background-color: #fef2f2;
    }
    .installment-item.partial {
        border-color: #ca8a04;
        background-color: #fefce8;
    }
    .inst-date {
        font-size: 0.85rem;
        font-weight: bold;
        margin-bottom: 4px;
        display: block;
    }
    .inst-amount {
        font-size: 0.75rem;
        font-family: monospace;
        display: block;
    }
    .inst-status {
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: bold;
        margin-top: 4px;
        display: block;
    }
@endsection

@section('content')
<h2>{{ config('app.name') }} - Resumen de Cuenta</h2>

<table>
    <tr>
        <td width="15%" class="bg-gray-50 font-bold">Estudiante</td>
        <td>{{ $user->full_name }} (ID: {{ $user->id }})</td>
        <td width="15%" class="bg-gray-50 font-bold">Fecha Reporte</td>
        <td width="20%">{{ now()->format('d/m/Y H:i') }}</td>
    </tr>
</table>

<h4>Vista Gráfica por Período</h4>
@php
    $groupedPayments = $payments->groupBy(fn($p) => $p->date->year);
@endphp

@foreach($groupedPayments as $year => $yearPayments)
    <div class="year-section">
        <div class="year-title">Año {{ $year }}</div>
        <div class="installments-container">
            @foreach($yearPayments as $payment)
                @php
                    $status = 'pending';
                    $statusText = 'Pendiente';
                    if ($payment->paid >= $payment->amount) {
                        $status = 'fully-paid';
                        $statusText = 'Pagado';
                    } elseif ($payment->paid > 0) {
                        $status = 'partial';
                        $statusText = 'Parcial';
                    }
                @endphp
                <div class="installment-item {{ $status }}">
                    <span class="inst-date">{{ $payment->date->format('m/Y') }}</span>
                    <span class="inst-amount">$ {{ number_format($payment->paid, 0, ',', '.') }}</span>
                    <span class="inst-status {{ 'text-' . ($status === 'fully-paid' ? 'success' : ($status === 'partial' ? 'warning' : 'error')) }}">
                        {{ $statusText }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endforeach

@if($receipts->isNotEmpty())
    <h4>Historial de Recibos de Pago</h4>
    <table>
        <thead>
            <tr>
                <th>Nro. Recibo</th>
                <th>Fecha</th>
                <th>Caja</th>
                <th>Descripción</th>
                <th class="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipts as $receipt)
                <tr>
                    <td class="center font-bold"># {{ str_pad($receipt->id, 8, '0', STR_PAD_LEFT) }}</td>
                    <td class="center">{{ $receipt->created_at->format('d/m/Y H:i') }}</td>
                    <td class="center">{{ $receipt->paymentBox }}</td>
                    <td class="text-xs">{{ $receipt->description }}</td>
                    <td class="right font-mono font-bold">$ {{ number_format($receipt->paymentAmount, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div style="margin-top: 3rem; text-align: center; font-size: 0.8rem; color: #999;">
    Documento generado digitalmente por el Sistema de Administración Escolar.
</div>
@endsection