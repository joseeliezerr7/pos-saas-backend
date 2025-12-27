<?php

namespace App\Models\CashRegister;

use App\Models\User\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClosing extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'cash_opening_id',
        'user_id',
        'expected_amount',
        'actual_amount',
        'difference',
        'denomination_breakdown',
        'closing_notes',
        'closed_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'denomination_breakdown' => 'array',
        'closed_at' => 'datetime',
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

    public function hasDifference(): bool
    {
        return abs($this->difference) > 0.01;
    }

    public function hasExcess(): bool
    {
        return $this->difference > 0.01;
    }

    public function hasShortage(): bool
    {
        return $this->difference < -0.01;
    }

    public function getDifferencePercentage(): float
    {
        if ($this->expected_amount == 0) {
            return 0;
        }

        return ($this->difference / $this->expected_amount) * 100;
    }
}
