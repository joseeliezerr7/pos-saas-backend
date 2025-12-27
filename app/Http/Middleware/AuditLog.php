<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    /**
     * Routes that should be audited
     */
    protected array $auditableRoutes = [
        'api/sales*',
        'api/invoices*',
        'api/products*',
        'api/users*',
        'api/cais*',
        'api/cash-registers/*/open',
        'api/cash-registers/*/close',
    ];

    /**
     * HTTP methods to audit
     */
    protected array $auditableMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldAudit($request, $response)) {
            $this->logRequest($request, $response);
        }

        return $response;
    }

    /**
     * Determine if the request should be audited.
     */
    protected function shouldAudit(Request $request, Response $response): bool
    {
        if (!in_array($request->method(), $this->auditableMethods)) {
            return false;
        }

        foreach ($this->auditableRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the request.
     */
    protected function logRequest(Request $request, Response $response): void
    {
        $user = auth()->user();

        $logData = [
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $this->filterSensitiveData($request->all()),
            'response_status' => $response->getStatusCode(),
            'timestamp' => now()->toDateTimeString(),
        ];

        Log::channel('audit')->info('API Request', $logData);
    }

    /**
     * Filter sensitive data from request.
     */
    protected function filterSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***FILTERED***';
            }
        }

        return $data;
    }
}
