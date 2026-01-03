<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador para gestión de dominios personalizados del tenant
 */
class TenantDomainController extends Controller
{
    /**
     * Listar todos los dominios del tenant actual
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $domains = TenantDomain::where('tenant_id', $user->company_id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $domains,
        ]);
    }

    /**
     * Agregar un nuevo dominio al tenant
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255|unique:tenant_domains,domain',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // Validar formato del dominio
        $domain = strtolower(trim($request->domain));
        if (!$this->isValidDomain($domain)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_DOMAIN',
                    'message' => 'El formato del dominio no es válido',
                ]
            ], 422);
        }

        $tenantDomain = TenantDomain::create([
            'tenant_id' => $user->company_id,
            'domain' => $domain,
            'is_primary' => false,
            'is_verified' => false,
        ]);

        // Generar token de verificación
        $verificationToken = $tenantDomain->generateVerificationToken();

        return response()->json([
            'success' => true,
            'data' => $tenantDomain,
            'message' => 'Dominio agregado. Debes verificarlo antes de usarlo.',
            'verification' => [
                'token' => $verificationToken,
                'instructions' => 'Agrega un registro TXT con el valor: possaas-verification=' . $verificationToken,
            ]
        ], 201);
    }

    /**
     * Verificar un dominio
     */
    public function verify(Request $request, int $domainId): JsonResponse
    {
        $user = auth()->user();

        $tenantDomain = TenantDomain::where('id', $domainId)
            ->where('tenant_id', $user->company_id)
            ->firstOrFail();

        if ($tenantDomain->is_verified) {
            return response()->json([
                'success' => true,
                'message' => 'El dominio ya está verificado',
            ]);
        }

        // Verificar DNS TXT record
        $isValid = $this->verifyDNSRecord($tenantDomain->domain, $tenantDomain->verification_token);

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VERIFICATION_FAILED',
                    'message' => 'No se pudo verificar el dominio. Asegúrate de haber agregado el registro TXT correctamente.',
                    'instructions' => 'Agrega un registro TXT con el valor: possaas-verification=' . $tenantDomain->verification_token,
                ]
            ], 422);
        }

        $tenantDomain->markAsVerified();

        return response()->json([
            'success' => true,
            'data' => $tenantDomain,
            'message' => 'Dominio verificado exitosamente',
        ]);
    }

    /**
     * Establecer un dominio como primario
     */
    public function setPrimary(Request $request, int $domainId): JsonResponse
    {
        $user = auth()->user();

        $tenantDomain = TenantDomain::where('id', $domainId)
            ->where('tenant_id', $user->company_id)
            ->firstOrFail();

        if (!$tenantDomain->is_verified) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOMAIN_NOT_VERIFIED',
                    'message' => 'Debes verificar el dominio antes de establecerlo como primario',
                ]
            ], 422);
        }

        $tenantDomain->setAsPrimary();

        return response()->json([
            'success' => true,
            'data' => $tenantDomain,
            'message' => 'Dominio establecido como primario',
        ]);
    }

    /**
     * Eliminar un dominio
     */
    public function destroy(int $domainId): JsonResponse
    {
        $user = auth()->user();

        $tenantDomain = TenantDomain::where('id', $domainId)
            ->where('tenant_id', $user->company_id)
            ->firstOrFail();

        if ($tenantDomain->is_primary) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_PRIMARY',
                    'message' => 'No puedes eliminar el dominio primario. Establece otro dominio como primario primero.',
                ]
            ], 422);
        }

        $tenantDomain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dominio eliminado exitosamente',
        ]);
    }

    /**
     * Validar formato de dominio
     */
    private function isValidDomain(string $domain): bool
    {
        // Validar formato básico de dominio
        return (bool) preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain);
    }

    /**
     * Verificar registro DNS TXT
     */
    private function verifyDNSRecord(string $domain, string $expectedToken): bool
    {
        // Intentar obtener registros TXT del dominio
        $txtRecords = @dns_get_record($domain, DNS_TXT);

        if (!$txtRecords) {
            return false;
        }

        $expectedValue = 'possaas-verification=' . $expectedToken;

        foreach ($txtRecords as $record) {
            if (isset($record['txt']) && $record['txt'] === $expectedValue) {
                return true;
            }
        }

        return false;
    }
}
