<style>
  * {
    font-family: Arial, Helvetica, sans-serif;
    padding:0px;
    margin:0px;
  }

  body {
    margin:1rem;
  }

  hr {
    height: 1rem;
    border: 0px;
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
@media print {

@page {
  size: A4 landscape;
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
  <div class="dontPrint" style="width:100%; text-align:right; padding:0.4rem; margin-bottom:1rem; background-color: #ddd; border:3px solid #aaa;">
    <button type="button" onclick="window.print();return false;"
      style=".">🖨️ Imprimir</button>
    <button type="button" onclick="window.close();return false;"
      style=".">❌ Cerrar</button>
  </div>
  <h2>{{ $data['shortname'] }} - {{ $data['longname'] }}</h2>

  <table>
    <tr>
      <td>
        {{ auth()->user()->lastname }}, {{ auth()->user()->firstname }} » ({{ auth()->user()->pid }})
      </td>
      <td class='right'>
        {{ date('d-m-Y H:i', strtotime(now())) }}      
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
      </tr>
    </thead>
    <tbody>
      @foreach ($grades as $grade)
        <tr>
          <td>{{ date('d-m-Y', strtotime($grade->date_id)) }}</td>
          <td><b>{{ $grade->subject->name }}</b> » {{ $grade->name }}</td>      
          <td style="text-align: center;">{{ $grade->grade }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
