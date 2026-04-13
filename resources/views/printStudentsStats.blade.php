<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Estadísticas Estudiante - {{ config('app.name') }}</title>
  <style>
    * {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 0;
      margin: 0;
      box-sizing: border-box;
    }

    body {
      margin: 2rem;
      color: #1f2937;
      background-color: #fff;
    }

    h2 {
      margin-bottom: 1rem;
      color: #111827;
    }

    table {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-collapse: collapse;
      margin-bottom: 1.5rem;
    }

    table td, table th {
      border: 1px solid #e5e7eb;
      padding: 0.75rem;
      font-size: 0.875rem;
    }

    table th {
      background-color: #f9fafb;
      font-weight: 600;
      text-align: left;
    }

    table tr:nth-child(even) {
      background-color: #fcfcfc;
    }

    table tr {
      page-break-inside: avoid !important;
    }

    .dontPrint {
      position: fixed;
      top: 1.5rem;
      right: 2rem;
      z-index: 1000;
      display: flex;
      justify-content: center;
      gap: 1rem;
      padding: 0.75rem 1.5rem;
      background-color: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(0, 0, 0, 0.1);
      border-radius: 9999px;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .right {
      text-align: right;
    }
    .center {
      text-align: center;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      font-size: 0.875rem;
      border-radius: 9999px;
      padding: 0.625rem 1.5rem;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid transparent;
      text-decoration: none;
    }

    .btn-print {
      color: #ffffff;
      background-color: #570df8;
    }

    .btn-print:hover {
      background-color: #4506cb;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(87, 13, 248, 0.4);
    }

    .btn-close {
      color: #374151;
      background-color: #ffffff;
      border-color: #d1d5db;
    }

    .btn-close:hover {
      background-color: #f3f4f6;
      border-color: #9ca3af;
      transform: translateY(-2px);
    }

    .badge {
      display: inline-block;
      padding: 0.125rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      background-color: #f3f4f6;
      color: #374151;
    }

    /* Grade types */
    .EV { background-color: rgba(214, 255, 208, 0.3) !important; }
    .TP { background-color: rgba(255, 254, 221, 0.3) !important; }
    .FI { background-color: rgba(221, 244, 255, 0.3) !important; }
  </style>

  <style media="print">
    @page {
      size: A4 portrait;
      margin: 1.5cm;
    }

    body {
      margin: 0;
      background-color: #fff;
    }

    .dontPrint {
      display: none !important;
    }
  </style>
</head>
<body>

  <div class="dontPrint">
    <button type="button" class="btn btn-print" onclick="window.print();return false;">
      <span>🖨️</span> Imprimir Reporte
    </button>
    <button type="button" class="btn btn-close" onclick="window.close();">
      <span>✕</span> Cerrar
    </button>
  </div>

  <h2>{{ $data['config']['shortname'] }} - {{ $data['config']['longname'] }}</h2>

  <table style="border: none; margin-bottom: 1.5rem;">
    <tr>
      <td style="border: none; padding: 0;">
        <span style="font-weight: 600; font-size: 1.1rem; color: #111827;">{{ $subject->name }}</span>
        <span class="badge" style="margin-left: 0.5rem;">Materia ID: {{ $subject->id }}</span>
        <div style="margin-top: 0.5rem; color: #374151;">
          Estudiante: <span style="font-weight: 600;">{{ $student->lastname }}, {{ $student->firstname }}</span>
          <br>
          <span style="font-size: 0.8rem; color: #6b7280;">{{ $student->email }} • {{ $student->phone }}</span>
        </div>
      </td>
      <td class='right' style="border: none; padding: 0; vertical-align: top; color: #6b7280;">
        Generado: {{ date('d/m/Y H:i') }}
      </td>
    </tr>
  </table>

  <h4 style="margin-bottom: 0.5rem; color: #374151;">Desglose de Clases y Calificaciones</h4>
  <table>
    <thead>
      <tr>
        <th style="width: 100px;">Fecha</th>
        <th>Descripción / Actividad</th>
        <th style="width: 80px; text-align: center;">Calif.</th>
        <th style="width: 100px; text-align: center;">Asistencia</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($classes as $class)
        <tr class="{{ $class->type }}">
          <td>{{ date('d/m/Y', strtotime($class->date_id)) }}</td>
          <td>
            <span class="badge" style="font-size: 0.65rem; margin-right: 0.25rem;">{{ $class->type }}</span>
            {{ $class->name }}
          </td>
          <td class="center">
            @if ($class->approved) <span style="color: #059669;">✔️</span> @endif 
            <span style="font-weight: 600;">{{ $class->grade }}</span>
          </td>
          <td class="center" style="font-weight: 500;">{{ $class->attendance }}%</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <h4 style="margin-bottom: 0.5rem; color: #374151; margin-top: 2rem;">Resumen de Rendimiento</h4>
  <table style="width: 50%; margin-left: 0;">
    <thead>
      <tr>
        <th>Concepto</th>
        <th style="text-align: center;">Cant.</th>
        <th style="text-align: center;">Resultado / Promedio</th>
      </tr>
    </thead>
    <tbody>
    <tr>
      <td style="font-weight: 600;">Promedio de Asistencias</td>
      <td class="center">-</td>
      <td class="center" style="font-weight: 700; color: #059669;">
        @if($data['classCount']>0)
        {{ceil($data['sumAttendance']/$data['classCount'])}}%
        @endif
      </td>
    </tr>
    <tr>
      <td>Evaluaciones (EV)</td>
      <td class="center">{{ $data['countEV'] }}</td>
      <td class="center" style="font-weight: 600;">{{ $data['countEV'] > 0 ? number_format($data['sumEV']/$data['countEV'], 1) : '-'}}</td>
    </tr>
    <tr>
      <td>Trabajos Prácticos (TP)</td>
      <td class="center">{{ $data['countTP'] }}</td>
      <td class="center" style="font-weight: 600;">{{ $data['countTP'] > 0 ? number_format($data['sumTP']/$data['countTP'], 1) : '-'}}</td>
    </tr>
    </tbody>
  </table>

</body>
</html>