<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    // Disable updated_at since the table only has created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}
