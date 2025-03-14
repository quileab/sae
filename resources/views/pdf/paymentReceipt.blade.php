<!DOCTYPE html>
<html lang="es">
@php
// $user = \App\Models\User::find(15);
// // get last payment of $user_id from paymentrecord table
// $paymentrecord = \App\Models\PaymentRecord::where('user_id', $user->id)
//     ->orderBy('id', 'desc')
//     ->first();

//     $data = [
//       'user' => $user,
//       'payment' => $paymentrecord,
//       'paymentDescription' => $paymentrecord->description,
//       'paymentAmount' => $paymentrecord->paymentAmount,
//       'paymentDate' => $paymentrecord->created_at,
//     ];
    
$amount = number_format($data['payment']->paymentAmount, 2);
$amountWords = NumberFormatter::create('es', NumberFormatter::SPELLOUT)->format($data['payment']->paymentAmount);
$copies = ['ORIGINAL', 'DUPLICADO'];
$copy = 'SINGLE';

@endphp

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Document</title>
  <style>
    /* import from google fonts open sans */
    @import url('https://fonts.googleapis.com/css?family=Open+Sans:400,700');

    /* page size A4, all borders 1 cm */
    @page {
      size: A4;
      margin: 1cm;
    }

    /* page header */
    header {
      position: fixed;
      top: 0cm;
      left: 0cm;
      right: 0cm;
      height: 3cm;
    }

    /* page footer */
    footer {
      position: fixed;
      bottom: 0cm;
      left: 0cm;
      right: 0cm;
      height: 3cm;
    }

    footer .page:after {
      counter-increment: pages content: counter(pages);
    }

    footer .page:before {
      content: counter(page) "/";
    }

    /* page body */
    body {
      font-family: 'Open Sans', sans-serif;
      /* padding-top: 3cm;
      padding-bottom: 3.1cm; */
      counter-reset: pages 1;
      /* set width to A4 minus margins */
      width: 19cm;
      /* set height to A4 minus margins */
      height: 27.7cm;
    }

    /* table */
    table {
      border-collapse: collapse;
      border-spacing: 0;
      font-family: 'Open Sans', sans-serif;
      font-size: 12px;
      width: 100%;
    }

    table thead {
      border: 0px solid #000;
      border-bottom: 0px solid #000;
    }

    table thead th {
      padding: 1mm;
      text-align: center;
    }

    table tbody td {
      padding: 1mm;
      text-align: center;
    }

    /*
    table tbody tr:nth-child(even) {
      background-color: #eee;
    }

    table tbody tr:nth-child(odd) {
      background-color: #fff;
    } */

    table tbody tr:last-child td {
      border-bottom: 0px solid #000;
    }

    /* table tfoot */
    table tfoot {
      border: 1px solid #000;
      border-top: 2px solid #000;
    }

    table tfoot td {
      padding: 1mm;
      text-align: center;
    }

    table tfoot tr:last-child td {
      border-bottom: 0px solid #000;
    }

    /* table tfoot total */
    table tfoot tr:last-child td:first-child {
      border-right: 0px solid #000;
    }

    table tfoot tr:last-child td:last-child {
      border-left: 0px solid #000;
    }

    p {
      margin: 0rem;
      padding: 0rem;
    }

    .inline-block {
      display: inline-block;
    }

    .inline-flex {
      display: inline-flex;
    }

    /* font sizes */
    .font-xs {
      font-size: .6rem;
    }

    .font-sm {
      font-size: .8rem;
    }

    .font-1 {
      font-size: 1rem;
    }

    .font-md {
      font-size: 1.2rem;
    }

    .font-lg {
      font-size: 1.4rem;
    }

    .font-xl {
      font-size: 1.6rem;
    }

    .font-xxl {
      font-size: 2rem;
    }

    /* font weights */
    .font-bold {
      font-weight: bold;
    }

    .font-light {
      font-weight: 300;
    }

    .font-normal {
      font-weight: normal;
    }

    /* text align */
    .text-left {
      text-align: left;
    }

    .text-center {
      text-align: center;
    }

    .text-right {
      text-align: right;
    }

    /* border */
    .border {
      border: 1px solid #000;
    }

    .border-top {
      border-top: 1px solid #000;
    }

    .border-bottom {
      border-bottom: 1px solid #000;
    }

    .border-left {
      border-left: 1px solid #000;
    }

    .border-right {
      border-right: 1px solid #000;
    }

    .borderless {
      border: 0px;
    }

    .flex {
      display: flex;
    }

    .inline-flex {
      display: inline-flex;
    }

    .inline-block {
      display: inline-block;
    }

    .page-break {
      page-break-after: always;
    }

    .w-full {
      width: 100%;
    }

    div {
      vertical-align: top;
    }

    hr{
      width: 100%;
      border-style: dashed none none;
      border-width: 2px;
      border-color: gray;
    }

  </style>
</head>

<body>
  @foreach ($copies as $copy)
    <div style="height: 49%;">
      <div style="padding-top:1cm;">
        <div class="inline-block text-center">
          <img style="height:3cm; width:auto;" src="./storage/imgs/logo.jpg"><br><br>
          RECIBO Nº: {{ str_pad($data['payment']->id, 6, '0', STR_PAD_LEFT) }}
        </div>
        <div class="inline-block text-center">
          <p class="font-bold font-md">
            ESCUELA DE EDUCACIÓN SECUNDARIA ORIENTADA<br>
            PARTICULAR INCORPORADA Nº 8206
          </p>
          <p class="font-bold font-1">
            "Roberto Vicentín"
          </p>
          <p class="font-sm">
            Calle 14 Nº 581 (3561) AVELLANEDA (Santa Fe) - Tel. (03482) 481182
          </p>
          <p class="font-xs">
            CUIT: 30-56780754-8 - IVA: Excento
          </p>
        </div>
      </div>

      <div class="w-full text-right">Avellaneda, {{ date('d/m/Y', strtotime($data['payment']->created_at)) }}</div>
      <div class="w-full text-left font-1">

        Recibí de: <b>{{ $data['user']->lastname }}, {{ $data['user']->firstname }}</b><br><br>
        Concepto: Pago voluntario, <b>{{ $data['paymentDescription'] }}</b><br><br>
        Curso/Div: <b>
          @foreach ($data['user']->careers as $career)
            {{ $career->name }} -
          @endforeach
        </b><br><br>
        la cantidad de pesos: <b>{{ $amountWords }}</b><br><br>
        <br>
        SON PESOS: <b>$ {{ $amount }}</b>
      </div>

      <div style="width:90%" class="text-right font-sm">Firma Autorizada</div>
      <div style="width:100ñ%" class="text-center font-sm">{{ $copy }}</div>
    </div>
    {!! $copy == $copies[0] ? '<hr>' : '' !!}
  @endforeach

</body>

</html>
