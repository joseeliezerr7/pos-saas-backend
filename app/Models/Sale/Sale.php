<?php

namespace App\Models\Sale;

use App\Models\CashRegister\CashOpening;
use App\Models\Customer;
use App\Models\Fiscal\Invoice;
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

class Sale extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cash_opening_id',
        'user_id',
        'customer_id',
        'sale_number',
        'customer_rtn',
        'customer_name',
        'subtotal',
        'discount',
        'tax',
        'total',
        'payment_method',
        'payment_details',
        'amount_paid',
        'amount_change',
        'status',
        'notes',
        'sold_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_change' => 'decimal:2',
        'payment_details' => 'array',
        'sold_at' => 'datetime',
    ];

    protected $auditEvents = ['created', 'updated'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashOpening(): BelongsTo
    {
        return $this->belongsTo(CashOpening::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function hasInvoice(): bool
    {
        return $this->invoice()->exists();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getTotalItems(): int
    {
        return $this->details()->sum('quantity');
    }

    public function getProfit(): float
    {
        $totalCost = $this->details()->get()->sum(function ($detail) {
            return $detail->cost * $detail->quantity;
        });

        return $this->subtotal - $totalCost;
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('sold_at', [$startDate, $endDate]);
    }

    public function scopeForCashier($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (!$sale->sale_number) {
                $sale->sale_number = self::generateSaleNumber($sale->branch_id);
            }
        });
    }

    public static function generateSaleNumber(int $branchId): string
    {
        $lastSale = self::where('branch_id', $branchId)
                        ->latest('id')
                        ->first();

        $number = $lastSale ? ((int) substr($lastSale->sale_number, -8)) + 1 : 1;

        return sprintf('VEN-%03d-%08d', $branchId, $number);
    }
}
