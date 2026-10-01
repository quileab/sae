@extends('print.layouts.report')

@section('title', 'Pagos de Estudiantes - '.config('app.name'))

@section('styles')
    h1 {
      margin: 0;
      padding: 0;
      margin-bottom: 1.5rem;
    }

    table {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-collapse: collapse;
    }

    table td,
    table th {
      border: 1px solid #e5e7eb;
      padding: 0.75rem;
      font-size: 0.875rem;
    }

    table th {
      background-color: #f9fafb;
      font-weight: 600;
      text-align: left;
    }

    table tr {
      page-break-inside: avoid !important;
    }

    .text-right {
      text-align: right;
    }
@endsection

@section('content')
<h1>Pagos de Estudiantes</h1>
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>ID</th>
            <th>Usuario</th>
            <th>Descripción</th>
            <th class="text-right">Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->created_at->format('d/m/Y') }}</td>
                <td>{{ $payment->id }}</td>
                <td>{{ $payment->user->lastname }}, {{ $payment->user->firstname }}</td>
                <td>{{ $payment->description }}</td>
                <td class="text-right">{{ number_format($payment->paymentAmount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" style="text-align: right;"><strong>Total:</strong></td>
            <td class="text-right"><strong>{{ number_format($payments->sum('paymentAmount'), 2) }}</strong></td>
        </tr>
    </tfoot>
</table>
@endsection