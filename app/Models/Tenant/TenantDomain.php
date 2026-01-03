<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo para gestión de dominios personalizados por tenant
 *
 * Permite que cada tenant tenga su propio subdominio o dominio personalizado
 * Ejemplos:
 * - empresa1.possaas.com (subdominio)
 * - www.empresa1.com (dominio personalizado)
 */
class TenantDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'is_verified',
        'verified_at',
        'verification_token',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Relación con Company (Tenant)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Genera un token de verificación
     */
    public function generateVerificationToken(): string
    {
        $this->verification_token = Str::random(64);
        $this->save();

        return $this->verification_token;
    }

    /**
     * Marca el dominio como verificado
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_token' => null,
        ]);
    }

    /**
     * Establece este dominio como primario
     */
    public function setAsPrimary(): void
    {
        // Primero quitar el flag de primario a otros dominios del mismo tenant
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Establecer este como primario
        $this->update(['is_primary' => true]);
    }

    /**
     * Valida si el dominio está disponible
     */
    public static function isDomainAvailable(string $domain): bool
    {
        return !static::where('domain', $domain)->exists();
    }

    /**
     * Obtiene el tenant por dominio
     */
    public static function getTenantByDomain(string $domain): ?Company
    {
        $tenantDomain = static::where('domain', $domain)
            ->where('is_verified', true)
            ->first();

        return $tenantDomain?->company;
    }

    /**
     * Scope para dominios verificados
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope para dominio primario
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
