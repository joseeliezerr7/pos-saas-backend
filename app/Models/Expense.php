<?php

namespace App\Models;

use App\Models\Tenant\Branch;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'user_id',
        'supplier_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'receipt_number',
        'invoice_number',
        'notes',
        'attachment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    protected $auditEvents = ['created', 'updated', 'deleted'];

    // Category constants
    const CATEGORY_RENT = 'rent';
    const CATEGORY_UTILITIES = 'utilities';
    const CATEGORY_SALARIES = 'salaries';
    const CATEGORY_MAINTENANCE = 'maintenance';
    const CATEGORY_SUPPLIES = 'supplies';
    const CATEGORY_MARKETING = 'marketing';
    const CATEGORY_TRANSPORTATION = 'transportation';
    const CATEGORY_TAXES = 'taxes';
    const CATEGORY_INSURANCE = 'insurance';
    const CATEGORY_OTHER = 'other';

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_RENT => 'Alquiler',
            self::CATEGORY_UTILITIES => 'Servicios Públicos',
            self::CATEGORY_SALARIES => 'Salarios',
            self::CATEGORY_MAINTENANCE => 'Mantenimiento',
            self::CATEGORY_SUPPLIES => 'Suministros',
            self::CATEGORY_MARKETING => 'Marketing',
            self::CATEGORY_TRANSPORTATION => 'Transporte',
            self::CATEGORY_TAXES => 'Impuestos',
            self::CATEGORY_INSURANCE => 'Seguros',
            self::CATEGORY_OTHER => 'Otros',
        ];
    }

    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'check' => 'Cheque',
            'other' => 'Otro',
        ];
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Scopes
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeByPaymentMethod($query, string $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    // Accessors
    public function getCategoryLabelAttribute(): string
    {
        $categories = self::getCategories();
        return $categories[$this->category] ?? $this->category;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        $methods = self::getPaymentMethods();
        return $methods[$this->payment_method] ?? $this->payment_method;
    }
}
