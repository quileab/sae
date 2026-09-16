@extends('print.layouts.report')

@section('title', 'Asistencia - '.config('app.name'))

@section('content')
  <h2>{{ $config['shortname'] }} - {{ $config['longname'] }}</h2>

  <table style="border: none; margin-bottom: 1.5rem;">
    <tr>
      <td style="border: none; padding: 0;">
        <span style="font-weight: 600; font-size: 1.1rem;">{{ $data['subject']->name }}</span>
        <span class="badge" style="margin-left: 0.5rem;">ID: {{ $data['subject']->id }}</span>
        <div style="margin-top: 0.25rem; color: #6b7280; font-size: 0.9rem;">
          Reporte de Asistencia Acumulada
        </div>
      </td>
      <td class="right" style="border: none; padding: 0; vertical-align: top; color: #6b7280;">
        Generado: {{ date('d/m/Y H:i') }}
      </td>
    </tr>
  </table>

  <table>
    <thead>
      <tr>
        <th style="width: 50px; text-align: center;">#</th>
        <th>Apellido y Nombre/s</th>
        <th style="width: 150px; text-align: center;">Asistencia</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($students as $student)
        <tr>
          <td style="text-align: center; color: #9ca3af;">{{ $loop->iteration }}</td>
          <td style="font-weight: 500;">{{ $student->lastname }}, {{ $student->firstname }}</td>
          <td style="text-align: center;">
            <span style="font-weight: 600; color: {{ $student->attendance <= 75 ? '#dc2626' : '#059669' }};">
              {{ $student->attendance }}%
            </span>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
