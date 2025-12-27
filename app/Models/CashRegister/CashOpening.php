<?php

namespace App\Models\CashRegister;

use App\Models\Sale\Sale;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashOpening extends Model
{
    use HasFactory, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'cash_register_id',
        'user_id',
        'opening_amount',
        'opening_notes',
        'opened_at',
        'is_open',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'is_open' => 'boolean',
    ];

    protected $appends = ['status'];

    protected $auditEvents = ['created', 'updated'];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function closing(): HasOne
    {
        return $this->hasOne(CashClosing::class);
    }

    /**
     * Get the status attribute (for frontend compatibility)
     */
    public function getStatusAttribute(): string
    {
        return $this->is_open ? 'open' : 'closed';
    }

    public function getTotalSales(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->sum('total');
    }

    public function getTotalCashSales(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->sum('total');
    }

    public function getTotalCardSales(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->where('payment_method', 'card')
            ->sum('total');
    }

    public function getTotalWithdrawals(): float
    {
        return (float) $this->transactions()
            ->where('type', 'withdrawal')
            ->sum('amount');
    }

    public function getTotalDeposits(): float
    {
        return (float) $this->transactions()
            ->where('type', 'deposit')
            ->sum('amount');
    }

    public function getExpectedAmount(): float
    {
        $total = $this->opening_amount;
        $total += $this->getTotalCashSales();
        $total += $this->getTotalDeposits();
        $total -= $this->getTotalWithdrawals();

        return (float) $total;
    }

    public function close(float $actualAmount, ?string $notes = null): CashClosing
    {
        $expectedAmount = $this->getExpectedAmount();
        $difference = $actualAmount - $expectedAmount;

        $closing = $this->closing()->create([
            'user_id' => auth()->id(),
            'expected_amount' => $expectedAmount,
            'actual_amount' => $actualAmount,
            'difference' => $difference,
            'closing_notes' => $notes,
            'closed_at' => now(),
        ]);

        $this->update(['is_open' => false]);

        return $closing;
    }

    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    public function scopeClosed($query)
    {
        return $query->where('is_open', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
