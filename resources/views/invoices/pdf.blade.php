<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            margin: 2px 0;
        }
        .invoice-info {
            margin: 20px 0;
        }
        .invoice-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-info td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        .invoice-info td:first-child {
            font-weight: bold;
            width: 30%;
            background-color: #f5f5f5;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        .items-table td {
            padding: 6px;
            border-bottom: 1px solid #ddd;
        }
        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            float: right;
            width: 40%;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        .totals td:first-child {
            font-weight: bold;
        }
        .totals .total-row td {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 10px;
        }
        .footer {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
        }
        .cai-box {
            border: 2px solid #000;
            padding: 10px;
            margin: 20px 0;
        }
        .cai-box p {
            margin: 3px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FACTURA FISCAL</h1>
        <p><strong>{{ config('app.name', 'EMPRESA') }}</strong></p>
        <p>RTN: 0801-0000-00000</p>
        <p>{{ $invoice->sale->branch->address ?? 'Dirección' }}</p>
        <p>Tel: {{ $invoice->sale->branch->phone ?? 'N/A' }}</p>
    </div>

    <div class="cai-box">
        <p><strong>CAI:</strong> {{ $invoice->cai_number }}</p>
        <p><strong>Rango Autorizado:</strong> {{ $invoice->range_authorized }}</p>
        <p><strong>Fecha Límite de Emisión:</strong> {{ \Carbon\Carbon::parse($invoice->cai_expiration_date)->format('d/m/Y') }}</p>
    </div>

    <div class="invoice-info">
        <table>
            <tr>
                <td>Número de Factura:</td>
                <td><strong>{{ $invoice->invoice_number }}</strong></td>
            </tr>
            <tr>
                <td>Fecha de Emisión:</td>
                <td>{{ \Carbon\Carbon::parse($invoice->issued_at)->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Cliente:</td>
                <td>{{ $invoice->customer_name }}</td>
            </tr>
            <tr>
                <td>RTN del Cliente:</td>
                <td>{{ $invoice->customer_rtn }}</td>
            </tr>
            @if($invoice->customer_address)
            <tr>
                <td>Dirección:</td>
                <td>{{ $invoice->customer_address }}</td>
            </tr>
            @endif
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Desc.</th>
                <th class="text-right">ISV</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->sale->details as $detail)
            <tr>
                <td>{{ $detail->product->code ?? '-' }}</td>
                <td>{{ $detail->product_name }}</td>
                <td class="text-right">{{ number_format($detail->quantity, 2) }}</td>
                <td class="text-right">L {{ number_format($detail->price, 2) }}</td>
                <td class="text-right">L {{ number_format($detail->discount, 2) }}</td>
                <td class="text-right">L {{ number_format($detail->tax, 2) }}</td>
                <td class="text-right">L {{ number_format($detail->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">L {{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Descuento:</td>
                <td class="text-right">L {{ number_format($invoice->discount, 2) }}</td>
            </tr>
            <tr>
                <td>Subtotal Gravado:</td>
                <td class="text-right">L {{ number_format($invoice->subtotal_taxed, 2) }}</td>
            </tr>
            <tr>
                <td>Subtotal Exento:</td>
                <td class="text-right">L {{ number_format($invoice->subtotal_exempt, 2) }}</td>
            </tr>
            <tr>
                <td>ISV (15%):</td>
                <td class="text-right">L {{ number_format($invoice->tax, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL:</td>
                <td class="text-right">L {{ number_format($invoice->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p><strong>Valor en Letras:</strong> {{ $invoice->total_in_words }}</p>
        <p style="margin-top: 10px;">Original: Cliente | Copia: Archivo</p>
        <p>La factura es beneficio de todos. Exíjala.</p>
        @if($invoice->is_voided)
        <p style="color: red; font-weight: bold; font-size: 14px; margin-top: 10px;">*** FACTURA ANULADA ***</p>
        <p>Motivo: {{ $invoice->void_notes }}</p>
        @endif
    </div>
</body>
</html>
