<?php

namespace App\Models\CashRegister;

use App\Models\Tenant\Branch;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegister extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'name',
        'printer_config',
        'status',
    ];

    protected $casts = [
        'printer_config' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openings(): HasMany
    {
        return $this->hasMany(CashOpening::class);
    }

    public function currentOpening(): ?CashOpening
    {
        return $this->openings()->where('is_open', true)->first();
    }

    public function isOpen(): bool
    {
        return $this->currentOpening() !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
