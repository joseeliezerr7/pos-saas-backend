<x-mail::message>
# Factura {{ $invoice->invoice_number }}

Estimado/a {{ $customer ? $customer->name : 'Cliente' }},

Le enviamos su factura correspondiente a la compra realizada.

<x-mail::panel>
**Detalles de la Factura:**

- **Número:** {{ $invoice->invoice_number }}
- **Fecha:** {{ $invoice->date }}
- **CAI:** {{ $invoice->cai ?? 'N/A' }}
- **Total:** L. {{ number_format($invoice->total, 2) }}
</x-mail::panel>

## Productos

<x-mail::table>
| Producto | Cantidad | Precio | Subtotal |
| :------- | -------: | -----: | -------: |
@foreach($items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | L. {{ number_format($item->unit_price, 2) }} | L. {{ number_format($item->total, 2) }} |
@endforeach
| | | **Total:** | **L. {{ number_format($invoice->total, 2) }}** |
</x-mail::table>

Gracias por su compra.

Saludos cordiales,<br>
**{{ $company->name }}**<br>
{{ $company->email }}<br>
{{ $company->phone }}
</x-mail::message>
