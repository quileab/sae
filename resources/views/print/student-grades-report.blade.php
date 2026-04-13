<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Calificaciones - {{ config('app.name') }}</title>
  <style>
    * {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 0;
      margin: 0;
      box-sizing: border-box;
    }

    body {
      margin: 1.5rem;
      color: #1f2937;
      background-color: #fff;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f3f4f6;
    }

    .header-logo {
      max-width: 80px;
      margin-right: 1.5rem;
    }

    .header-info h2 {
      font-size: 1.25rem;
      color: #111827;
    }

    .header-right {
      text-align: right;
      font-size: 0.875rem;
      color: #4b5563;
    }

    table {
      width: 100%;
      border: 1px solid #e5e7eb;
      border-collapse: collapse;
      font-size: 9px;
    }

    th, td {
      border: 1px solid #e5e7eb;
      padding: 4px;
      text-align: left;
    }

    th {
      background-color: #f9fafb;
      font-weight: 600;
      color: #374151;
    }

    .rotated-text {
      writing-mode: vertical-rl;
      transform: rotate(180deg);
      white-space: nowrap;
      padding: 8px 2px;
      height: 60px;
      text-align: center;
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
      transition: all 0.2s;
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
    }

    .btn-close {
      color: #374151;
      background-color: #ffffff;
      border-color: #d1d5db;
    }

    .btn-close:hover {
      background-color: #f3f4f6;
      transform: translateY(-2px);
    }

    .total-cell {
      background-color: #f3f4f6;
      font-weight: 600;
      white-space: nowrap;
    }

    .alert-row {
      background-color: #fffbeb !important;
    }

    .footer {
      margin-top: 1rem;
      text-align: center;
      font-size: 10px;
      color: #9ca3af;
    }
  </style>

  <style media="print">
    @page {
      size: A4 landscape;
      margin: 1cm;
    }
    body { margin: 0; }
    .dontPrint { display: none !important; }
  </style>
</head>
<body>

  <div class="dontPrint">
    <button type="button" class="btn btn-print" onclick="window.print();return false;">
      <span>🖨️</span> Imprimir Planilla
    </button>
    <button type="button" class="btn btn-close" onclick="window.close();">
      <span>✕</span> Cerrar
    </button>
  </div>

  <div class="header">
    <div style="display: flex; align-items: center;">
      @if($config->logo)
        <img src="{{ asset($config->logo) }}" alt="Logo" class="header-logo">
      @endif
      <div class="header-info">
        <h2>{{ $config->longname }}</h2>
        <div style="color: #6b7280; font-size: 0.9rem;">Planilla de Seguimiento Académico</div>
      </div>
    </div>
    <div class="header-right">
      <div style="font-weight: 700; color: #111827; font-size: 1rem;">{{ $subject->name }}</div>
      <div>{{ $subject->career->name }}</div>
      <div>Ciclo Lectivo: {{ request()->query('cycle') ?? date('Y') }}</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th rowspan="2" style="width: 180px;">Apellido y Nombre</th>
        <th rowspan="2" style="width: 70px;">DNI</th>
        <th colspan="{{ reset($reportData)['total_classes_q1'] + 1 }}" style="text-align: center; background-color: #ecfdf5;">1er Cuatrimestre</th>
        <th colspan="{{ reset($reportData)['total_classes_q2'] + 1 }}" style="text-align: center; background-color: #eff6ff;">2do Cuatrimestre</th>
        <th colspan="1" style="text-align: center; background-color: #fef2f2;">Final</th>
      </tr>
      <tr>
        @foreach (reset($reportData)['classSessions_q1'] as $session)
          <th class="rotated-text">{{ date('d/m', strtotime($session->date)) }}</th>
        @endforeach
        <th class="total-cell">PROX. Q1</th>
        @foreach (reset($reportData)['classSessions_q2'] as $session)
          <th class="rotated-text">{{ date('d/m', strtotime($session->date)) }}</th>
        @endforeach
        <th class="total-cell">PROX. Q2</th>
        <th class="total-cell">ANUAL</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($reportData as $data)
        @php
          $q1_alert = round($data['attendance_q1']['percentage']) < 75;
          $q2_alert = round($data['attendance_q2']['percentage']) < 75;
        @endphp
        <tr class="{{ ($q1_alert || $q2_alert) ? 'alert-row' : '' }}">
          <td style="font-weight: 500;">{{ $data['student']->lastname }}, {{ $data['student']->firstname }}</td>
          <td style="color: #6b7280;">{{ $data['student']->id }}</td>
          
          {{-- Q1 Sessions --}}
          @foreach ($data['classSessions_q1'] as $session)
            <td style="text-align: center">
              @php $grade = $data['grades_q1']->get($session->id); @endphp
              @if ($grade)
                <div style="font-size: 8px;">{{ $grade->attendance }}%</div>
                @if (str_starts_with(strtolower($grade->comments), 'ev') || str_starts_with(strtolower($grade->comments), 'tp'))
                  @php
                    $type = strtoupper(substr($grade->comments, 0, 2));
                    $val = ($grade->grade == 0 && $grade->approved == 1) ? 'A' : $grade->grade;
                  @endphp
                  <div style="font-weight: 700; color: #570df8;">{{ $type }}:{{ $val }}</div>
                @endif
              @else - @endif
            </td>
          @endforeach
          <td class="total-cell">
            <span style="color: {{ $q1_alert ? '#dc2626' : '#059669' }};">As:{{ round($data['attendance_q1']['percentage']) }}%</span><br>
            EV:{{ round($data['avg_ev_q1'], 1) }}
          </td>

          {{-- Q2 Sessions --}}
          @foreach ($data['classSessions_q2'] as $session)
            <td style="text-align: center">
              @php $grade = $data['grades_q2']->get($session->id); @endphp
              @if ($grade)
                <div style="font-size: 8px;">{{ $grade->attendance }}%</div>
                @if (str_starts_with(strtolower($grade->comments), 'ev') || str_starts_with(strtolower($grade->comments), 'tp'))
                  @php
                    $type = strtoupper(substr($grade->comments, 0, 2));
                    $val = ($grade->grade == 0 && $grade->approved == 1) ? 'A' : $grade->grade;
                  @endphp
                  <div style="font-weight: 700; color: #570df8;">{{ $type }}:{{ $val }}</div>
                @endif
              @else - @endif
            </td>
          @endforeach
          <td class="total-cell">
            <span style="color: {{ $q2_alert ? '#dc2626' : '#059669' }};">As:{{ round($data['attendance_q2']['percentage']) }}%</span><br>
            EV:{{ round($data['avg_ev_q2'], 1) }}
          </td>

          {{-- Annual Totals --}}
          <td class="total-cell" style="text-align: center; border-left: 2px solid #e5e7eb;">
            <div style="font-weight: 800; font-size: 10px;">{{ round($data['annual_attendance_percentage']) }}%</div>
            <div style="color: #570df8;">P:{{ round($data['annual_avg_ev'], 1) }}</div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="footer">
    Reporte generado desde el Sistema de Gestión Académica • {{ date('d/m/Y H:i') }}
  </div>

</body>
</html>