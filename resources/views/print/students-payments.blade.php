<!DOCTYPE html>
<html>

<head>
    <title>Pagos de Estudiantes</title>
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

        .text-right {
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
                    <td>{{ $payment->created_at->format('Y-m-d') }}</td>
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
</body>

</html>