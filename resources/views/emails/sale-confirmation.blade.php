<x-mail::message>
# Confirmación de Venta #{{ $sale->id }}

Estimado/a {{ $customer ? $customer->name : 'Cliente' }},

Le confirmamos que hemos recibido su orden correctamente.

<x-mail::panel>
**Detalles de la Venta:**

- **Número de Venta:** #{{ $sale->id }}
- **Fecha:** {{ $sale->created_at->format('d/m/Y H:i') }}
- **Total:** L. {{ number_format($sale->total, 2) }}
- **Estado:** {{ $sale->status === 'completed' ? 'Completada' : 'Procesando' }}
</x-mail::panel>

## Productos Comprados

<x-mail::table>
| Producto | Cantidad | Precio | Subtotal |
| :------- | -------: | -----: | -------: |
@foreach($items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | L. {{ number_format($item->unit_price, 2) }} | L. {{ number_format($item->total, 2) }} |
@endforeach
| | | **Total:** | **L. {{ number_format($sale->total, 2) }}** |
</x-mail::table>

@if($sale->discount > 0)
**Descuento Aplicado:** L. {{ number_format($sale->discount, 2) }}
@endif

Gracias por su preferencia.

Saludos cordiales,<br>
**{{ $company->name }}**<br>
{{ $company->email }}<br>
{{ $company->phone }}
</x-mail::message>
