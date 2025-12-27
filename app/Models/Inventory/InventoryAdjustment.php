<?php

namespace App\Models\Inventory;

use App\Models\Tenant\Branch;
use App\Models\User\User;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    use HasFactory, HasTenantScope, Auditable;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'user_id',
        'approved_by',
        'adjustment_number',
        'reason',
        'notes',
        'total_adjustment',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'total_adjustment' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($adjustment) {
            if (!$adjustment->adjustment_number) {
                $adjustment->adjustment_number = static::generateAdjustmentNumber($adjustment->branch_id);
            }
        });
    }

    /**
     * Generate adjustment number
     */
    protected static function generateAdjustmentNumber(int $branchId): string
    {
        $branch = Branch::find($branchId);
        $prefix = $branch ? $branch->code : 'ADJ';
        $date = now()->format('Ymd');

        $lastAdjustment = static::where('branch_id', $branchId)
            ->whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastAdjustment ? (intval(substr($lastAdjustment->adjustment_number, -4)) + 1) : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Relationships
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentDetail::class, 'adjustment_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Helpers
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function approve(?int $userId = null): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);

        return true;
    }

    public function reject(?int $userId = null): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Get reason label
     */
    public function getReasonLabelAttribute(): string
    {
        $labels = [
            'physical_count' => 'Conteo Físico',
            'damage' => 'Daño/Deterioro',
            'theft' => 'Robo/Pérdida',
            'expiration' => 'Caducidad',
            'correction' => 'Corrección',
            'other' => 'Otro',
        ];

        return $labels[$this->reason] ?? $this->reason;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
