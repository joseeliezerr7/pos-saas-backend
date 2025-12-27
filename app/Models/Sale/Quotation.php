<?php

namespace App\Models\Sale;

use App\Models\Customer;
use App\Models\Tenant\Branch;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'user_id',
        'customer_id',
        'quotation_number',
        'customer_rtn',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'discount',
        'tax',
        'total',
        'status',
        'notes',
        'valid_until',
        'quoted_at',
        'converted_to_sale_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'datetime',
        'quoted_at' => 'datetime',
    ];

    protected $auditEvents = ['created', 'updated'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(QuotationDetail::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class, 'id', 'converted_to_sale_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->valid_until && $this->valid_until->isPast());
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted' && $this->converted_to_sale_id !== null;
    }

    public function getTotalItems(): int
    {
        return $this->details()->sum('quantity');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeValid($query)
    {
        return $query->where('valid_until', '>=', now())
                     ->whereIn('status', ['pending', 'accepted']);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('quoted_at', [$startDate, $endDate]);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quotation) {
            if (!$quotation->quotation_number) {
                $quotation->quotation_number = self::generateQuotationNumber($quotation->branch_id);
            }

            if (!$quotation->quoted_at) {
                $quotation->quoted_at = now();
            }

            if (!$quotation->valid_until) {
                $quotation->valid_until = now()->addDays(15); // Valid for 15 days by default
            }
        });
    }

    public static function generateQuotationNumber(int $branchId): string
    {
        $lastQuotation = self::where('branch_id', $branchId)
                            ->latest('id')
                            ->first();

        $number = $lastQuotation ? ((int) substr($lastQuotation->quotation_number, -8)) + 1 : 1;

        return sprintf('COT-%03d-%08d', $branchId, $number);
    }
}
