<?php

namespace App\Models\CashRegister;

use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasFactory, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'cash_opening_id',
        'user_id',
        'type',
        'amount',
        'payment_method',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $auditEvents = ['created'];

    public function cashOpening(): BelongsTo
    {
        return $this->belongsTo(CashOpening::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSale(): bool
    {
        return $this->type === 'sale';
    }

    public function isWithdrawal(): bool
    {
        return $this->type === 'withdrawal';
    }

    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    public function isCorrection(): bool
    {
        return $this->type === 'correction';
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForCashOpening($query, int $cashOpeningId)
    {
        return $query->where('cash_opening_id', $cashOpeningId);
    }
}
