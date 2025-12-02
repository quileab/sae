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

        tbody tr:hover {
            background-color: #e0ffe0 !important;
        }

        button {
            color: #ffffff;
            background-color: #2d63c8;
            font-size: 19px;
            border: 1px solid #1b3a75;
            border-radius: 0.5rem;
            padding: 0.5rem 2rem;
            cursor: pointer
        }

        button:hover {
            background-color: #3271e7;
            color: #ffffff;
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

    <style media="print">
        /* @page {size:landscape}  */
        @media print {

            @page {
                size: A4 landscape;
                max-height: 100%;
                max-width: 100%;
                margin: 1cm;
            }

            body {
                width: 100%;
                height: 100%;
                margin: 0cm;
                padding: 0cm;
            }
        }

        .dontPrint {
            display: none;
        }
    </style>
</head>

<body>
    <div class="dontPrint"
        style="width:100%; text-align:right; padding:0.4rem; margin-bottom:1rem; background-color: #ddd; border:3px solid #aaa;">
        <button type="button" onclick="window.print();return false;" style=".">🖨️ Imprimir</button>
        <button type="button" onclick="window.close();return false;" style=".">❌ Cerrar</button>
    </div>
    <div class="header">
        <div class="header-left">
            <img src="{{ asset($config->logo) }}" alt="Logo" class="header-logo">
            <div class="header-text">
                <h2>{{ $config->longname }}</h2>
            </div>
        </div>
        <div class="header-right">
            <strong>{{ $subject->name }}</strong> | {{ $subject->career->name }}<br>
            Ciclo: {{ date('Y') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Apellido y Nombre</th>
                <th rowspan="2">DNI</th>
                <th colspan="{{ reset($reportData)['total_classes_q1'] + 1 }}">1er Cuatrimestre</th>
                <th colspan="{{ reset($reportData)['total_classes_q2'] + 1 }}">2do Cuatrimestre</th>
                <th colspan="3">Promedio General</th>
            </tr>
            <tr>
                @foreach (reset($reportData)['classSessions_q1'] as $session)
                    <th class="rotated-text">{{ date('d/m', strtotime($session->date)) }}</th>
                @endforeach
                <th>Totales</th>
                @foreach (reset($reportData)['classSessions_q2'] as $session)
                    <th class="rotated-text">{{ date('d/m', strtotime($session->date)) }}</th>
                @endforeach
                <th>Totales</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $data)
                <tr
                    style="{{ round($data['attendance_q1']['percentage']) < 75 || round($data['attendance_q2']['percentage']) < 75 ? 'background:#ffa;' : '' }}">
                    <td>{{ $data['student']->lastname }}, {{ $data['student']->firstname }}</td>
                    <td>{{ $data['student']->id }}</td>
                    @foreach ($data['classSessions_q1'] as $session)
                        <td style="text-align: center">
                            @php
                                $grade = $data['grades_q1']->get($session->id);
                            @endphp
                            @if ($grade)
                                {{ $grade->attendance > 0 ? $grade->attendance . '%' : '-' }}
                                @if (str_starts_with(strtolower($grade->comments), 'ev') || str_starts_with(strtolower($grade->comments), 'tp'))
                                    <br>
                                    @php
                                        $type = strtoupper(substr($grade->comments, 0, 2)); // "EV" or "TP"
                                        $gradeValue = ($grade->grade == 0 && $grade->approved == 1) ? 'Aprob.' : $grade->grade;
                                    @endphp
                                    <small>{{ $type }}. {{ $gradeValue }}</small>
                                @elseif ($grade->grade === 'Aprobado')
                                    <br><small>Aprobado</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                    <td>
                        Asis.:{{ round($data['attendance_q1']['percentage']) }}%<br>
                        Prom. EV: {{ round($data['avg_ev_q1'], 2) }}/{{ $data['count_ev_q1'] }}<br>
                        Prom. TP: {{ round($data['avg_tp_q1'], 2) }}/{{ $data['count_tp_q1'] }}
                    </td>
                    @foreach ($data['classSessions_q2'] as $session)
                        <td style="text-align: center">
                            @php
                                $grade = $data['grades_q2']->get($session->id);
                            @endphp
                            @if ($grade)
                                {{ $grade->attendance > 0 ? $grade->attendance . '%' : '-' }}
                                @if (str_starts_with(strtolower($grade->comments), 'ev') || str_starts_with(strtolower($grade->comments), 'tp'))
                                    <br>
                                    @php
                                        $type = strtoupper(substr($grade->comments, 0, 2)); // "EV" or "TP"
                                        $gradeValue = ($grade->grade == 0 && $grade->approved == 1) ? 'Aprob.' : $grade->grade;
                                    @endphp
                                    <small>{{ $type }}. {{ $gradeValue }}</small>
                                @elseif ($grade->grade === 'Aprobado')
                                    <br><small>Aprobado</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                    <td>
                        Asis.:{{ round($data['attendance_q2']['percentage']) }}%<br>
                        Prom. EV: {{ round($data['avg_ev_q2'], 2) }}/{{ $data['count_ev_q2'] }}<br>
                        Prom. TP: {{ round($data['avg_tp_q2'], 2) }}/{{ $data['count_tp_q2'] }}
                    </td>
                    <td>
                        Asis.:{{ round($data['annual_attendance_percentage']) }}%<br>
                        Prom. EV: {{ round($data['annual_avg_ev'], 2) }}/{{ $data['annual_count_ev'] }}<br>
                        Prom. TP: {{ round($data['annual_avg_tp'], 2) }}/{{ $data['annual_count_tp'] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado el {{ date('d/m/Y') }}</p>
    </div>
</body>

</html>