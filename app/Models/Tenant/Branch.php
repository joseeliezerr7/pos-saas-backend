<?php

namespace App\Models\Tenant;

use App\Models\CashRegister\CashRegister;
use App\Models\Fiscal\CAI;
use App\Models\Inventory\Stock;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'address',
        'phone',
        'email',
        'is_main',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function cais(): HasMany
    {
        return $this->hasMany(CAI::class);
    }

    public function isMain(): bool
    {
        return $this->is_main;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
