<?php

namespace App\Models\Tenant;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'started_at',
        'expires_at',
        'canceled_at',
        'auto_renew',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'canceled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    protected $appends = ['starts_at'];

    protected $auditEvents = ['created', 'updated'];

    // Accessor for backwards compatibility with frontend
    public function getStartsAtAttribute()
    {
        return $this->started_at;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isActive(): bool
    {
        if ($this->status === 'canceled' || $this->status === 'expired') {
            return false;
        }

        if ($this->status === 'trial') {
            return $this->trial_ends_at && $this->trial_ends_at->isFuture();
        }

        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function daysRemaining(): int
    {
        if (!$this->isActive()) {
            return 0;
        }

        $expirationDate = $this->isOnTrial() ? $this->trial_ends_at : $this->expires_at;
        return now()->diffInDays($expirationDate, false);
    }

    public function cancel(): bool
    {
        return $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'auto_renew' => false,
        ]);
    }

    public function renew(): bool
    {
        $duration = $this->billing_cycle === 'yearly' ? 365 : 30;

        return $this->update([
            'status' => 'active',
            'expires_at' => now()->addDays($duration),
        ]);
    }
}
