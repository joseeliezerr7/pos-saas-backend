<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Etiquetas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 10mm; }
        .labels-container { display: flex; flex-wrap: wrap; gap: 5mm; }
        .label {
            border: 1px solid #ddd;
            padding: 3mm;
            text-align: center;
            page-break-inside: avoid;
            @if($options['size'] === 'small')
                width: 50mm; height: 25mm;
            @elseif($options['size'] === 'large')
                width: 100mm; height: 50mm;
            @else
                width: 70mm; height: 35mm;
            @endif
        }
        @if($options['columns'] == 2)
            .label { width: 48%; }
        @elseif($options['columns'] == 3)
            .label { width: 31%; }
        @elseif($options['columns'] == 4)
            .label { width: 23%; }
        @endif
        .label-name { font-size: {{ $options['size'] === 'small' ? '8px' : ($options['size'] === 'large' ? '14px' : '10px') }}; font-weight: bold; margin-bottom: 2mm; }
        .label-sku { font-size: {{ $options['size'] === 'small' ? '7px' : ($options['size'] === 'large' ? '10px' : '8px') }}; color: #666; margin-bottom: 1mm; }
        .label-barcode { margin: 2mm 0; }
        .label-barcode svg { max-width: 100%; height: auto; }
        .label-price { font-size: {{ $options['size'] === 'small' ? '10px' : ($options['size'] === 'large' ? '16px' : '12px') }}; font-weight: bold; margin-top: 1mm; }
        .label-code { font-size: {{ $options['size'] === 'small' ? '6px' : ($options['size'] === 'large' ? '10px' : '8px') }}; margin-top: 1mm; }
    </style>
</head>
<body>
    <div class="labels-container">
        @foreach($labels as $label)
            <div class="label">
                <div class="label-name">{{ Str::limit($label['name'], 30) }}</div>
                @if($options['show_sku'] && !empty($label['sku']))
                    <div class="label-sku">SKU: {{ $label['sku'] }}</div>
                @endif
                <div class="label-barcode">
                    <div style="text-align:center; background:white; padding:5px;">
                        {!! $label['barcode_html'] !!}
                    </div>
                </div>
                @if($options['show_price'])
                    <div class="label-price">L. {{ number_format($label['price'], 2) }}</div>
                @endif
                <div class="label-code">{{ $label['barcode'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
