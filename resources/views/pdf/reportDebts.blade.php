<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Deudas - {{ now()->format('d/m/Y') }}</title>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            padding: 0;
            margin: 0;
        }
        body {
            margin: 1.5rem;
            color: #333;
        }
        h2 { margin-bottom: 0.5rem; color: #2d63c8; }
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
            font-size: 0.8rem;
        }
        table th {
            background-color: #f4f4f4;
            font-weight: bold;
            border-bottom: 2px solid #333;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .font-bold { font-weight: bold; }
        .text-error { color: #b91c1c; }
        .bg-gray-50 { background-color: #f9fafb; }
        @page { size: A4 portrait; margin: 1cm; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2>{{ config('app.name') }} - Reporte de Deudas</h2>
        <p>Fecha de referencia: {{ \Carbon\Carbon::parse($dateAsOf)->format('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Estudiante</th>
                <th>Carrera(s)</th>
                <th class="right">Deuda Total</th>
            </tr>
        </thead>
        <tbody>
            @php $totalGeneral = 0; @endphp
            @foreach ($students as $student)
                @php $totalGeneral += $student->total_debt; @endphp
                <tr>
                    <td class="center">{{ $student->id }}</td>
                    <td class="font-bold">{{ $student->lastname }}, {{ $student->firstname }}</td>
                    <td>
                        @foreach($student->careers as $career)
                            {{ $career->name }}{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    </td>
                    <td class="right font-mono text-error font-bold">
                        $ {{ number_format($student->total_debt, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-gray-50">
                <th colspan="3" class="right">DEUDA TOTAL GENERAL</th>
                <th class="right font-mono text-error font-bold" style="font-size: 1rem;">
                    $ {{ number_format($totalGeneral, 2, ',', '.') }}
                </th>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 3rem; text-align: center; font-size: 0.7rem; color: #999;">
        Documento generado digitalmente por el Sistema de Administración Escolar el {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
