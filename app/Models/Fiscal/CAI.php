<?php

namespace App\Models\Fiscal;

use App\Models\Tenant\Branch;
use App\Traits\Auditable;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CAI extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, Auditable;

    protected $table = 'cais';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cai_number',
        'document_type',
        'range_start',
        'range_end',
        'total_documents',
        'used_documents',
        'authorization_date',
        'expiration_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'authorization_date' => 'date',
        'expiration_date' => 'date',
        'total_documents' => 'integer',
        'used_documents' => 'integer',
    ];

    protected $auditEvents = ['created', 'updated'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function correlatives(): HasMany
    {
        return $this->hasMany(Correlative::class, 'cai_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'cai_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expiration_date >= now()->toDateString()
            && $this->hasAvailableCorrelatives();
    }

    public function isExpired(): bool
    {
        return $this->expiration_date < now()->toDateString();
    }

    public function isDepleted(): bool
    {
        return $this->used_documents >= $this->total_documents;
    }

    public function hasAvailableCorrelatives(): bool
    {
        return $this->correlatives()->where('status', 'available')->exists();
    }

    public function getAvailableCorrelativesCount(): int
    {
        return $this->correlatives()->where('status', 'available')->count();
    }

    public function getRemainingDocuments(): int
    {
        return $this->total_documents - $this->used_documents;
    }

    public function isNearExpiration(int $days = 30): bool
    {
        return $this->expiration_date->diffInDays(now()) <= $days;
    }

    public function isLowOnCorrelatives(int $threshold = 100): bool
    {
        return $this->getAvailableCorrelativesCount() <= $threshold;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('expiration_date', '>=', now()->toDateString());
    }

    public function scopeForDocumentType($query, string $type)
    {
        return $query->where('document_type', $type);
    }
}
