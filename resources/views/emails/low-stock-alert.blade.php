<x-mail::message>
# Alerta de Stock Bajo

Hola,

Le informamos que los siguientes productos tienen stock bajo y requieren reposición:

<x-mail::table>
| Producto | Stock Actual | Stock Mínimo | Acción Requerida |
| :------- | -----------: | -----------: | :--------------: |
@foreach($products as $product)
| {{ $product->name }} | {{ $product->stock }} | {{ $product->min_stock ?? 10 }} | @if($product->stock == 0) **URGENTE** @else Reponer @endif |
@endforeach
</x-mail::table>

@component('mail::panel')
**Total de productos con stock bajo:** {{ count($products) }}

Se recomienda realizar pedidos de reposición lo antes posible para evitar ruptura de stock.
@endcomponent

Sistema POS SaaS<br>
**{{ $company->name }}**
</x-mail::message>
