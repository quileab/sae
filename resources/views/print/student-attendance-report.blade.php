<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Asistencia - {{ config('app.name') }}</title>
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
      margin-bottom: 0.5rem;
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

  <header style="margin-bottom: 2rem;">
    <h2>{{ $config->shortname }} - {{ $config->longname }}</h2>
    <div style="font-size: 1.1rem; color: #111827; margin-bottom: 0.5rem;">
      Carrera: <span style="font-weight: 600;">{{ $subject->career->name }}</span>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
      <div>
        Asignatura: <span style="font-weight: 600;">{{ $subject->name }}</span>
        <div style="color: #6b7280; font-size: 0.9rem; margin-top: 0.25rem;">
          Reporte de Asistencia por Cuatrimestre
        </div>
      </div>
      <div style="text-align: right; color: #374151; font-size: 0.9rem;">
        Profesor/a: <span style="font-weight: 600;">{{ $subject->teacher->fullName ?? 'N/A' }}</span>
        <div style="color: #6b7280; font-size: 0.8rem;">Generado: {{ date('d/m/Y H:i') }}</div>
      </div>
    </div>
  </header>

  <table>
    <thead>
      <tr>
        <th>APELLIDO Y NOMBRES</th>
        <th style="width: 100px;">D.N.I.</th>
        <th style="width: 140px; text-align: center;">Q1 ({{$total_classes_q1}} clases)</th>
        <th style="width: 140px; text-align: center;">Q2 ({{$total_classes_q2}} clases)</th>
        <th style="width: 120px; text-align: center;">Total General</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($reportData as $index => $data)
        <tr>
          <td style="font-weight: 500;">{{ $data['student']->lastname }}, {{ $data['student']->firstname }}</td>
          <td>{{ $data['student']->id }}</td>
          <td style="text-align: center;">
            <div style="font-weight: 600;">{{ round($data['attendance_q1']['percentage']) }}%</div>
            <div style="font-size: 0.7rem; color: #6b7280;">{{ $data['attendance_q1']['count'] }} asistencias</div>
          </td>
          <td style="text-align: center;">
            <div style="font-weight: 600;">{{ round($data['attendance_q2']['percentage']) }}%</div>
            <div style="font-size: 0.7rem; color: #6b7280;">{{ $data['attendance_q2']['count'] }} asistencias</div>
          </td>
          <td style="text-align: center;">
            @php
              $total_asist = $data['attendance_q1']['count'] + $data['attendance_q2']['count'];
              $total_clases = $total_classes_q1 + $total_classes_q2;
              $percent = $total_clases > 0 ? round(($total_asist / $total_clases) * 100) : 0;
            @endphp
            <div style="font-weight: 700; color: {{ $percent < 75 ? '#dc2626' : '#059669' }};">{{ $percent }}%</div>
            <div style="font-size: 0.7rem; color: #6b7280;">{{ $total_asist }} / {{ $total_clases }}</div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

</body>
</html>