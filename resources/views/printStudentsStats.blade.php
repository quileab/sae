<style>
  * {
    font-family: Arial, Helvetica, sans-serif;
    padding:0px;
    margin:0px;
  }

  hr {
    height: 1rem;
    border: 0px;
  }

  body {
    margin:1rem;
  }

  h2{
    margin: 0rem;
    padding: 0rem;
  }
  h4{
    margin: 0rem;
    padding: 0rem;
  }

  table{
    width:100%; border:2px solid; border-collapse:collapse;
  }

  table td, table th{
    border:1px solid;
    padding:0.4rem 0.5rem;
  }

  table th{
    border-bottom: 2px solid black;
    background-color:#eee;
  }
  
  table tr{
    page-break-inside: avoid !important;
  }

  .right{
    text-align:right;
  }
  .center{
    text-align:center;
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
/* Grade type */
.EV{
  background-color:rgb(214, 255, 208)
}
.TP{
  background-color:rgb(255, 254, 221)
}
.FI{
  background-color:rgb(221, 244, 255)
}

</style>

<style media="print">
@media print {

@page {
  size: A4 portrait;
  max-height:100%;
  max-width:100%;
  margin: 1cm;
}

body {
  width:100%;
  height:100%;
  margin: 0cm;
  padding: 0cm;
  }    
}

.dontPrint {
     display:none;
}

</style>   
  <div class="dontPrint" style="position:relative; top:0px; left:0px; width:100%; text-align:right; padding:0.4rem; margin-bottom:1rem; background-color: #ddd; border:3px solid #aaa;">
    <button type="button" onclick="window.print();return false;"
      style=".">🖨️ Imprimir</button>
    <button type="button" onclick="window.close();return false;"
      style=".">❌ Cerrar</button>
  </div>
  <h2>{{ $data['config']['shortname'] }} - {{ $data['config']['longname'] }}</h2>

  <table>
    <tr>
      <td>
        {{ $subject->id }}: {{ $subject->name }}
      </td>
      <td class='right'>
        {{ date('d-m-Y H:i', strtotime(now())) }}
      </td>
    </tr>
    <tr>
      <td>
        {{ $student->id }}: {{ $student->lastname }}, {{ $student->firstname }}
      </td>
      <td class='right'>
        {{ $student->email }} / {{ $student->phone }}
      </td>
    </tr>
  </table>
  <hr />
  <table>
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Descripción</th>
        <th>Calif.</th>
        <th>Asistencia</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($classes as $class)
        <tr class="{{ $class->type }}">
          <td>{{ date('d-m-Y', strtotime($class->date_id)) }}</td>
          <td>{{ $class->name }}</td>
          <td class="right">@if ($class->approved) ✔️ @endif {{ $class->grade }}</td>
          <td class="center">{{ $class->attendance }}%</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <hr />
  <table>
    <thead>
      <tr>
        <th>Descripción</th>
        <th>Cant.</th>
        <th>%</th>
      </tr>
    </thead>
    <tbody>
    <tr>
      <td>Promedio de Asistencias</td>
      <td></td>
      <td class="right">
        @if($data['classCount']>0)
        {{ceil($data['sumAttendance']/$data['classCount'])}}%
        @endif
      </td>
    </tr>
    <tr>
      <td>Evaluaciones</td>
      <td class="right">{{ $data['countEV'] }}</td>
      <td class="right">Promedio {{ $data['countEV'] > 0 ? ceil($data['sumEV']/$data['countEV']) : '-'}}</td>
    </tr>
    <tr>
      <td>Trabajos Prácticos</td>
      <td class="right">{{ $data['countTP'] }}</td>
      <td class="right">Promedio {{ $data['countTP'] > 0 ? ceil($data['sumTP']/$data['countTP']) : '-'}}</td>
    </tr>
    </tbody>
  </table>