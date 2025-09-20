<html>

<head>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        table.waffle {
            width: 100%;
            border-collapse: collapse;
        }

        table.waffle th,
        table.waffle td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
        }

        thead {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .rotate-text {
            transform: rotate(-90deg);
            white-space: wrap;
            display: inline-block;
            text-align: center;
        }

        .s18 {
            height: 6rem;
            width: 0rem;
            font-size: 12px;
            font-weight: lighter;
        }
    </style>
</head>

<body>
    <table class="waffle">
        <thead>
            <tr>
                <td colspan="20">INSTITUTO SUPERIOR PARTICULAR INCORPORADO Nº 4013 "PADRE JOAQUÍN BONALDO"</td>
                <td rowspan="3">Ciclo Lectivo<br><span style="font-size:14pt;font-weight:bold;">{{ date('Y') }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="20">Carrera: {{ $subject->career->name }}</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right;">Asignatura:</td>
                <td colspan="12" style="text-align: left;">{{ $subject->name }}</td>
                <td colspan="3" style="text-align: right;">CURSO:</td>
                <td colspan="2">{{ $subject->course_year }}º</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right;">Profesor/a:</td>
                <td colspan="10" style="text-align: left;">{{ $subject->teacher->fullName ?? 'N/A' }}</td>
                <td colspan="8">FORMATO: MATERIA</td>
            </tr>
            <tr>
                <th class="s18" rowspan="2"><span class="rotate-text">ORDEN</span></th>
                <th rowspan="2">APELLIDO Y NOMBRES</th>
                <th rowspan="2">D.N.I.</th>
                <th colspan="5">PRIMER CUATRIMESTRE</th>
                <th colspan="2">Reincorp. Asistencia</th>
                <th colspan="5">SEGUNDO CUATRIMESTRE</th>
                <th colspan="2">Reincorp. Asistencia</th>
                <th colspan="2">Regulariza</th>
                <th></th>
                <th rowspan="2">Promedio<br>de parciales</th>
            </tr>
            <tr>
                <th class="s18"><span class="rotate-text">Trabajos Prácticos</span></th>
                <th class="s18"><span class="rotate-text">Recuperatorio T. Prácticos</span></th>
                <th class="s18"><span class="rotate-text">Parcial</span></th>
                <th class="s18"><span class="rotate-text">Recuperatorio del Parcial</span></th>
                <th class="s18"><span class="rotate-text">% Asistencia</span></th>
                <th class="s18"><span class="rotate-text">Parcial Reincorporac.</span></th>
                <th class="s18"><span class="rotate-text">Recuperat. Reincorporac.</span></th>
                <th class="s18"><span class="rotate-text">Trabajos Prácticos</span></th>
                <th class="s18"><span class="rotate-text">Recuperatorio T. Prácticos</span></th>
                <th class="s18"><span class="rotate-text">Parcial</span></th>
                <th class="s18"><span class="rotate-text">Recuperatorio del Parcial</span></th>
                <th class="s18"><span class="rotate-text">% Asistencia</span></th>
                <th class="s18"><span class="rotate-text">Parcial Reincorporac.</span></th>
                <th class="s18"><span class="rotate-text">Recuperat. Reincorporac.</span></th>
                <th>SI</th>
                <th>NO</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $index => $data)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: left;">{{ $data['student']->lastname }}, {{ $data['student']->firstname }}</td>
                    <td>{{ $data['student']->id }}</td>
                    <td>{{ ($value = round($data['first_semester']['tp'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ ($value = round($data['first_semester']['rec_tp'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ ($value = round($data['first_semester']['ev'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ ($value = round($data['first_semester']['rec_ev'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ round($data['first_semester']['attendance']) }}%</td>
                    <td></td>
                    <td></td>
                    <td>{{ ($value = round($data['second_semester']['tp'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ ($value = round($data['second_semester']['rec_tp'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ ($value = round($data['second_semester']['ev'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ ($value = round($data['second_semester']['rec_ev'], 2)) == 0 ? '' : $value }}</td>
                    <td>{{ round($data['second_semester']['attendance']) }}%</td>
                    <td></td>
                    <td></td>
                    <td>{{ $data['regularized'] ? 'X' : '' }}</td>
                    <td>{{ !$data['regularized'] ? 'X' : '' }}</td>
                    <td></td>
                    <td>{{ ($value = round(($data['first_semester']['ev'] + $data['second_semester']['ev']) / 2, 2)) == 0 ? '' : $value }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>