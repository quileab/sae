<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>@yield('title', 'Reporte - '.config('app.name'))</title>
  <style>
    * {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 0;
      margin: 0;
      box-sizing: border-box;
    }

    body {
      margin: 2rem;
      color: #1f2937;
      background-color: #fff;
    }

    h2 {
      margin-bottom: 1rem;
      color: #111827;
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

    .right {
      text-align: right;
    }

    .center {
      text-align: center;
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
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
      box-shadow: 0 4px 12px rgba(87, 13, 248, 0.4);
    }

    .btn-close {
      color: #374151;
      background-color: #ffffff;
      border-color: #d1d5db;
    }

    .btn-close:hover {
      background-color: #f3f4f6;
      border-color: #9ca3af;
      transform: translateY(-2px);
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

    @yield('styles')
  </style>

  <style media="print">
    @page {
      size: A4 @yield('orientation', 'portrait');
      margin: 1.5cm;
    }

    body {
      margin: 0;
      background-color: #fff;
    }

    .dontPrint {
      display: none !important;
    }
  </style>
</head>
<body>
  <div class="dontPrint">
    <button type="button" class="btn btn-print" onclick="window.print();return false;">
      @yield('print-button-label', 'Imprimir Reporte')
    </button>
    <button type="button" class="btn btn-close" onclick="window.close();">
      Cerrar
    </button>
  </div>

  @yield('content')
</body>
</html>
