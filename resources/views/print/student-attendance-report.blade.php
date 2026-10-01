@extends('print.layouts.report')

@section('title', 'Reporte de Asistencia - '.config('app.name'))

@section('styles')
    header {
      margin-bottom: 2rem;
    }

    header h2 {
      margin-bottom: 0.5rem;
      color: #111827;
    }
    
    header div {
      font-size: 1.1rem;
      color: #111827;
      margin-bottom: 0.5rem;
    }

    header div span {
      font-weight: 600;
    }

    .header-flex {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }

    .header-left div {
      color: #6b7280;
      font-size: 0.9rem;
      margin-top: 0.25rem;
    }

    .header-right {
      text-align: right;
      color: #374151;
      font-size: 0.9rem;
    }

    .header-right div {
      color: #6b7280;
      font-size: 0.8rem;
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

    .badge {
      display: inline-block;
      padding: 0.125rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      background-color: #f3f4f6;
      color: #374151;
    }
@endsection

@section('content')
<header>
    <h2>{{ $config->shortname }} - {{ $config->longname }}</h2>
    <div style="font-size: 1.1rem; color: #111827; margin-bottom: 0.5rem;">
      Carrera: <span style="font-weight: 600;">{{ $subject->career->name }}</span>
    </div>
    <div class="header-flex">
      <div>
        Asignatura: <span style="font-weight: 600;">{{ $subject->name }}</span>
        <div style="color: #6b7280; font-size: 0.9rem; margin-top: 0.25rem;">
          Reporte de Asistencia por Cuatrimestre
        </div>
      </div>
      <div class="header-right">
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
            <div style="font-weight: 700; color: {{ $percent <= 75 ? '#dc2626' : '#059669' }};">{{ $percent }}%</div>
            <div style="font-size: 0.7rem; color: #6b7280;">{{ $total_asist }} / {{ $total_clases }}</div>
          </td>
        </tr>
      @endforeach
    </tbody>
</table>
@endsection