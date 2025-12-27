<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fiscal Configuration (SAR Honduras)
    |--------------------------------------------------------------------------
    |
    | Configuration for Honduras tax authority (SAR) compliance
    |
    */

    'sar' => [
        'enabled' => env('FISCAL_SAR_ENABLED', true),

        'document_types' => [
            'FACTURA' => 'Factura',
            'NOTA_CREDITO' => 'Nota de Crédito',
            'NOTA_DEBITO' => 'Nota de Débito',
            'RECIBO_HONORARIOS' => 'Recibo por Honorarios',
            'FACTURA_EXPORTACION' => 'Factura de Exportación',
        ],

        'tax_rates' => [
            'ISV' => 15.00, // Impuesto Sobre Ventas
            'ISV_18' => 18.00, // Tasa especial
        ],

        'cai' => [
            'length' => 50,
            'alert_days_before_expiration' => env('FISCAL_CAI_ALERT_DAYS', 30),
            'format' => '/^[A-Z0-9\-]+$/',
        ],

        'correlative' => [
            'format' => '%03d-%03d-%02d-%08d', // XXX-XXX-XX-XXXXXXXX
            'alert_count' => env('FISCAL_CORRELATIVE_ALERT_COUNT', 100),
            'components' => [
                'establishment' => 3,
                'point_of_emission' => 3,
                'document_type' => 2,
                'sequential' => 8,
            ],
        ],

        'rtn' => [
            'format' => '/^\d{4}-\d{4}-\d{5}$/', // XXXX-XXXX-XXXXX
            'consumer_final' => '0801-0000-00000',
        ],

        'invoice' => [
            'required_fields' => [
                'cai_number',
                'invoice_number',
                'customer_name',
                'customer_rtn',
                'subtotal',
                'tax',
                'total',
                'issued_at',
                'cai_expiration_date',
            ],
            'void_reasons' => [
                'ERROR_DIGITACION' => 'Error en la digitación',
                'DEVOLUCION' => 'Devolución de mercancía',
                'DESCUENTO_POSTERIOR' => 'Descuento posterior',
                'DUPLICADO' => 'Factura duplicada',
                'OTRO' => 'Otro motivo',
            ],
        ],

        'reports' => [
            'monthly_deadline_day' => 10, // Day of next month
            'formats' => ['pdf', 'excel', 'xml'],
        ],

        'dte' => [
            'enabled' => env('FISCAL_DTE_ENABLED', false),
            'api_url' => env('FISCAL_DTE_API_URL', 'https://api.sar.gob.hn'),
            'api_key' => env('FISCAL_DTE_API_KEY'),
            'certificate_path' => env('FISCAL_DTE_CERT_PATH'),
            'certificate_password' => env('FISCAL_DTE_CERT_PASSWORD'),
        ],
    ],

    'currency' => [
        'code' => 'HNL',
        'symbol' => 'L',
        'decimal_separator' => '.',
        'thousand_separator' => ',',
        'decimals' => 2,
    ],
];
