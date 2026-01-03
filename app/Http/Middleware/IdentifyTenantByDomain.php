<?php

namespace App\Http\Middleware;

use App\Models\Tenant\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para identificar el tenant desde el dominio/subdominio
 *
 * Este middleware se ejecuta antes de la autenticación y establece
 * el contexto del tenant basándose en el dominio de la petición.
 */
class IdentifyTenantByDomain
{
    /**
     * Dominios base que no son tenants
     */
    private const SYSTEM_DOMAINS = [
        'localhost',
        '127.0.0.1',
        'possaas.test',
        'possaas.com',
        'api.possaas.com',
        'admin.possaas.com',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost();

        // Si es un dominio del sistema, continuar sin establecer tenant
        if ($this->isSystemDomain($domain)) {
            return $next($request);
        }

        // Intentar obtener tenant por dominio completo
        $tenant = TenantDomain::getTenantByDomain($domain);

        // Si no se encuentra, intentar extraer subdominio
        if (!$tenant) {
            $subdomain = $this->extractSubdomain($domain);
            if ($subdomain) {
                // Buscar por slug de la empresa
                $tenant = \App\Models\Tenant\Company::where('slug', $subdomain)
                    ->orWhere('name', 'LIKE', "%{$subdomain}%")
                    ->first();
            }
        }

        // Si se encontró un tenant, establecerlo en el contexto
        if ($tenant) {
            // Validar que el tenant esté activo
            if (!$tenant->is_active) {
                return response()->json([
                    'error' => [
                        'code' => 'TENANT_INACTIVE',
                        'message' => 'Esta empresa está inactiva. Contacta al administrador.',
                    ]
                ], 403);
            }

            // Almacenar tenant en el contenedor de la aplicación
            App::instance('identified_tenant', $tenant);
            App::instance('tenant_id', $tenant->id);

            // Agregar header de respuesta para debugging
            $request->headers->set('X-Identified-Tenant', $tenant->id);
        }

        return $next($request);
    }

    /**
     * Verifica si el dominio es un dominio del sistema
     */
    private function isSystemDomain(string $domain): bool
    {
        foreach (self::SYSTEM_DOMAINS as $systemDomain) {
            if (str_ends_with($domain, $systemDomain) || $domain === $systemDomain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrae el subdominio de un dominio completo
     *
     * Ejemplos:
     * - empresa1.possaas.com -> empresa1
     * - empresa2.possaas.test -> empresa2
     * - possaas.com -> null
     */
    private function extractSubdomain(string $domain): ?string
    {
        $parts = explode('.', $domain);

        // Si solo tiene 2 partes (domain.com), no hay subdominio
        if (count($parts) < 3) {
            return null;
        }

        // El subdominio es la primera parte
        $subdomain = $parts[0];

        // Ignorar subdominios reservados
        $reserved = ['www', 'api', 'admin', 'app', 'mail', 'ftp'];
        if (in_array($subdomain, $reserved)) {
            return null;
        }

        return $subdomain;
    }
}
