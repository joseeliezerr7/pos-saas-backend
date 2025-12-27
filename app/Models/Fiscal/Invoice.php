<?php

namespace App\Models\Fiscal;

use App\Models\Sale\Sale;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'cai_id',
        'correlative_id',
        'invoice_number',
        'cai_number',
        'customer_rtn',
        'customer_name',
        'customer_address',
        'subtotal_exempt',
        'subtotal_taxed',
        'subtotal',
        'tax',
        'discount',
        'total',
        'total_in_words',
        'issued_at',
        'cai_expiration_date',
        'range_authorized',
        'is_voided',
        'void_reason',
        'void_notes',
        'voided_at',
        'voided_by',
        'invoice_uuid',
        'xml_signed',
        'qr_code',
    ];

    protected $casts = [
        'subtotal_exempt' => 'decimal:2',
        'subtotal_taxed' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'issued_at' => 'datetime',
        'cai_expiration_date' => 'date',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
    ];

    protected $auditEvents = ['created', 'updated'];

    protected $appends = ['status'];

    public function getStatusAttribute(): string
    {
        return $this->is_voided ? 'voided' : 'active';
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function cai(): BelongsTo
    {
        return $this->belongsTo(CAI::class, 'cai_id');
    }

    public function correlative(): BelongsTo
    {
        return $this->belongsTo(Correlative::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // TODO: Implement InvoiceVoid model
    /*
    public function voids(): HasMany
    {
        return $this->hasMany(InvoiceVoid::class);
    }
    */

    public function isVoided(): bool
    {
        return $this->is_voided;
    }

    public function canBeVoided(): bool
    {
        // Solo se pueden anular facturas del mismo período fiscal
        $sameMonth = $this->issued_at->isSameMonth(now());
        return !$this->is_voided && $sameMonth;
    }

    public function void(string $reason, string $notes = null, int $userId = null): bool
    {
        if (!$this->canBeVoided()) {
            return false;
        }

        $this->update([
            'is_voided' => true,
            'void_reason' => $reason,
            'void_notes' => $notes,
            'voided_at' => now(),
            'voided_by' => $userId ?? auth()->id(),
        ]);

        // Register void in separate table for SAR reporting
        // TODO: Implement InvoiceVoid model
        /*
        InvoiceVoid::create([
            'tenant_id' => $this->tenant_id,
            'invoice_id' => $this->id,
            'user_id' => $userId ?? auth()->id(),
            'reason' => $reason,
            'notes' => $notes,
            'voided_at' => now(),
        ]);
        */

        // Mark correlative as voided
        $this->correlative->markAsVoided();

        return true;
    }

    public function getFormattedInvoiceNumber(): string
    {
        return $this->invoice_number;
    }

    public function getRangeAuthorized(): string
    {
        if ($this->range_authorized) {
            return $this->range_authorized;
        }

        return sprintf(
            'Del %s al %s',
            $this->cai->range_start,
            $this->cai->range_end
        );
    }

    public function scopeNotVoided($query)
    {
        return $query->where('is_voided', false);
    }

    public function scopeVoided($query)
    {
        return $query->where('is_voided', true);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('issued_at', [$startDate, $endDate]);
    }

    public function scopeForCustomer($query, string $rtn)
    {
        return $query->where('customer_rtn', $rtn);
    }
}
