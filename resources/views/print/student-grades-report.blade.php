<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Calificaciones</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 4px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-logo {
            max-width: 100px;
            margin-right: 20px;
        }

        .header-text h1,
        .header-text h2 {
            margin: 0;
        }

        .header-right {
            text-align: right;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
        }

        .rotated-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            padding-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-left">
            <img src="{{ asset($config->logo) }}" alt="Logo" class="header-logo">
            <div class="header-text">
                <h2>{{ $config->longname }}</h2>
            </div>
        </div>
        <div class="header-right">
            <h2>Reporte de Calificaciones</h2>
            <strong>{{ $subject->name }}</strong> | {{ $subject->career->name }}<br>
            Ciclo: {{ date('Y') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Apellido y Nombre</th>
                <th colspan="{{ $total_classes_q1 }}">1er Cuatrimestre</th>
                <th colspan="{{ $total_classes_q2 }}">2do Cuatrimestre</th>
            </tr>
            <tr>
                @foreach ($classSessions_q1 as $session)
                    <th>{{ date('d/m', strtotime($session->date)) }}</th>
                @endforeach
                @foreach ($classSessions_q2 as $session)
                    <th>{{ date('d/m', strtotime($session->date)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $data)
                <tr>
                    <td>{{ $data['student']->lastname }}, {{ $data['student']->firstname }}</td>
                    @foreach ($classSessions_q1 as $session)
                        <td style="text-align: center">
                            @php
                                $grade = $data['grades']->get($session->id);
                            @endphp
                            @if ($grade)
                                X
                                @if (str_starts_with(strtolower($grade->comments), 'tp') || str_starts_with(strtolower($grade->comments), 'ev'))
                                    <br>
                                    @if ($grade->grade == 0 && $grade->approved == 1)
                                        <small>Aprob.</small>
                                    @else
                                        <small>{{ substr($grade->comments, 0, 2) }}: {{ $grade->grade }}</small>
                                    @endif
                                @endif
                                @if ($grade->grade == 'Aprobado')
                                    <br>
                                    <small>Aprobado</small>
                                @endif
                            @endif
                        </td>
                    @endforeach
                    @foreach ($classSessions_q2 as $session)
                        <td style="text-align: center">
                            @php
                                $grade = $data['grades']->get($session->id);
                            @endphp
                            @if ($grade)
                                X
                                @if (str_starts_with($grade->comments, 'TP') || str_starts_with($grade->comments, 'EV'))
                                    <br>
                                    @if ($grade->grade == 0 && $grade->approved == 1)
                                        <small>Aprob.</small>
                                    @else
                                        <small>{{ $grade->comments }}: {{ $grade->grade }}</small>
                                    @endif
                                @endif
                                @if ($grade->grade == 'Aprobado')
                                    <br>
                                    <small>Aprobado</small>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado el {{ date('d/m/Y') }}</p>
    </div>
</body>

</html>