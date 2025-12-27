<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Correlative extends Model
{
    use HasFactory;

    protected $fillable = [
        'cai_id',
        'number',
        'formatted_number',
        'status',
        'used_at',
        'voided_at',
    ];

    protected $casts = [
        'number' => 'integer',
        'used_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function cai(): BelongsTo
    {
        return $this->belongsTo(CAI::class, 'cai_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function markAsUsed(): bool
    {
        return $this->update([
            'status' => 'used',
            'used_at' => now(),
        ]);
    }

    public function markAsVoided(): bool
    {
        return $this->update([
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    public function scopeForCAI($query, int $caiId)
    {
        return $query->where('cai_id', $caiId);
    }
}
