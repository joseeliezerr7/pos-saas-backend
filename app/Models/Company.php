<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'rtn',
        'email',
        'phone',
        'address',
        'logo',
        'is_active',
        'settings',
        'notification_settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'notification_settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'notification_settings' => '{"send_invoice_email":true,"send_sale_confirmation":false,"send_low_stock_alerts":true,"low_stock_alert_email":""}',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'tenant_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class, 'tenant_id')
            ->where('status', 'active')
            ->latest();
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'tenant_id')->latest();
    }
}
