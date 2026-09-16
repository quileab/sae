@extends('print.layouts.report')

@section('orientation', 'landscape')
@section('title', 'Reporte - '.config('app.name'))

@section('styles')
    hr {
      border: 0;
      border-top: 1px solid #e5e7eb;
      margin: 0.5rem 0;
    }
@endsection

@section('content')
  <h2>{{ $config['shortname'] }} - {{ $config['longname'] }}</h2>

  <table style="border: none; margin-bottom: 2rem;">
    <tr>
      <td style="border: none; padding: 0;">
        <span style="font-weight: 600; font-size: 1.1rem;">{{ $data['subject']->name }}</span>
        <span class="badge" style="margin-left: 0.5rem;">ID: {{ $data['subject']->id }}</span>
        <div style="margin-top: 0.25rem; color: #6b7280;">
          Estudiante: <span style="font-weight: 600;">{{ $data['user']->lastname }}, {{ $data['user']->firstname }}</span>
          <span style="margin: 0 0.5rem;">-</span>
          Asistencia Total: <span style="font-weight: 600; color: #059669;">{{ $data['attendance'] ?? 'n/a' }}</span>
        </div>
      </td>
      <td class="right" style="border: none; padding: 0; vertical-align: top; color: #6b7280;">
        {{ date('d/m/Y H:i') }}
      </td>
    </tr>
  </table>

  <table>
    <thead>
      <tr>
        <th style="width: 100px;">Fecha</th>
        <th style="width: 120px;">Clase - Unidad</th>
        <th style="width: 80px;">Tipo</th>
        <th>Contenido y Evaluaciones</th>
        <th>Actividades</th>
        <th style="width: 150px;">Profesor</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($classbooks as $classbook)
        <tr>
          <td>{{ date('d/m/Y', strtotime($classbook->date)) }}</td>
          <td>
            <div style="font-weight: 600;">Clase {{ $classbook->class_number }}</div>
            <div style="font-size: 0.75rem; color: #6b7280;">Unidad {{ $classbook->unit }}</div>
          </td>
          <td><span class="badge">{{ $classbook->type }}</span></td>
          <td>
            <div style="margin-bottom: 0.5rem;">{{ $classbook->content }}</div>
            <div class="right">
              <hr>
              <span style="font-size: 0.75rem; color: #6b7280;">
                @if($classbook->grade > 0)
                  <span style="font-weight: 600; color: #111827;">Nota: {{ $classbook->grade }}</span>
                  <span style="margin: 0 0.25rem;">-</span>
                @endif
                Asistencia: <span style="font-weight: 600; color: #111827;">{{ $classbook->attendance }}%</span>
              </span>
            </div>
          </td>
          <td style="font-size: 0.8rem; color: #4b5563;">{{ $classbook->activities }}</td>
          <td>{{ $classbook->teacher_lastname ?? 'n/a' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
