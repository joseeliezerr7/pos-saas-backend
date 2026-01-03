<?php

namespace App\Models\Credit;

use App\Models\Customer;
use App\Models\Tenant\Branch;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPayment extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'branch_id',
        'user_id',
        'payment_number',
        'amount',
        'payment_method',
        'transaction_reference',
        'payment_details',
        'balance_before',
        'balance_after',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payment_details' => 'array',
        'payment_date' => 'datetime',
    ];

    protected $auditEvents = ['created', 'updated'];

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Boot method to auto-generate payment number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->payment_number) {
                $payment->payment_number = self::generatePaymentNumber($payment->tenant_id);
            }
        });
    }

    /**
     * Generate unique payment number
     */
    public static function generatePaymentNumber(int $tenantId): string
    {
        $lastPayment = self::where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $lastPayment ? ((int) substr($lastPayment->payment_number, -8)) + 1 : 1;

        return sprintf('PAG-%08d', $number);
    }
}
