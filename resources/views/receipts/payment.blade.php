<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago {{ $payment->payment_number }}</title>
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
            color: #2563eb;
        }
        .header p {
            margin: 2px 0;
        }
        .receipt-info {
            margin: 20px 0;
        }
        .receipt-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .receipt-info td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .receipt-info td:first-child {
            font-weight: bold;
            width: 35%;
            background-color: #f5f5f5;
        }
        .payment-box {
            background-color: #f0f9ff;
            border: 2px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .payment-box .amount {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin: 10px 0;
        }
        .payment-box .label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .allocations-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .allocations-table th {
            background-color: #1e40af;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        .allocations-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .allocations-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary {
            margin-top: 20px;
            float: right;
            width: 50%;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .summary td:first-child {
            font-weight: bold;
        }
        .summary .highlight {
            background-color: #dbeafe;
            font-size: 14px;
            font-weight: bold;
        }
        .footer {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 10px;
            text-align: center;
        }
        .signature-box {
            margin-top: 60px;
            text-align: center;
        }
        .signature-line {
            border-top: 2px solid #000;
            width: 300px;
            margin: 0 auto;
            padding-top: 5px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-cash {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-card {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-transfer {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-check {
            background-color: #f3e8ff;
            color: #6b21a8;
        }
        .badge-qr {
            background-color: #fce7f3;
            color: #9f1239;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RECIBO DE PAGO</h1>
        <p><strong>{{ $payment->branch->company->name ?? config('app.name', 'EMPRESA') }}</strong></p>
        @if($payment->branch->company->rtn ?? false)
        <p>RTN: {{ $payment->branch->company->rtn }}</p>
        @endif
        <p>{{ $payment->branch->address ?? 'Dirección' }}</p>
        <p>Tel: {{ $payment->branch->phone ?? 'N/A' }}</p>
    </div>

    <div class="receipt-info">
        <table>
            <tr>
                <td>No. de Recibo:</td>
                <td><strong>{{ $payment->payment_number }}</strong></td>
            </tr>
            <tr>
                <td>Fecha de Pago:</td>
                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Cliente:</td>
                <td><strong>{{ $payment->customer->name }}</strong></td>
            </tr>
            @if($payment->customer->rtn)
            <tr>
                <td>RTN del Cliente:</td>
                <td>{{ $payment->customer->rtn }}</td>
            </tr>
            @endif
            @if($payment->customer->phone)
            <tr>
                <td>Teléfono:</td>
                <td>{{ $payment->customer->phone }}</td>
            </tr>
            @endif
            <tr>
                <td>Método de Pago:</td>
                <td>
                    @php
                        $methodClass = match($payment->payment_method) {
                            'cash' => 'badge-cash',
                            'card' => 'badge-card',
                            'transfer' => 'badge-transfer',
                            'check' => 'badge-check',
                            'qr' => 'badge-qr',
                            default => 'badge-cash'
                        };
                        $methodLabel = match($payment->payment_method) {
                            'cash' => 'EFECTIVO',
                            'card' => 'TARJETA',
                            'transfer' => 'TRANSFERENCIA',
                            'check' => 'CHEQUE',
                            'qr' => 'QR',
                            default => strtoupper($payment->payment_method)
                        };
                    @endphp
                    <span class="badge {{ $methodClass }}">{{ $methodLabel }}</span>
                </td>
            </tr>
            <tr>
                <td>Recibido por:</td>
                <td>{{ $payment->user->name ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="payment-box">
        <div class="label">MONTO RECIBIDO</div>
        <div class="amount">L {{ number_format($payment->amount, 2) }}</div>
    </div>

    @if($payment->allocations->isNotEmpty())
    <h3 style="margin: 20px 0 10px 0; color: #1e40af;">Aplicación del Pago</h3>
    <table class="allocations-table">
        <thead>
            <tr>
                <th>No. Venta</th>
                <th>Fecha de Venta</th>
                <th>Fecha Vencimiento</th>
                <th class="text-right">Total Venta</th>
                <th class="text-right">Saldo Anterior</th>
                <th class="text-right">Pago Aplicado</th>
                <th class="text-right">Saldo Restante</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment->allocations as $allocation)
            <tr>
                <td>{{ $allocation->creditSale->sale->sale_number }}</td>
                <td>{{ \Carbon\Carbon::parse($allocation->creditSale->sale->sold_at)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($allocation->creditSale->due_date)->format('d/m/Y') }}</td>
                <td class="text-right">L {{ number_format($allocation->creditSale->original_amount, 2) }}</td>
                <td class="text-right">L {{ number_format($allocation->creditSale->balance_due + $allocation->amount_allocated, 2) }}</td>
                <td class="text-right"><strong>L {{ number_format($allocation->amount_allocated, 2) }}</strong></td>
                <td class="text-right">L {{ number_format($allocation->creditSale->balance_due, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="summary">
        <table>
            <tr>
                <td>Balance Anterior del Cliente:</td>
                <td class="text-right">L {{ number_format($payment->balance_before, 2) }}</td>
            </tr>
            <tr>
                <td>Pago Recibido:</td>
                <td class="text-right"><strong>L {{ number_format($payment->amount, 2) }}</strong></td>
            </tr>
            <tr class="highlight">
                <td>Nuevo Balance:</td>
                <td class="text-right">L {{ number_format($payment->balance_after, 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    @if($payment->notes)
    <div style="margin-top: 30px; padding: 10px; background-color: #fef3c7; border-left: 4px solid #f59e0b;">
        <strong>Notas:</strong><br>
        {{ $payment->notes }}
    </div>
    @endif

    <div class="signature-box">
        <div class="signature-line">
            Firma del Cliente
        </div>
    </div>

    <div class="footer">
        <p><strong>Gracias por su pago</strong></p>
        <p style="margin-top: 5px;">Este documento es un comprobante de pago oficial</p>
        <p style="margin-top: 10px; color: #666;">Generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
