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
      size: A4 landscape;
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
<div class="dontPrint">
  <button type="button" onclick="window.print();return false;" style=".">🖨️ Imprimir</button>
  <button type="button" onclick="history.back(); window.close();return false;" style=".">❌ Cerrar</button>
</div>
<h2>{{ $config['shortname'] }} - {{ $config['longname'] }}</h2>

<table>
  <tr>
    <td>
      <small>{{ $data['subject']->id }}</small>: {{ $data['subject']->name }}
      » {{$data['user']->lastname}},{{$data['user']->firstname}} »&nbsp;<small>Asistencia:
        {{$data['attendance'] ?? 'n/a' }}</small>
    </td>
    <td class='right'>
      {{ date('d-m-Y H:i', strtotime(now())) }}
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th>Fecha</th>
      <th>Clase - Unidad</th>
      <th>Tipo</th>
      <th>Contenido</th>
      <th>Actividades</th>
      {{-- <th>Observaciones</th> --}}
      <th>Profesor</th>
      {{-- <th>Autoridad</th> --}}
    </tr>
  </thead>
  <tbody>
    @foreach ($classbooks as $classbook)
    <tr>
      <td>{{ date('d-m-Y', strtotime($classbook->date)) }}</td>
      <td>{{ $classbook->class_number }} - {{ $classbook->unit }}</td>
      <td><small>{{ $classbook->type }}</small></td>
      <td>{{ $classbook->content }}
      <div class="right">
        <hr />
        <small>
        @if($classbook->grade > 0)
      Calif. {{ $classbook->grade }} -&nbsp;
      @endif
        Asist. {{ $classbook->attendance }}%
        </small>
      </div>
      </td>
      <td>{{ $classbook->activities }}</td>
      {{-- <td>{{ $classbook->Observations }}</td> --}}
      <td>{{ $classbook->teacher_lastname ?? 'n/a' }}</td>
      {{-- <td>{{ $classbook->Authority_user_id }}</td> --}}
    </tr>
  @endforeach
  </tbody>
</table>