<html>

<head>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            padding: 0px;
            margin: 0px;
            box-sizing: border-box;
        }

        body {
            margin: 1rem;
        }

        h2 {
            margin: 0rem;
            padding: 0rem;
        }

        h4 {
            margin: 0rem;
            padding: 0rem;
        }

        table {
            width: 100%;
            border: 1px solid;
            border-collapse: collapse;
        }

        table td,
        table th {
            border: 1px solid;
            padding: 0.4rem 0.5rem;
        }

        table tr {
            page-break-inside: avoid !important;
        }

        .dontPrint {
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            text-align: right;
            padding: 1rem;
            background-color: #aaaaaa55;
            backdrop-filter: blur(2px);
            box-shadow: 0 5px 10px #999999;
        }

        .right {
            text-align: right;
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
    </style>

    <style media="print">
        /* @page {size:landscape}  */
        @media print {

            @page {
                size: A4 portrait;
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
    <div class="dontPrint">
        <button type="button" onclick="window.print();return false;" style=".">🖨️ Imprimir</button>
        <button type="button" onclick="history.back(); window.close();return false;" style=".">❌ Cerrar</button>
    </div>
    <table class="waffle">
        <thead>
            <tr>
                <td colspan="6">
                    <h2>{{ $config['shortname'] }} - {{ $config['longname'] }}</h2>
                </td>
            </tr>
            <tr>
                <td colspan="6">Carrera: <strong>{{ $subject->career->name }}</strong> /
                    Asignatura: <strong>{{ $subject->name }}</strong>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right;"></td>
                <td colspan="3" style="text-align: right;">Profesor/a: {{ $subject->teacher->fullName ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>#</th>
                <th>APELLIDO Y NOMBRES</th>
                <th>D.N.I.</th>
                <th>Q1 ({{$total_classes_q1}} clases)</th>
                <th>Q2 ({{$total_classes_q2}} clases)</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $index => $data)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="text-align: left;">{{ $data['student']->lastname }}, {{ $data['student']->firstname }}</td>
                    <td>{{ $data['student']->id }}</td>
                    <td>{{ $data['attendance_q1']['count'] }} ({{ round($data['attendance_q1']['percentage']) }}%)</td>
                    <td>{{ $data['attendance_q2']['count'] }} ({{ round($data['attendance_q2']['percentage']) }}%)</td>
                    <td>{{ $data['attendance_q1']['count'] + $data['attendance_q2']['count'] }}
                        ({{ ($total_classes_q1 + $total_classes_q2) > 0 ? round(($data['attendance_q1']['count'] + $data['attendance_q2']['count']) / ($total_classes_q1 + $total_classes_q2) * 100) : 0 }}%)
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>