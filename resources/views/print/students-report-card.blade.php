@extends('print.layouts.report')

@section('orientation', 'landscape')
@section('title', 'Boletín - '.config('app.name'))
@section('print-button-label', 'Imprimir Boletín')

@section('content')
  <h2>{{ $data['shortname'] }} - {{ $data['longname'] }}</h2>

  <table style="border: none; margin-bottom: 1.5rem;">
    <tr>
      <td style="border: none; padding: 0;">
        <div style="font-size: 1.1rem; color: #111827;">
          <span style="font-weight: 600;">{{ auth()->user()->lastname }}, {{ auth()->user()->firstname }}</span>
          <span class="badge" style="margin-left: 0.5rem;">DNI: {{ auth()->user()->pid }}</span>
        </div>
        <div style="margin-top: 0.25rem; color: #6b7280; font-size: 0.9rem;">
          Reporte de Calificaciones Académicas
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
        <th style="width: 120px;">Fecha</th>
        <th>Descripción de la Materia / Evaluación</th>
        <th style="width: 100px; text-align: center;">Calificación</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($grades as $grade)
        <tr>
          <td>{{ date('d/m/Y', strtotime($grade->date_id)) }}</td>
          <td>
            <div style="font-weight: 600; color: #111827;">{{ $grade->subject->name }}</div>
            <div style="font-size: 0.75rem; color: #6b7280;">{{ $grade->name }}</div>
          </td>
          <td style="text-align: center;">
            <span style="font-weight: 700; font-size: 1.1rem; color: {{ $grade->grade < 4 ? '#dc2626' : ($grade->grade < 7 ? '#d97706' : '#059669') }};">
              {{ $grade->grade }}
            </span>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
