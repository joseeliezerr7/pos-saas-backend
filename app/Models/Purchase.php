<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'supplier_id',
        'user_id',
        'approved_by',
        'purchase_number',
        'supplier_invoice_number',
        'subtotal',
        'tax',
        'discount',
        'total',
        'status',
        'payment_status',
        'notes',
        'expected_date',
        'ordered_at',
        'received_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'expected_date' => 'date',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'draft' || $this->status === 'ordered';
    }

    public function isReceived()
    {
        return $this->status === 'received';
    }

    public function canBeReceived()
    {
        return in_array($this->status, ['ordered', 'partial']);
    }

    public function calculateTotals()
    {
        $details = $this->details;

        $this->subtotal = $details->sum('subtotal');
        $this->tax = $details->sum(function ($detail) {
            return ($detail->subtotal * $detail->tax_rate) / 100;
        });
        $this->total = $this->subtotal + $this->tax - $this->discount;
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchase) {
            if (!$purchase->purchase_number) {
                $purchase->purchase_number = static::generatePurchaseNumber($purchase->tenant_id);
            }
        });
    }

    public static function generatePurchaseNumber($tenantId)
    {
        $lastPurchase = static::where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $number = $lastPurchase ? intval(substr($lastPurchase->purchase_number, 3)) + 1 : 1;

        return 'PUR' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
