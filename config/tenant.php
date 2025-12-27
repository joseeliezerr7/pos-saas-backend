<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Multi-tenant settings for the POS SaaS application
    |
    */

    'enabled' => env('TENANT_ENABLED', true),

    'cache_ttl' => env('TENANT_CACHE_TTL', 3600),

    'column' => 'tenant_id',

    'models' => [
        'company' => App\Models\Tenant\Company::class,
        'branch' => App\Models\Tenant\Branch::class,
        'subscription' => App\Models\Tenant\Subscription::class,
        'plan' => App\Models\Tenant\Plan::class,
    ],

    'excluded_tables' => [
        'migrations',
        'password_reset_tokens',
        'failed_jobs',
        'personal_access_tokens',
        'plans',
    ],

    'trial_days' => 14,

    'limits' => [
        'basic' => [
            'branches' => 1,
            'users' => 5,
            'products' => 500,
            'monthly_transactions' => 1000,
        ],
        'professional' => [
            'branches' => 3,
            'users' => 15,
            'products' => 5000,
            'monthly_transactions' => 10000,
        ],
        'enterprise' => [
            'branches' => 10,
            'users' => 50,
            'products' => 50000,
            'monthly_transactions' => 100000,
        ],
        'corporate' => [
            'branches' => -1, // unlimited
            'users' => -1,
            'products' => -1,
            'monthly_transactions' => -1,
        ],
    ],
];
