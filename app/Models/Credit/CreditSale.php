<?php

namespace App\Models\Credit;

use App\Models\Customer;
use App\Models\Sale\Sale;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditSale extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'customer_id',
        'original_amount',
        'amount_paid',
        'balance_due',
        'due_date',
        'status',
        'days_overdue',
        'paid_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Business logic helpers
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || ($this->balance_due > 0 && $this->due_date->isPast());
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'balance_due' => 0,
            'paid_at' => now(),
        ]);
    }

    /**
     * Scopes
     */
    public function scopeOverdue($query)
    {
        return $query->where('balance_due', '>', 0)
            ->where('due_date', '<', now());
    }

    public function scopePending($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('balance_due', '>', 0);
    }
}
