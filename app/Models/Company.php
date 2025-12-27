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
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
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
