<?php

namespace App\Models\GiftCard;

use App\Models\Customer;
use App\Models\Sale\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'initial_balance',
        'current_balance',
        'status',
        'issued_by',
        'customer_id',
        'sold_in_sale_id',
        'issued_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'is_active',
        'is_expired',
        'is_fully_redeemed',
    ];

    /**
     * Relationships
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function soldInSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sold_in_sale_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    /**
     * Accessors
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' &&
               $this->current_balance > 0 &&
               (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsFullyRedeemedAttribute(): bool
    {
        return $this->current_balance <= 0;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('current_balance', '>', 0)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Helper Methods
     */
    public function canRedeem(float $amount = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($amount !== null && $amount > $this->current_balance) {
            return false;
        }

        return true;
    }

    public function markAsRedeemed(): void
    {
        if ($this->current_balance <= 0) {
            $this->update(['status' => 'redeemed']);
        }
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsVoided(): void
    {
        $this->update(['status' => 'voided']);
    }
}
