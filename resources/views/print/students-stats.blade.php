@extends('print.layouts.report')

@section('title', 'Estadísticas Estudiante - '.config('app.name'))

@section('styles')
    .EV { background-color: rgba(214, 255, 208, 0.3) !important; }
    .TP { background-color: rgba(255, 254, 221, 0.3) !important; }
    .FI { background-color: rgba(221, 244, 255, 0.3) !important; }
@endsection

@section('content')
  <h2>{{ $data['config']['shortname'] }} - {{ $data['config']['longname'] }}</h2>

  <table style="border: none; margin-bottom: 1.5rem;">
    <tr>
      <td style="border: none; padding: 0;">
        <span style="font-weight: 600; font-size: 1.1rem; color: #111827;">{{ $subject->name }}</span>
        <span class="badge" style="margin-left: 0.5rem;">Materia ID: {{ $subject->id }}</span>
        <div style="margin-top: 0.5rem; color: #374151;">
          Estudiante: <span style="font-weight: 600;">{{ $student->lastname }}, {{ $student->firstname }}</span>
          <br>
          <span style="font-size: 0.8rem; color: #6b7280;">{{ $student->email }} - {{ $student->phone }}</span>
        </div>
      </td>
      <td class="right" style="border: none; padding: 0; vertical-align: top; color: #6b7280;">
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
            @if ($class->approved) <span style="color: #059669;">OK</span> @endif
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
          @if($data['classCount'] > 0)
            {{ ceil($data['sumAttendance'] / $data['classCount']) }}%
          @endif
        </td>
      </tr>
      <tr>
        <td style="font-weight: 600;">Promedio de Notas</td>
        <td class="center">EV: {{ $data['countEV'] }} / TP: {{ $data['countTP'] }}</td>
        <td class="center" style="font-weight: 700; color: #111827;">
          EV:
          @if($data['countEV'] > 0)
            {{ ceil($data['sumEV'] / $data['countEV']) }}
          @else
            -
          @endif
          / TP:
          @if($data['countTP'] > 0)
            {{ ceil($data['sumTP'] / $data['countTP']) }}
          @else
            -
          @endif
        </td>
      </tr>
    </tbody>
  </table>
@endsection
